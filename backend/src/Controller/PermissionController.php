<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Security\PermissionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
#[Route('/api/me/permissions', name: 'api_me_permissions', methods: ['GET'])]
class PermissionController extends AbstractController {

    public function __invoke(
        #[CurrentUser] ?Usuario $user,
        $permissionManager,
    ): JsonResponse {
        if (!$user) {
            return $this->json(['actions' => []]);
        }

        return $this->json([
            'actions' => $permissionManager->getEffectiveActions($user),
        ]);
    }
}
