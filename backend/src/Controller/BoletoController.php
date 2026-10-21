<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Estacion;
use App\Entity\Usuario;
use App\Salida\Abordaje\Abordaje;
use App\Salida\SalidaRechazada;
use App\Venta\Acceso\AccesoVentas;
use App\Venta\Boleto\Listado\FiltroBoletos;
use App\Venta\Boleto\Listado\ListadoBoletos;
use App\Venta\Clientes;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Mensaje\EnviarBoletoPorCorreo;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Listado de boletos (el "Boletos" del legado, permiso `boleto.ver`):
 * filtros, una fila por asiento vendido, "Consultar", cancelar (no se
 * presentó, `boleto.cancelar`), revertir un cancelado y reenviar por correo. Anular y reasignar
 * siguen en `/api/venta/boletos/*`, chequear en `/api/abordaje/*`.
 * Errores como `{ error, codigo }`.
 */
#[AsController]
#[Route("/api/boletos", name: "api_boletos_")]
final class BoletoController extends AbstractController
{
    public const VER = "boleto.ver";
    public const CANCELAR = "boleto.cancelar";

    public function __construct(
        private readonly ListadoBoletos $listado,
        private readonly AccesoVentas $acceso,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $reloj,
    ) {}

    /** Valores iniciales de los filtros y qué puede hacer el usuario con un boleto. */
    #[Route("/opciones", name: "opciones", methods: ["GET"])]
    public function opciones(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);
        $hoy = $this->reloj->now()->setTime(0, 0);

        return $this->json([
            "desde" => $hoy->modify("-30 days")->format("Y-m-d"),
            "hasta" => $hoy->modify("+30 days")->format("Y-m-d"),
            "maxDias" => FiltroBoletos::MAX_DIAS,
            "porPagina" => FiltroBoletos::POR_PAGINA,
            "estaciones" => array_map(
                static fn(Estacion $e) => ["id" => $e->getId(), "nombre" => $e->getNombre(), "departamento" => $e->getDepartamento()],
                $this->em->getRepository(Estacion::class)->findBy(["activo" => true], ["nombre" => "ASC"]),
            ),
            "puede" => [
                "cancelar" => $this->isGranted(self::CANCELAR),
                "anular" => $this->isGranted(VentaController::ANULAR),
                "reasignar" => $this->isGranted(VentaController::REASIGNAR),
                "chequear" => $this->isGranted(AbordajeController::CHEQUEAR),
            ],
        ]);
    }

    /**
     * `?desde&hasta` (AAAA-MM-DD; por defecto ±30 días), `id`, `reasignado`,
     * `salida`, `documento`, `estado` (separados por coma), `asiento`,
     * `clase`, `serie`, `factura`, `origen`, `destino`, `referencia`,
     * `autorizacion`, `clienteDocumento`, `clienteBoleto`, `pagina`,
     * `porPagina`, `orden`, `dir`.
     */
    #[Route("", name: "listar", methods: ["GET"])]
    public function listar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->responder(fn() => $this->listado->buscar(FiltroBoletos::desdeQuery($request->query->all(), $this->reloj->now()), $usuario));
    }

    /** `?q=` (NIT, nombre o documento): clientes para los filtros. */
    #[Route("/clientes", name: "clientes", methods: ["GET"])]
    public function clientes(Request $request, Clientes $clientes): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->json($clientes->buscar((string) $request->query->get("q", "")));
    }

    #[Route("/{id<\d+>}", name: "detalle", methods: ["GET"])]
    public function detalle(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->responder(fn() => $this->listado->detalle($id, $usuario));
    }

    /** `{ motivo? }`: el pasajero no viaja (queda cancelado; la venta y su factura siguen). */
    #[Route("/{id<\d+>}/cancelar", name: "cancelar", methods: ["POST"])]
    public function cancelar(int $id, Request $request, #[CurrentUser] Usuario $usuario, Abordaje $abordaje): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CANCELAR);
        $datos = $request->getContent() === "" ? [] : $request->toArray();

        return $this->responder(fn() => $abordaje->cancelar(
            $id,
            (string) ($datos["motivo"] ?? ""),
            fn(BoletoVenta $v) => $this->acceso->puedeOperar($v, $usuario),
        ));
    }

    /** El pasajero sí viaja: un cancelado vuelve a emitido si su asiento sigue libre (mismo permiso que cancelar, como el legado). */
    #[Route("/{id<\d+>}/revertir", name: "revertir", methods: ["POST"])]
    public function revertir(int $id, #[CurrentUser] Usuario $usuario, Abordaje $abordaje): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CANCELAR);

        return $this->responder(fn() => $abordaje->revertir($id, fn(BoletoVenta $v) => $this->acceso->puedeOperar($v, $usuario)));
    }

    /** `{ correo }`: manda el boleto (PDF con los datos de la factura) a esa dirección. 202. */
    #[Route("/{id<\d+>}/correo", name: "correo", methods: ["POST"])]
    public function correo(int $id, Request $request, #[CurrentUser] Usuario $usuario, MessageBusInterface $bus): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->responder(function () use ($id, $request, $usuario, $bus) {
            $boleto = $this->em->find(BoletoAsiento::class, $id) ?? throw new VentaRechazada("El boleto no existe.", "no_encontrado", 404);
            $venta = $boleto->getBoletoVenta();
            if (!$this->acceso->puedeOperar($venta, $usuario)) {
                throw new VentaRechazada("No puede operar sobre boletos de esta venta.", "permiso", 403);
            }
            $correo = trim((string) ($request->toArray()["correo"] ?? ""));
            if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
                throw new VentaRechazada("Escriba un correo válido.", "correo_invalido", 400);
            }
            $bus->dispatch(new EnviarBoletoPorCorreo((int) $venta->getId(), [], $correo));

            return ["correo" => $correo];
        }, Response::HTTP_ACCEPTED);
    }

    /** @param callable(): mixed $operacion */
    private function responder(callable $operacion, int $estado = Response::HTTP_OK): JsonResponse
    {
        try {
            return $this->json($operacion(), $estado);
        } catch (VentaRechazada|SalidaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }
}
