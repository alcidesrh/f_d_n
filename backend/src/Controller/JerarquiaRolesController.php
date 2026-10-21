<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Rol\JerarquiaInvalida;
use App\Rol\JerarquiaRoles;
use App\Security\Voter\EntityVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/** Jerarquía de roles como árbol (`App\Rol\JerarquiaRoles`), editada en `/configuracion/roles`. */
#[AsController]
class JerarquiaRolesController extends AbstractController
{
    public function __construct(
        private readonly JerarquiaRoles $jerarquia,
    ) {}

    /** `[{ id, children: [...] }]`: solo los roles con padres o hijos. */
    #[Route('/api/rol-jerarquia', name: 'api_rol_jerarquia', methods: ['GET'])]
    public function leer(): JsonResponse
    {
        $this->denyUnlessAllowed(EntityVoter::READ);

        return $this->json($this->jerarquia->leer());
    }

    /** Reemplaza la jerarquía completa; responde la guardada. */
    #[Route('/api/rol-jerarquia', name: 'api_rol_jerarquia_guardar', methods: ['PUT'])]
    public function guardar(Request $request): JsonResponse
    {
        $this->denyUnlessAllowed(EntityVoter::UPDATE);

        try {
            $this->jerarquia->escribir($request->toArray());
        } catch (JerarquiaInvalida $error) {
            return $this->json(['error' => $error->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->jerarquia->leer());
    }

    /** `ROLE_ADMIN` (con `role_hierarchy`) o el permiso plano `role.{read,update}`, como los menús. */
    private function denyUnlessAllowed(string $attribute): void
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->denyAccessUnlessGranted($attribute, Role::class);
        }
    }
}
