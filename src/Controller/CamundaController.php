<?php

namespace App\Controller;

use App\Service\CamundaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CamundaController extends AbstractController
{
    #[Route('/api/process/start', methods: ['POST'])]
    public function startProcess(Request $request, CamundaService $camunda): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $variables = $data['variables'] ?? [];

            $result = $camunda->startProcess($variables);
            $status = isset($result['status']) && is_int($result['status']) ? $result['status'] : 200;

            return new JsonResponse($result, $status);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al iniciar proceso'], 500);
        }
    }

    #[Route('/api/process/start-revision', methods: ['POST'])]
    public function startProcessRevision(Request $request, CamundaService $camunda): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $variables = $data['variables'] ?? [];

            $result = $camunda->startProcess($variables, -1);
            $status = isset($result['status']) && is_int($result['status']) ? $result['status'] : 200;

            return new JsonResponse($result, $status);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al iniciar proceso de revisión'], 500);
        }
    }

    #[Route('/api/tasks/search', methods: ['POST'])]
    public function searchTasks(Request $request, CamundaService $camunda): JsonResponse
    {
        try {
            $body = json_decode($request->getContent(), true);

            // Garantizamos que sea un array
            if (!is_array($body)) {
                $body = [];
            }

            $result = $camunda->searchTasks($body);

            $status = isset($result['status']) && is_int($result['status'])
                ? $result['status']
                : 200;

            return new JsonResponse($result, $status);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error'   => 'Error al consultar tareas',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    #[Route('/api/tasks/{userTaskKey}/complete', methods: ['POST'])]
    public function completeTask(string $userTaskKey, Request $request, CamundaService $camunda): JsonResponse
    {
        try {
            $body = json_decode($request->getContent(), true) ?? [];

            if (!isset($body['action']) || $body['action'] !== 'complete') {
                return new JsonResponse(['error' => 'action debe ser "complete"'], 400);
            }

            if (!isset($body['variables']) || !is_array($body['variables'])) {
                return new JsonResponse(['error' => 'variables es obligatorio'], 400);
            }

            $result = $camunda->completeUserTask($userTaskKey, $body);

            return new JsonResponse($result, $result['status'] ?? 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error al completar tarea',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    #[Route('/api/process/{key}/cancel', methods: ['POST'])]
    public function cancelProcess(string $key, CamundaService $camunda): JsonResponse
    {
        try {
            $result = $camunda->cancelProcess($key);

            return new JsonResponse([
                "message" => "Proceso cancelado correctamente",
                "response" => $result
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error al cancelar proceso',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
