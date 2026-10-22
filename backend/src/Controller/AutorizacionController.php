<?php

declare(strict_types=1);

namespace App\Controller;

use App\Autorizacion\Autorizable;
use App\Autorizacion\AutorizacionRechazada;
use App\Autorizacion\Autorizaciones;
use App\Autorizacion\ConsultaAutorizaciones;
use App\Entity\Enum\EstadoAutorizacion;
use App\Entity\Usuario;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Autorizaciones, pases de un solo uso (ADR-040): pedirlas (cualquier
 * usuario con sesión, para lo que lo bloquea), resolverlas (quien tiene
 * `autorizacion.aprobar` donde cae la operación) y retirarlas (quien la
 * pidió). Se usan solas al repetir la operación. Errores: `{ error, codigo }`.
 */
#[AsController]
#[Route("/api/autorizaciones", name: "api_autorizaciones_")]
final class AutorizacionController extends AbstractController
{
    public const APROBAR = "autorizacion.aprobar";

    public function __construct(
        private readonly Autorizaciones $autorizaciones,
        private readonly ConsultaAutorizaciones $consulta,
        private readonly ClockInterface $reloj,
    ) {}

    /** `?vista=por-resolver|mias|todas&estado=&pagina=` */
    #[Route("", name: "listar", methods: ["GET"])]
    public function listar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $vista = (string) $request->query->get("vista", "mias");
        if (!in_array($vista, ConsultaAutorizaciones::VISTAS, true)) {
            $vista = "mias";
        }

        return $this->json($this->consulta->listar(
            $usuario,
            $vista,
            EstadoAutorizacion::tryFrom((string) $request->query->get("estado", "")),
            $request->query->getInt("pagina", 1),
        ));
    }

    /** Para la cabecera: `{ porResolver, aprobadas, pendientes, aprueba }`. */
    #[Route("/resumen", name: "resumen", methods: ["GET"])]
    public function resumen(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->json($this->consulta->resumen($usuario) + ["aprueba" => $this->isGranted(self::APROBAR)]);
    }

    #[Route("/{id<\d+>}", name: "ver", methods: ["GET"])]
    public function ver(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->responder(fn() => $this->consulta->una($id, $usuario));
    }

    /** `{ capacidad, dispensa, sujeto: boletos|salida, ids, cantidad?, motivo }` (el `autorizable` del bloqueo más el motivo). */
    #[Route("", name: "solicitar", methods: ["POST"])]
    public function solicitar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->responder(function () use ($request, $usuario) {
            $datos = $request->toArray();
            $s = $this->autorizaciones->solicitar($usuario, Autorizable::desdeArray($datos), (string) ($datos["motivo"] ?? ""));

            return $this->consulta->fila($s, $usuario, $this->reloj->now());
        }, Response::HTTP_CREATED);
    }

    /** `{ nota? }` */
    #[Route("/{id<\d+>}/aprobar", name: "aprobar", methods: ["POST"])]
    public function aprobar(int $id, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->responder(fn() => $this->consulta->fila(
            $this->autorizaciones->aprobar($id, $usuario, self::texto($request, "nota")),
            $usuario,
            $this->reloj->now(),
        ));
    }

    /** `{ motivo }` */
    #[Route("/{id<\d+>}/rechazar", name: "rechazar", methods: ["POST"])]
    public function rechazar(int $id, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->responder(fn() => $this->consulta->fila(
            $this->autorizaciones->rechazar($id, $usuario, (string) self::texto($request, "motivo")),
            $usuario,
            $this->reloj->now(),
        ));
    }

    #[Route("/{id<\d+>}/retirar", name: "retirar", methods: ["POST"])]
    public function retirar(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->responder(fn() => $this->consulta->fila($this->autorizaciones->retirar($id, $usuario), $usuario, $this->reloj->now()));
    }

    private static function texto(Request $request, string $campo): ?string
    {
        $valor = $request->getContent() === "" ? null : ($request->toArray()[$campo] ?? null);

        return is_string($valor) ? $valor : null;
    }

    /** @param callable(): mixed $operacion */
    private function responder(callable $operacion, int $estado = Response::HTTP_OK): JsonResponse
    {
        try {
            return $this->json($operacion(), $estado);
        } catch (AutorizacionRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }
}
