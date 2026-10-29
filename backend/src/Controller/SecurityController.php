<?php

namespace App\Controller;

use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\ApiToken;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Autenticación: el firewall valida usuario/contraseña (`json_login` en
 * `/api/login`) y aquí se entrega el Bearer. El logout lo resuelve
 * `App\EventListener\LogoutListener`. Lo que puede hacer el usuario se pide
 * aparte (`GET /api/me/acceso`, ADR-039).
 */
class SecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** Devuelve (y reactiva) el token del usuario; lo crea si no tiene. */
    #[Route('/api/login', name: 'app_login', methods: ['POST'])]
    public function login(IriConverterInterface $iriConverter, #[CurrentUser] ?Usuario $user = null): Response
    {
        if (!$user) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $user->getToken();
        if (!$token) {
            $token = (new ApiToken())->setUsuario($user);
            $user->addApiToken($token);
            $this->entityManager->persist($token);
        }
        $token->setActivo(true);
        $this->entityManager->flush();

        return $this->json([
            'token' => $token->getToken(),
            'username' => $user->getUsername(),
            'uri' => $iriConverter->getIriFromResource($user),
        ]);
    }

    /** Token válido del usuario autenticado (por Bearer: el firewall no guarda sesión). */
    #[Route('/auth', name: 'auth', methods: ['POST'])]
    public function auth(#[CurrentUser] ?Usuario $user = null): Response
    {
        if (!$user) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(['token' => $user->getValidTokenStrings(), 'username' => $user->getUsername()]);
    }
}
