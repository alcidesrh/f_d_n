<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Agencia;
use App\Entity\AgenciaMovimiento;
use App\Entity\Usuario;
use App\Venta\Agencia\DepositosAgencia;
use App\Venta\Agencia\SaldoAgencia;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Transaccion;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Saldo de las agencias (ADR-021). Los datos de la agencia van por el CRUD
 * genérico; el saldo solo cambia por aquí o al acreditar un depósito
 * (`DepositoAgenciaController`, ADR-034), con permiso `agencia.acreditar`.
 */
#[AsController]
#[Route("/api/agencias/{id<\d+>}", name: "api_agencia_")]
final class AgenciaController extends AbstractController
{
    public const ACREDITAR = "agencia.acreditar";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SaldoAgencia $saldo,
        private readonly Transaccion $transaccion,
        private readonly DepositosAgencia $depositos,
        private readonly ClockInterface $reloj,
    ) {}

    /** Saldo y últimos movimientos. Una agencia solo ve el suyo. */
    #[Route("/saldo", name: "saldo", methods: ["GET"])]
    public function saldo(Agencia $agencia, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $propia = $usuario->getAgencia()?->getId();
        if ($propia !== null ? $propia !== $agencia->getId() : !$this->puedeVerSaldos()) {
            $this->denyAccessUnlessGranted("read", Agencia::class);
        }

        return $this->json($this->estado($agencia));
    }

    /**
     * Depósito ya verificado: queda registrado y acreditado en un paso
     * (ADR-034). `{ importe: centavos, referencia (número de boleta),
     * fecha?: AAAA-MM-DD, observacion?, bonificacion?: bool }`
     */
    #[Route("/depositos", name: "deposito", methods: ["POST"])]
    public function depositar(Agencia $agencia, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ACREDITAR);
        $datos = $request->toArray();

        return $this->responder(function () use ($agencia, $datos, $usuario) {
            $fecha = \DateTimeImmutable::createFromFormat("!Y-m-d", (string) ($datos["fecha"] ?? "")) ?: $this->reloj->now();
            $this->depositos->registrarAcreditado(
                (int) $agencia->getId(),
                $fecha,
                (int) ($datos["importe"] ?? 0),
                (string) ($datos["referencia"] ?? ""),
                (bool) ($datos["bonificacion"] ?? true),
                self::texto($datos["observacion"] ?? null, 200),
                $usuario,
            );

            return $this->estado($this->em->find(Agencia::class, $agencia->getId()));
        });
    }

    /** `{ importe: centavos con signo, observacion }` */
    #[Route("/ajustes", name: "ajuste", methods: ["POST"])]
    public function ajustar(Agencia $agencia, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ACREDITAR);
        $datos = $request->toArray();

        return $this->responder(function () use ($agencia, $datos, $usuario) {
            $observacion = self::texto($datos["observacion"] ?? null, 255)
                ?? throw new VentaRechazada("Indique el motivo del ajuste.");
            $this->transaccion->ejecutar(fn() => $this->saldo->ajustar(
                $this->em->find(Agencia::class, $agencia->getId()),
                (int) ($datos["importe"] ?? 0),
                $this->em->find(Usuario::class, $usuario->getId()),
                $observacion,
            ));

            return $this->estado($this->em->find(Agencia::class, $agencia->getId()));
        });
    }

    /** @return array<string, mixed> */
    private function estado(Agencia $agencia): array
    {
        $moneda = new Currency($agencia->getMoneda());
        $movimientos = $this->em->getRepository(AgenciaMovimiento::class)->findBy(["agencia" => $agencia], ["id" => "DESC"], 50);

        return [
            "id" => $agencia->getId(),
            "nombre" => $agencia->getNombre(),
            "saldo" => DatosBoleto::importe(new Money($agencia->getSaldo(), $moneda)),
            "porcentajeBonificacion" => $agencia->getPorcentajeBonificacion(),
            "puedeAcreditar" => $this->isGranted(self::ACREDITAR),
            "movimientos" => array_map(static fn(AgenciaMovimiento $m) => [
                "id" => $m->getId(),
                "fecha" => $m->getFecha()->format(DATE_ATOM),
                "tipo" => $m->getTipo()->value,
                "monto" => DatosBoleto::importe(new Money($m->getMonto(), $moneda)),
                "saldo" => DatosBoleto::importe(new Money($m->getSaldoResultante(), $moneda)),
                "referencia" => $m->getReferencia(),
                "observacion" => $m->getObservacion(),
                "usuario" => $m->getUsuario()?->getUsername(),
                "venta" => $m->getBoletoVenta()?->getId(),
            ], $movimientos),
        ];
    }

    private function puedeVerSaldos(): bool
    {
        return $this->isGranted("ROLE_ADMIN") || $this->isGranted(self::ACREDITAR) || $this->isGranted(DepositoAgenciaController::DEPOSITAR);
    }

    private function responder(callable $operacion): JsonResponse
    {
        try {
            return $this->json($operacion());
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        } catch (\DomainException $e) {
            return $this->json(["error" => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private static function texto(mixed $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v !== "" ? mb_substr($v, 0, $max) : null;
    }
}
