<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Administracion\AccesoRechazado;
use App\Acceso\Administracion\AdministracionAccesos;
use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Catalogo\Catalogo;
use App\Acceso\Catalogo\DefinicionCapacidad;
use App\Acceso\Limite;
use App\Entity\Empresa;
use App\Entity\Estacion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administración de accesos (ADR-039): catálogo de capacidades, roles,
 * asignaciones por usuario, activación y "¿quién puede…?". Ver con
 * `iam.ver`, otorgar con `iam.asignar` (sin escalar privilegios:
 * `App\Acceso\Delegacion`), editar roles con `iam.roles`. Errores como
 * `{ error, codigo }`.
 */
#[AsController]
#[Route('/api/acceso', name: 'api_acceso_')]
final class AccesoController extends AbstractController
{
    public function __construct(
        private readonly AdministracionAccesos $accesos,
        private readonly Catalogo $catalogo,
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
    ) {}

    /** Las capacidades por área, los límites y las empresas y estaciones para los alcances. */
    #[Route('/catalogo', name: 'catalogo', methods: ['GET'])]
    public function catalogo(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::IAM_VER->value);

        return $this->json([
            'capacidades' => array_values(array_map(static fn(DefinicionCapacidad $d) => $d->toArray(), $this->catalogo->definiciones())),
            'limites' => array_map(static fn(Limite $l) => ['codigo' => $l->value, 'nombre' => $l->nombre(), 'capacidad' => $l->capacidad()->value], Limite::cases()),
            'empresas' => array_map(
                static fn(Empresa $e) => ['id' => (int) $e->getId(), 'nombre' => $e->getNombreCorto() ?? $e->getNombre()],
                $this->em->getRepository(Empresa::class)->findBy([], ['nombre' => 'ASC']),
            ),
            'estaciones' => array_map(
                static fn(Estacion $e) => ['id' => (int) $e->getId(), 'nombre' => $e->getNombre(), 'departamento' => $e->getDepartamento(), 'activo' => $e->isActivo()],
                $this->em->getRepository(Estacion::class)->findBy([], ['nombre' => 'ASC']),
            ),
            'puede' => [
                'roles' => $this->autorizacion->puede(Capacidad::IAM_ROLES),
                'asignar' => $this->autorizacion->puede(Capacidad::IAM_ASIGNAR),
            ],
        ]);
    }

    #[Route('/roles', name: 'roles', methods: ['GET'])]
    public function roles(): JsonResponse
    {
        return $this->responder(fn() => $this->accesos->roles());
    }

    /** `{ nombre, descripcion?, capacidades: [codigo], limites?: { codigo: n } }` */
    #[Route('/roles', name: 'rol_crear', methods: ['POST'])]
    public function crearRol(Request $request): JsonResponse
    {
        return $this->responder(fn() => $this->accesos->crearRol($request->toArray()), Response::HTTP_CREATED);
    }

    #[Route('/roles/{id<\d+>}', name: 'rol_editar', methods: ['PUT'])]
    public function editarRol(int $id, Request $request): JsonResponse
    {
        return $this->responder(fn() => $this->accesos->editarRol($id, $request->toArray()));
    }

    #[Route('/roles/{id<\d+>}', name: 'rol_eliminar', methods: ['DELETE'])]
    public function eliminarRol(int $id): JsonResponse
    {
        return $this->responder(function () use ($id) {
            $this->accesos->eliminarRol($id);

            return ['id' => $id];
        });
    }

    /** Asignaciones, permisos efectivos y qué puede cambiar quien mira. */
    #[Route('/usuarios/{id<-?\d+>}', name: 'usuario', methods: ['GET'])]
    public function usuario(int $id): JsonResponse
    {
        return $this->responder(fn() => $this->accesos->accesos($id));
    }

    /** `{ rol, empresas: [id]|null, estaciones: [id]|null, desde?, hasta?, motivo? }` (null = todas). */
    #[Route('/usuarios/{id<-?\d+>}/asignaciones', name: 'otorgar', methods: ['POST'])]
    public function otorgar(int $id, Request $request): JsonResponse
    {
        return $this->responder(fn() => $this->accesos->otorgar($id, $request->toArray()), Response::HTTP_CREATED);
    }

    #[Route('/asignaciones/{id<\d+>}', name: 'modificar', methods: ['PUT'])]
    public function modificar(int $id, Request $request): JsonResponse
    {
        return $this->responder(fn() => $this->accesos->modificar($id, $request->toArray()));
    }

    #[Route('/asignaciones/{id<\d+>}', name: 'revocar', methods: ['DELETE'])]
    public function revocar(int $id): JsonResponse
    {
        return $this->responder(fn() => $this->accesos->revocar($id));
    }

    /** `{ activo: bool }` */
    #[Route('/usuarios/{id<-?\d+>}/activo', name: 'activar', methods: ['POST'])]
    public function activar(int $id, Request $request): JsonResponse
    {
        return $this->responder(function () use ($id, $request) {
            $this->accesos->activar($id, (bool) ($request->toArray()['activo'] ?? false));

            return $this->accesos->accesos($id);
        });
    }

    /** `?capacidad=&empresa=&estacion=`: quién la tiene ahí (o en algún lado), y por qué asignación. */
    #[Route('/quien-puede', name: 'quien_puede', methods: ['GET'])]
    public function quienPuede(Request $request): JsonResponse
    {
        return $this->responder(fn() => $this->accesos->quienPuede(
            (string) $request->query->get('capacidad', ''),
            $request->query->getInt('empresa') ?: null,
            $request->query->getInt('estacion') ?: null,
        ));
    }

    /** Usuarios activos sin nada que hacer (para revisarlos tras la migración). */
    #[Route('/sin-alcance', name: 'sin_alcance', methods: ['GET'])]
    public function sinAlcance(): JsonResponse
    {
        return $this->responder(fn() => $this->accesos->sinAlcance());
    }

    /** @param callable(): mixed $operacion */
    private function responder(callable $operacion, int $estado = Response::HTTP_OK): JsonResponse
    {
        try {
            return $this->json($operacion(), $estado);
        } catch (AccesoRechazado $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        } catch (JsonException) {
            return $this->json(['error' => 'El cuerpo debe ser JSON.', 'codigo' => 'json_invalido'], Response::HTTP_BAD_REQUEST);
        }
    }
}
