<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Venta\Agencia\DepositosAgencia;
use App\Venta\Agencia\FiltroDepositos;
use App\Venta\Agencia\ListadoDepositos;
use App\Venta\Excepcion\VentaRechazada;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Gestión de agencias del legado (ADR-034): registrar depósito, buscar
 * depósitos (acreditar / rechazar) y consultar saldos. Registrar y ver con
 * `agencia.depositar`; acreditar y rechazar con `agencia.acreditar`, que
 * también deja ver y registrar. Un usuario de agencia solo ve la suya y
 * nunca acredita. Errores como `{ error, codigo }`.
 */
#[AsController]
#[Route("/api/depositos-agencia", name: "api_depositos_agencia_")]
final class DepositoAgenciaController extends AbstractController
{
    public const DEPOSITAR = "agencia.depositar";

    public function __construct(
        private readonly DepositosAgencia $depositos,
        private readonly ListadoDepositos $listado,
        private readonly ClockInterface $reloj,
    ) {}

    /** Agencias elegibles, valores iniciales de los filtros y qué puede hacer el usuario. */
    #[Route("/opciones", name: "opciones", methods: ["GET"])]
    public function opciones(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->exigirVer();
        $hoy = $this->reloj->now()->setTime(0, 0);
        $propia = $usuario->getAgencia()?->getId();

        return $this->json([
            "hoy" => $hoy->format("Y-m-d"),
            "desde" => $hoy->modify("-1 month")->format("Y-m-d"),
            "hasta" => $hoy->modify("+1 month")->format("Y-m-d"),
            "porPagina" => FiltroDepositos::POR_PAGINA,
            "agencias" => $this->listado->agencias($propia),
            "agenciaPropia" => $propia,
            "puede" => [
                "acreditar" => $propia === null && $this->isGranted(AgenciaController::ACREDITAR),
                "elegirBono" => $propia === null,
            ],
        ]);
    }

    /** `?desde&hasta` (AAAA-MM-DD, por defecto ±1 mes), `id`, `agencia`, `boleta`, `estado` (separados por coma), `pagina`, `porPagina`. */
    #[Route("", name: "listar", methods: ["GET"])]
    public function listar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->exigirVer();

        return $this->responder(fn() => $this->listado->buscar(
            FiltroDepositos::desdeQuery($request->query->all(), $this->reloj->now()),
            $usuario->getAgencia()?->getId(),
        ));
    }

    /** `{ agencia, fecha: AAAA-MM-DD, importe: centavos, numeroBoleta, aplicaBono?, observacion? }` */
    #[Route("", name: "registrar", methods: ["POST"])]
    public function registrar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->exigirVer();
        $datos = $request->toArray();

        return $this->responder(function () use ($datos, $usuario) {
            $fecha = \DateTimeImmutable::createFromFormat("!Y-m-d", (string) ($datos["fecha"] ?? ""));
            if ($fecha === false || $fecha->format("Y-m-d") !== ($datos["fecha"] ?? null)) {
                throw new VentaRechazada("Indique la fecha del depósito.", "fecha_invalida", 400);
            }
            $agencia = $usuario->getAgencia()?->getId() ?? (int) ($datos["agencia"] ?? 0);
            if ($agencia <= 0) {
                throw new VentaRechazada("Debe seleccionar la agencia.", "agencia_requerida", 400);
            }

            return ListadoDepositos::fila($this->depositos->registrar(
                $agencia,
                $fecha,
                (int) ($datos["importe"] ?? 0),
                (string) ($datos["numeroBoleta"] ?? ""),
                (bool) ($datos["aplicaBono"] ?? true),
                isset($datos["observacion"]) ? (string) $datos["observacion"] : null,
                $usuario,
            ));
        }, Response::HTTP_CREATED);
    }

    #[Route("/{id<\d+>}/acreditar", name: "acreditar", methods: ["POST"])]
    public function acreditar(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->exigirResolver($usuario);

        return $this->responder(fn() => ListadoDepositos::fila($this->depositos->acreditar($id, $usuario)));
    }

    /** `{ motivo }` */
    #[Route("/{id<\d+>}/rechazar", name: "rechazar", methods: ["POST"])]
    public function rechazar(int $id, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->exigirResolver($usuario);
        $motivo = (string) ($request->toArray()["motivo"] ?? "");

        return $this->responder(fn() => ListadoDepositos::fila($this->depositos->rechazar($id, $motivo, $usuario)));
    }

    /** Saldo, depositado, bonificado y pendiente por agencia (la suya, si es de agencia). */
    #[Route("/saldos", name: "saldos", methods: ["GET"])]
    public function saldos(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->exigirVer();

        return $this->json($this->listado->saldos($usuario->getAgencia()?->getId()));
    }

    private function exigirVer(): void
    {
        if (!$this->isGranted(self::DEPOSITAR) && !$this->isGranted(AgenciaController::ACREDITAR)) {
            throw $this->createAccessDeniedException("Requiere el permiso agencia.depositar.");
        }
    }

    private function exigirResolver(Usuario $usuario): void
    {
        $this->denyAccessUnlessGranted(AgenciaController::ACREDITAR);
        if ($usuario->getAgencia() !== null) {
            throw $this->createAccessDeniedException("Una agencia no acredita ni rechaza sus propios depósitos.");
        }
    }

    private function responder(callable $operacion, int $estado = Response::HTTP_OK): JsonResponse
    {
        try {
            return $this->json($operacion(), $estado);
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        } catch (\DomainException $e) {
            return $this->json(["error" => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
