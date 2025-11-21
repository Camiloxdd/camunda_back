<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CamundaService
{
    private HttpClientInterface $client;
    private string $authUrl;
    private string $clientId;
    private string $clientSecret;
    private string $audience;
    private string $tasklistBaseUrl;
    private string $zeebeBaseUrl;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;

        // Leer desde env (ajusta si prefieres ParameterBagInterface / %env()%)
        $this->authUrl = $_ENV['ZEEBE_AUTHORIZATION_SERVER_URL'] ?? 'https://login.cloud.camunda.io/oauth/token';
        $this->clientId = $_ENV['ZEEBE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['ZEEBE_CLIENT_SECRET'] ?? '';
        $this->audience = $_ENV['ZEEBE_AUDIENCE'] ?? ($_ENV['AUDIENCE'] ?? 'tasklist.camunda.io');
        $this->tasklistBaseUrl = $_ENV['CAMUNDA_TASKLIST_BASE_URL'] ?? 'https://sin-1.tasklist.camunda.io/e124b5cc-12b1-4b3b-be0e-6b18860d1230';
        $this->zeebeBaseUrl = $_ENV['CAMUNDA_ZEEBE_URL'] ?? 'https://sin-1.zeebe.camunda.io/e124b5cc-12b1-4b3b-be0e-6b18860d1230';
    }

    private function getAccessToken(): string
    {
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'audience' => $this->audience,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $response = $this->client->request('POST', $this->authUrl, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => $body,
        ]);

        $content = $response->getContent(false);
        $data = json_decode($content, true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('No access token returned from Camunda auth: ' . substr($content, 0, 200));
        }
        return $data['access_token'];
    }

    // Nuevo: helper equivalente al snippet JS (POST form-url-encoded -> devuelve access_token)
    public function getAccessTokenViaForm(): string
    {
        $params = [
            'grant_type'    => 'client_credentials',
            'audience'      => $this->audience,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];

        $response = $this->client->request('POST', $this->authUrl, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query($params),
        ]);

        $content = $response->getContent(false);
        $data = json_decode($content, true);

        if (empty($data['access_token'])) {
            $msg = is_string($content) ? substr($content, 0, 1000) : '';
            throw new \RuntimeException('No access token returned from Camunda auth: ' . $msg);
        }

        return $data['access_token'];
    }

    public function startProcess(string $processDefinitionId, array $variables = []): array
    {
        $token = $this->getAccessToken();
        $url = rtrim($this->zeebeBaseUrl, '/') . '/v2/process-instances';
        $payload = ['processDefinitionId' => $processDefinitionId, 'variables' => (object)$variables];

        $response = $this->client->request('POST', $url, [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return json_decode($response->getContent(), true);
    }

    public function startRevision(array $variables = []): array
    {
        $token = $this->getAccessToken();
        $url = rtrim($this->zeebeBaseUrl, '/') . '/v2/process-instances';
        $payload = ['processDefinitionId' => 'Process_1pw9wvj', 'version' => -1, 'variables' => (object)$variables];

        $response = $this->client->request('POST', $url, [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return json_decode($response->getContent(), true);
    }

    public function searchTasks(array $searchBody = []): array
    {
        $token = $this->getAccessToken();
        $url = rtrim($this->tasklistBaseUrl, '/') . '/v2/user-tasks/search';

        $response = $this->client->request('POST', $url, [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => $searchBody,
        ]);

        return json_decode($response->getContent(), true);
    }

    public function completeUserTask(string $userTaskKey, array $variables = []): array
    {
        $token = $this->getAccessToken();
        $url = rtrim($this->tasklistBaseUrl, '/') . '/v2/user-tasks/' . rawurlencode($userTaskKey) . '/completion';

        $response = $this->client->request('POST', $url, [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => ['variables' => (object)$variables],
        ]);

        return json_decode($response->getContent(), true);
    }

    public function cancelProcess(string $processInstanceKey): array
    {
        $token = $this->getAccessToken();
        $url = rtrim($this->zeebeBaseUrl, '/') . '/v2/process-instances/' . rawurlencode($processInstanceKey) . '/cancellation';

        $response = $this->client->request('POST', $url, [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => new \stdClass(),
        ]);

        return json_decode($response->getContent(), true);
    }
}
