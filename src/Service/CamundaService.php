<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CamundaService
{
    private string $zeebeUrl;
    private string $tasklistUrl;
    private string $authUrl;
    private string $clientId;
    private string $clientSecret;
    private string $audience;

    private HttpClientInterface $http;

    public function __construct(HttpClientInterface $http)
    {
        $this->http          = $http;
        $this->zeebeUrl      = $_ENV['CAMUNDA_ZEEBE_URL'];
        $this->tasklistUrl   = $_ENV['CAMUNDA_TASKLIST_BASE_URL'];

        // 🔥 variables correctas de Camunda 8 SaaS
        $this->authUrl       = $_ENV['ZEEBE_AUTHORIZATION_SERVER_URL'];
        $this->clientId      = $_ENV['ZEEBE_CLIENT_ID'];
        $this->clientSecret  = $_ENV['ZEEBE_CLIENT_SECRET'];
        $this->audience      = $_ENV['AUDIENCE'];
    }

    private function getAccessToken(): string
    {
        $response = $this->http->request("POST", $this->authUrl, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'audience'      => $this->audience
            ]
        ]);

        $data = $response->toArray(false);

        if (!isset($data['access_token'])) {
            throw new \Exception('No se pudo obtener token de Camunda: ' . json_encode($data));
        }

        return $data['access_token'];
    }

    public function startProcess(array $variables, int $version = null)
    {
        $token = $this->getAccessToken();

        $payload = [
            "processDefinitionId" => "Process_1a2wlvt",
            "variables" => $this->normalizeVariables($variables),
        ];

        if ($version !== null) {
            $payload["version"] = $version;
        }

        $resp = $this->http->request("POST", "$this->zeebeUrl/v2/process-instances", [
            "headers" => [
                "Authorization" => "Bearer $token",
                "Content-Type" => "application/json"
            ],
            "json" => $payload
        ]);

        return $resp->toArray(false);
    }

    private function normalizeVariables(array $variables): array|object
    {
        if ($variables === []) {
            return new \stdClass();
        }

        if (function_exists('array_is_list') && array_is_list($variables)) {
            return ["items" => $variables];
        }

        return $variables;
    }

    public function searchTasks(array $body)
    {
        $token = $this->getAccessToken();

        // Estructura por defecto compatible con /v2/user-tasks/search
        $defaultPayload = [
            'filter' => [
                'state' => 'CREATED',
            ],
            'page' => [
                'limit' => 50,
            ],
        ];

        // Permitir que el body que venga sobreescriba porciones de filter/page
        $payload = array_replace_recursive($defaultPayload, $body ?? []);

        file_put_contents(
            __DIR__ . "/../../var/log/camunda_debug.log",
            "Request to Camunda Tasklist:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n",
            FILE_APPEND
        );

        $resp = $this->http->request(
            'POST',
            "$this->tasklistUrl/v2/user-tasks/search",
            [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]
        );

        return $resp->toArray(false);
    }

    public function completeUserTask(string $userTaskKey, array $payload): array
    {
        try {
            $url = $this->tasklistUrl . "/v2/user-tasks/{$userTaskKey}/completion";
            $token = $this->getAccessToken();

            // Asegura que siempre haya 'variables' como objeto
            if (!isset($payload['variables']) || !is_array($payload['variables'])) {
                $payload['variables'] = new \stdClass();
            }

            if (!isset($payload['action'])) {
                $payload['action'] = 'complete';
            }

            $resp = $this->http->request("POST", $url, [
                "headers" => [
                    "Authorization" => "Bearer $token",
                    "Content-Type" => "application/json"
                ],
                "json" => $payload
            ]);

            return [
                "status" => $resp->getStatusCode(),
                "data" => json_decode($resp->getContent(), true)
            ];
        } catch (\Throwable $e) {
            return [
                "status" => 500,
                "error" => $e->getMessage()
            ];
        }
    }

    public function cancelProcess(string $processInstanceKey)
    {
        $token = $this->getAccessToken();

        try {
            $resp = $this->http->request(
                'POST',
                $this->zeebeUrl . "/v2/process-instances/{$processInstanceKey}/cancellation",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => new \stdClass(), // cuerpo vacío
                ]
            );

            // Aquí Camunda debe devolver 204 si todo fue bien
            return [
                'status'   => $resp->getStatusCode(),
                'response' => $resp->toArray(false),
            ];
        } catch (\Symfony\Component\HttpClient\Exception\ClientException $e) {
            // Propaga el status y el body de Camunda
            $response = $e->getResponse();
            return [
                'status'   => $response->getStatusCode(),
                'response' => $response->toArray(false),
            ];
        }
    }
}
