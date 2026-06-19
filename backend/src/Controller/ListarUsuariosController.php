<?php

namespace App\Controller;

use App\Repository\UsuarioRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class ListarUsuariosController extends AbstractController {

    public function __construct(
        private UsuarioRepository $usuarioRepository,
    ) {
    }

    #[Route('/api/users-brief', name: 'api_users_brief', methods: ['GET'])]
    public function __invoke(): JsonResponse {
        $usuarios = $this->usuarioRepository->findAll();

        $result = array_map(fn($u) => [
            'id' => $u->getId(),
            'username' => $u->getUsername(),
            'label' => $u->getFullName() . ' (' . $u->getUsername() . ')',
        ], $usuarios);

        return $this->json($result);
    }
}
