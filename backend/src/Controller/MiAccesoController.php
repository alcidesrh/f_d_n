<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Autorizacion;
use App\Acceso\Catalogo\CatalogoRecursos;
use App\Acceso\EstacionTrabajo;
use App\Bitacora\TipoRegistro;
use App\Acceso\Administracion\BitacoraAcceso;
use App\Entity\Enum\OperacionUsuario;
use App\Entity\Usuario;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Lo que puede hacer el usuario con sesión (ADR-039), para que el frontend
 * muestre o esconda pantallas y botones —quien decide es siempre el
 * backend—, y su estación de trabajo.
 */
#[AsController]
#[Route('/api/me', name: 'api_me_')]
final class MiAccesoController extends AbstractController
{
    public function __construct(
        private readonly Autorizacion $autorizacion,
        private readonly CatalogoRecursos $recursos,
        private readonly EstacionTrabajo $estacionTrabajo,
    ) {}

    /**
     * `capacidades` (código → ámbitos), `recursos` (entidad de la API
     * genérica → ver/editar/eliminar), `sistema`, la estación de trabajo, las
     * que puede elegir y una `huella` que cambia con cualquier cambio.
     */
    #[Route('/acceso', name: 'acceso', methods: ['GET'])]
    public function acceso(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        $permisos = $this->autorizacion->de($usuario);
        $recursos = [];
        foreach ($this->recursos->recursos() as $r) {
            if ($r->oculto) {
                continue;
            }
            $recursos[$r->nombreCorto] = [
                'ver' => $r->lecturaLibre || ($r->ver !== null && $permisos->tiene($r->ver)),
                'editar' => $r->editar !== null && $permisos->tiene($r->editar),
                'eliminar' => $r->eliminar !== null && $permisos->tiene($r->eliminar),
            ];
        }
        $this->estacionTrabajo->asegurar($usuario);
        $estacion = $this->estacionTrabajo->actual($usuario);
        $agencia = $usuario->getAgencia();

        return $this->json([
            'usuario' => ['id' => (int) $usuario->getId(), 'username' => $usuario->getUsername()],
            'sistema' => $permisos->sistema(),
            'capacidades' => (object) array_map(static fn($alcance) => $alcance->toArray(), $permisos->capacidades()),
            'recursos' => (object) $recursos,
            'agencia' => $agencia === null ? null : ['id' => (int) $agencia->getId(), 'nombre' => $agencia->getNombre()],
            'estacionTrabajo' => $estacion === null ? null : ['id' => (int) $estacion->getId(), 'nombre' => $estacion->getNombre(), 'alias' => $estacion->getAlias(), 'departamento' => $estacion->getDepartamento()],
            'estaciones' => $this->estacionTrabajo->opciones($usuario),
            'huella' => $permisos->huella() . ':' . ($estacion?->getId() ?? '-'),
        ]);
    }

    /** `{ estacion }`: cambia la estación de trabajo por una de sus asignaciones. */
    #[Route('/estacion-trabajo', name: 'estacion_trabajo', methods: ['PUT'])]
    public function estacionTrabajo(Request $request, #[CurrentUser] Usuario $usuario, BitacoraAcceso $bitacora): JsonResponse
    {
        $id = $request->toArray()['estacion'] ?? null;
        if (!is_numeric($id)) {
            return $this->json(['error' => 'Elija la estación.', 'codigo' => 'estacion_requerida'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $antes = $usuario->getEstacion()?->getNombre();
        try {
            $estacion = $this->estacionTrabajo->elegir($usuario, (int) $id);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage(), 'codigo' => 'alcance'], Response::HTTP_FORBIDDEN);
        }
        if ($antes !== $estacion->getNombre()) {
            $bitacora->anotar(TipoRegistro::USUARIO, (int) $usuario->getId(), OperacionUsuario::ESTACION_TRABAJO, $usuario, ['antes' => $antes, 'ahora' => $estacion->getNombre()]);
        }

        return $this->acceso($usuario);
    }
}
