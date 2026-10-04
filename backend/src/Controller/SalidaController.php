<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Bus;
use App\Entity\Empresa;
use App\Entity\Trayecto;
use App\Entity\Usuario;
use App\Entity\Salida;
use App\Salida\ConsultaSalidas;
use App\Salida\DetalleSalida;
use App\Salida\Esquemas;
use App\Salida\FiltroSalidas;
use App\Salida\GestionSalidas;
use App\Salida\Manifiesto\ManifiestoPdf;
use App\Salida\Manifiesto\ManifiestoSalida;
use App\Salida\Programacion\Programacion;
use App\Salida\ProgramadorSalidas;
use App\Salida\SalidaRechazada;
use App\Salida\VistaSalida;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Gestión logística de salidas (ADR-024): listado, programador (con esquemas
 * guardados) y editar/anular/eliminar con propagación a las idénticas
 * futuras. Bajo `/api/gestion-salidas` para no chocar con el recurso
 * `Salida` de API Platform. Las respuestas de error son `{ error, codigo }`.
 */
#[AsController]
#[Route("/api/gestion-salidas", name: "api_gestion_salidas_")]
final class SalidaController extends AbstractController
{
    public const VER = "salida.ver";
    public const CREAR = "salida.crear";
    public const EDITAR = "salida.editar";
    public const ANULAR = "salida.cancelar";
    public const ELIMINAR = "salida.eliminar";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GestionSalidas $gestion,
        private readonly ProgramadorSalidas $programador,
        private readonly Esquemas $esquemas,
    ) {}

    /** Ver `FiltroSalidas::desdeQuery` para los parámetros. */
    #[Route("", name: "listar", methods: ["GET"])]
    public function listar(Request $request, ConsultaSalidas $consulta): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->json($consulta->buscar(FiltroSalidas::desdeQuery($request->query->all())));
    }

    /** Empresas, trayectos activos y buses (de la empresa del usuario) para filtros y formularios, y qué puede hacer el usuario. */
    #[Route("/opciones", name: "opciones", methods: ["GET"])]
    public function opciones(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);
        $trayectos = $this->em->createQueryBuilder()
            ->select("t", "o", "d")
            ->from(Trayecto::class, "t")
            ->join("t.origen", "o")
            ->join("t.destino", "d")
            ->where("t.activo = true")
            ->orderBy("o.nombre")
            ->addOrderBy("d.nombre")
            ->getQuery()
            ->getResult();
        $buses = $this->em->getRepository(Bus::class)->findBy([], ["codigo" => "ASC"]);

        return $this->json([
            "empresas" => array_map(
                static fn(Empresa $e) => ["id" => $e->getId(), "nombre" => $e->getNombreCorto()],
                $this->em->getRepository(Empresa::class)->findBy([], ["nombre" => "ASC"]),
            ),
            "trayectos" => array_map(VistaSalida::trayecto(...), $trayectos),
            "buses" => array_map(VistaSalida::bus(...), $buses),
            "puede" => [
                "crear" => $this->isGranted(self::CREAR),
                "editar" => $this->isGranted(self::EDITAR),
                "anular" => $this->isGranted(self::ANULAR),
                "eliminar" => $this->isGranted(self::ELIMINAR),
            ],
        ]);
    }

    /** Body de `Programacion::desdeArray`. Devuelve qué salidas se crearían y cuáles no (y por qué). */
    #[Route("/programacion/vista-previa", name: "vista_previa", methods: ["POST"])]
    public function vistaPrevia(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CREAR);

        return $this->responder(fn() => $this->programador->vistaPrevia(Programacion::desdeArray($request->toArray(), new \DateTimeImmutable())));
    }

    /** Como la vista previa, más `guardarComo?: string` (nombre del esquema a guardar). */
    #[Route("/programacion", name: "programar", methods: ["POST"])]
    public function programar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CREAR);
        $datos = $request->toArray();

        return $this->responder(fn() => $this->programador->programar(
            Programacion::desdeArray($datos, new \DateTimeImmutable()),
            isset($datos["guardarComo"]) ? (string) $datos["guardarComo"] : null,
            $this->em->find(Usuario::class, $usuario->getId()),
        ), 201);
    }

    #[Route("/esquemas", name: "esquemas", methods: ["GET"])]
    public function esquemas(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CREAR);

        return $this->json(["items" => $this->esquemas->listar()]);
    }

    /** `{ nombre, trayectoId, intervaloDias, momentos: [{ hora, busId }] }` */
    #[Route("/esquemas", name: "esquema_crear", methods: ["POST"])]
    public function crearEsquema(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CREAR);

        return $this->responder(fn() => $this->esquemas->guardar(null, $request->toArray(), $this->em->find(Usuario::class, $usuario->getId())), 201);
    }

    #[Route("/esquemas/{id<\d+>}", name: "esquema_guardar", methods: ["PUT"])]
    public function guardarEsquema(int $id, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CREAR);

        return $this->responder(fn() => $this->esquemas->guardar($id, $request->toArray(), $this->em->find(Usuario::class, $usuario->getId())));
    }

    #[Route("/esquemas/{id<\d+>}", name: "esquema_eliminar", methods: ["DELETE"])]
    public function eliminarEsquema(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CREAR);

        return $this->responder(function () use ($id) {
            $this->esquemas->eliminar($id);

            return ["id" => $id];
        });
    }

    /** Salida con paradas, croquis, estado de cada asiento y resumen de venta ("Ver"). */
    #[Route("/{id<\d+>}/detalle", name: "detalle", methods: ["GET"])]
    public function detalle(int $id, DetalleSalida $detalle): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->responder(fn() => $detalle->de($this->salida($id)));
    }

    /** PDF del manifiesto `interno` o `piloto` de la salida. */
    #[Route("/{id<\d+>}/manifiesto/{tipo<interno|piloto>}", name: "manifiesto", methods: ["GET"])]
    public function manifiesto(int $id, string $tipo, #[CurrentUser] Usuario $usuario, ManifiestoSalida $manifiestos, ManifiestoPdf $pdf): Response
    {
        $this->denyAccessUnlessGranted(self::VER);

        try {
            $manifiesto = $manifiestos->de($this->salida($id));
        } catch (SalidaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        return new Response($pdf->generar($tipo, $manifiesto, $usuario->getUsername()), 200, [
            "Content-Type" => "application/pdf",
            "Content-Disposition" => sprintf('inline; filename="%s"', ManifiestoPdf::nombreArchivo($tipo, $manifiesto)),
        ]);
    }

    /** Cuántas futuras idénticas tiene la salida y cuántas tienen asientos (para confirmar la propagación). */
    #[Route("/{id<\d+>}/propagacion", name: "propagacion", methods: ["GET"])]
    public function propagacion(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->responder(fn() => $this->gestion->propagacion($id));
    }

    /** `{ trayectoId?, busId?, fecha?: AAAA-MM-DDTHH:MM, propagar?: bool }` */
    #[Route("/{id<\d+>}", name: "editar", methods: ["PUT"])]
    public function editar(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);
        $datos = $request->toArray();

        return $this->responder(fn() => $this->gestion->editar($id, $datos, (bool) ($datos["propagar"] ?? false)));
    }

    /** `{ propagar?: bool }` */
    #[Route("/{id<\d+>}/anular", name: "anular", methods: ["POST"])]
    public function anular(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ANULAR);
        $propagar = (bool) ($request->getContent() !== "" ? ($request->toArray()["propagar"] ?? false) : false);

        return $this->responder(fn() => $this->gestion->anular($id, $propagar));
    }

    /** `?propagar=1` */
    #[Route("/{id<\d+>}", name: "eliminar", methods: ["DELETE"])]
    public function eliminar(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ELIMINAR);

        return $this->responder(fn() => $this->gestion->eliminar($id, $request->query->getBoolean("propagar")));
    }

    private function salida(int $id): Salida
    {
        return $this->em->find(Salida::class, $id)
            ?? throw new SalidaRechazada("La salida no existe.", "salida_inexistente", 404);
    }

    /** @param callable(): mixed $operacion */
    private function responder(callable $operacion, int $estado = 200): JsonResponse
    {
        try {
            return $this->json($operacion(), $estado);
        } catch (SalidaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }
}
