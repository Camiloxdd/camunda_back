<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user', name: 'user_')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('/list', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        try {
            $users = $this->userRepository->findAll();

            if (empty($users)) {
                return $this->json(['message' => 'No hay usuarios registrados'], Response::HTTP_NOT_FOUND);
            }

            $data = array_map(fn(User $user) => [
                'id' => $user->getId(),
                'nombre' => $user->getNombre(),
                'correo' => $user->getCorreo(),
                'cargo' => $user->getCargo(),
                'telefono' => $user->getTelefono(),
                'area' => $user->getArea(),
                'sede' => $user->getSede(),
                'super_admin' => $user->getSuperAdmin(),
                'aprobador' => $user->getAprobador(),
                'solicitante' => $user->getSolicitante(),
                'comprador' => $user->getComprador(),
            ], $users);

            return $this->json($data);
        } catch (\Exception $e) {
            $this->logger->error('Error en list: ' . $e->getMessage());
            return $this->json(['message' => 'Server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $nombre = $data['nombre'] ?? null;
            $correo = $data['correo'] ?? null;
            $contraseña = $data['contraseña'] ?? null;
            $cargo = $data['cargo'] ?? null;
            $telefono = $data['telefono'] ?? null;
            $area = $data['area'] ?? null;
            $sede = $data['sede'] ?? null;
            $super_admin = $data['super_admin'] ?? false;
            $aprobador = $data['aprobador'] ?? false;
            $solicitante = $data['solicitante'] ?? false;
            $comprador = $data['comprador'] ?? false;

            if (!$nombre || !$correo || !$contraseña) {
                return $this->json(['message' => 'Faltan campos obligatorios.'], Response::HTTP_BAD_REQUEST);
            }

            $user = new User();
            $user->setNombre($nombre);
            $user->setCorreo($correo);
            $user->setContraseña($this->passwordHasher->hashPassword($user, $contraseña));
            $user->setCargo($cargo);
            $user->setTelefono($telefono);
            $user->setArea($area);
            $user->setSede($sede);
            $user->setSuperAdmin((bool)$super_admin);
            $user->setAprobador((bool)$aprobador);
            $user->setSolicitante((bool)$solicitante);
            $user->setComprador((bool)$comprador);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->json(
                ['message' => 'Usuario creado correctamente', 'id' => $user->getId()],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            $this->logger->error('Error en create: ' . $e->getMessage());
            return $this->json(['message' => 'Error en el servidor'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/update/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->userRepository->find($id);

            if (!$user) {
                return $this->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            if (isset($data['nombre'])) $user->setNombre($data['nombre']);
            if (isset($data['correo'])) $user->setCorreo($data['correo']);
            if (isset($data['cargo'])) $user->setCargo($data['cargo']);
            if (isset($data['telefono'])) $user->setTelefono($data['telefono']);
            if (isset($data['area'])) $user->setArea($data['area']);
            if (isset($data['sede'])) $user->setSede($data['sede']);
            if (isset($data['super_admin'])) $user->setSuperAdmin((bool)$data['super_admin']);
            if (isset($data['aprobador'])) $user->setAprobador((bool)$data['aprobador']);
            if (isset($data['solicitante'])) $user->setSolicitante((bool)$data['solicitante']);
            if (isset($data['comprador'])) $user->setComprador((bool)$data['comprador']);

            $this->entityManager->flush();

            return $this->json(['message' => 'Usuario actualizado correctamente']);
        } catch (\Exception $e) {
            $this->logger->error('Error en update: ' . $e->getMessage());
            return $this->json(['message' => 'Error del servidor'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        try {
            $user = $this->userRepository->find($id);

            if (!$user) {
                return $this->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return $this->json(['message' => 'Usuario eliminado correctamente']);
        } catch (\Exception $e) {
            $this->logger->error('Error en delete: ' . $e->getMessage());
            return $this->json(['message' => 'Error del servidor'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}