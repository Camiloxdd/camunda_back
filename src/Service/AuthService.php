<?php

namespace App\Service;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthService
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
    ) {}

    /**
     * Crear un token JWT con los datos del usuario
     */
    public function createJwt(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    /**
     * Extraer el token de la cookie o del header Authorization
     */
    public function getTokenFromRequest(\Symfony\Component\HttpFoundation\Request $request): ?string
    {
        // Intentar obtener del header Authorization
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Intentar obtener de la cookie
        return $request->cookies->get('token');
    }
}