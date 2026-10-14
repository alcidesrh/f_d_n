<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Security\Voter\EntityVoter;
use App\Usuario\EliminacionInvalida;
use App\Usuario\EliminacionUsuarios;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Borrado de usuarios con permiso `usuario.delete`. Primero se analiza lo que
 * depende de ellos; después se eliminan con una decisión por tabla
 * (`desvincular` o `eliminar`). Ver `App\Usuario\EliminacionUsuarios`.
 */
#[AsController]
final class EliminarUsuariosController extends AbstractController
{
    /** `{ ids }` → lo que depende de esos usuarios. */
    #[Route('/api/usuarios/eliminacion/analizar', name: 'api_usuarios_eliminacion_analizar', methods: ['POST'])]
    public function analizar(Request $request, EliminacionUsuarios $eliminacion): JsonResponse
    {
        $this->denyAccessUnlessGranted(EntityVoter::DELETE, Usuario::class);

        return $this->json(['grupos' => $eliminacion->analizar($this->ids($request))]);
    }

    /** `{ ids, decisiones: { tabla: "desvincular"|"eliminar" } }` */
    #[Route('/api/usuarios/eliminacion', name: 'api_usuarios_eliminacion', methods: ['POST'])]
    public function eliminar(Request $request, EliminacionUsuarios $eliminacion, #[CurrentUser] Usuario $actual): JsonResponse
    {
        $this->denyAccessUnlessGranted(EntityVoter::DELETE, Usuario::class);

        $ids = $this->ids($request);
        if (in_array($actual->getId(), $ids, true)) {
            return $this->json(['error' => 'No puede eliminar su propio usuario.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $decisiones = $request->toArray()['decisiones'] ?? [];
        if (!is_array($decisiones)) {
            throw new BadRequestHttpException('Las decisiones deben ser un objeto.');
        }

        try {
            return $this->json($eliminacion->eliminar($ids, $decisiones));
        } catch (EliminacionInvalida $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        $ids = $request->toArray()['ids'] ?? null;
        if (!is_array($ids) || $ids === []) {
            throw new BadRequestHttpException('Indique los usuarios a eliminar.');
        }

        $enteros = array_values(array_unique(array_filter(
            array_map(static fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : 0, $ids),
            static fn (int $id): bool => $id > 0,
        )));
        if ($enteros === []) {
            throw new BadRequestHttpException('Los ids deben ser números positivos.');
        }

        return $enteros;
    }
}
