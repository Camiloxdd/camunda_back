<?php

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthUserController extends AbstractController
{
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
    ): Response {
        // Decodificamos el JSON
        $data = json_decode($request->getContent(), true);

        // Depuración rápida si algo llega mal
        if ($data === null) {
            return new JsonResponse(['message' => 'Invalid JSON format'], Response::HTTP_BAD_REQUEST);
        }

        // Aceptamos ambas variantes de nombres
        $correo = $data['correo'] ?? $data['email'] ?? null;
        $contraseña = $data['contraseña'] ?? $data['password'] ?? null;

        // Validamos
        if (!$correo || !$contraseña) {
            return new JsonResponse(['message' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        // Buscamos el usuario
        $user = $em->getRepository(User::class)->findOneBy(['correo' => $correo]);
        if (!$user) {
            return new JsonResponse(['message' => 'Invalid credentials (user not found)'], Response::HTTP_UNAUTHORIZED);
        }

        // Verificamos la contraseña
        if (!$passwordHasher->isPasswordValid($user, $contraseña)) {
            return new JsonResponse(['message' => 'Invalid credentials (wrong password)'], Response::HTTP_UNAUTHORIZED);
        }

        // Creamos el token JWT
        $token = $jwtManager->create($user);

        // Devolvemos la respuesta con la info básica y el token
        $response = new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getCorreo(),
            'nombre' => $user->getNombre(),
            'cargo' => $user->getCargo(),
            'area' => $user->getArea(),
            'token' => $token
        ]);

        // También podemos incluir la cookie (opcional)
        $cookie = Cookie::create('token')
            ->withValue($token)
            ->withExpires(new \DateTime('+1 hour'))
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSameSite('lax');

        $response->headers->setCookie($cookie);

        return $response;
    }


    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /**@var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'correo' => $user->getCorreo(),
            'nombre' => $user->getNombre(),
            'cargo' => $user->getCargo(),
            'area' => $user->getArea(),
            'sede' => $user->getSede() ?? null,
            'super_admin' => $user->getSuperAdmin(),
            'aprobador' => $user->getAprobador(),
            'solicitante' => $user->getSolicitante(),
            'comprador' => $user->getComprador(),
        ]);
    }

    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = new JsonResponse(['message' => 'Logged out successfully']);
        $cookie = Cookie::create('token')
            ->withValue('')
            ->withExpires(new \DateTime('-1 hour'))
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSameSite('lax');
        $response->headers->setCookie($cookie);
        return $response;
    }
}
