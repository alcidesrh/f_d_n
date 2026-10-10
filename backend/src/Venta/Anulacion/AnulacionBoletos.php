<?php

declare(strict_types=1);

namespace App\Venta\Anulacion;

use App\Bitacora\RegistradorBitacora;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Salida;
use App\Entity\Usuario;
use App\Venta\Agencia\SaldoAgencia;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\Facturador;
use App\Venta\PublicadorOcupacion;
use App\Venta\ReglasBoletos;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Anulación de boletos antes de la hora de salida.
 *
 * Por cada venta afectada: primero se anula su factura en el certificador
 * (llamada externa, fuera de transacción; si falla no cambia nada), y luego,
 * en una transacción con las salidas bloqueadas, los boletos pasan a
 * `anulado` (el asiento queda libre y la bitácora deja constancia) y, si es
 * una agencia, se le devuelve el precio. Una factura cubre toda la venta:
 * no se anula con boletos vivos de la venta fuera de la selección
 * (`ReglasBoletos::exigirVentaCompleta`).
 *
 * Si el certificador anuló la factura pero la transacción falla, la factura
 * queda marcada anulada: reintentar no la vuelve a anular, solo termina de
 * anular los boletos.
 */
final class AnulacionBoletos
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly Facturador $facturador,
        private readonly SaldoAgencia $saldo,
        private readonly PublicadorOcupacion $publicador,
        private readonly RegistradorBitacora $bitacora,
        private readonly ClockInterface $reloj,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param list<int>                        $ids        boletos a anular
     * @param callable(BoletoVenta): bool      $puedeOperar si el usuario puede operar sobre esa venta
     *
     * @return array{anulados: list<int>, fallidos: list<array<string, mixed>>} por venta: lo que se anuló y lo que no
     *
     * @throws VentaRechazada si algo de lo pedido no se puede anular (no se toca nada)
     */
    public function anular(array $ids, Usuario $usuario, string $motivo, callable $puedeOperar): array
    {
        $motivo = trim($motivo);
        if ($motivo === "") {
            throw new VentaRechazada("Indique el motivo de la anulación.", "falta_motivo");
        }
        $ids = array_values(array_unique(array_map("intval", $ids)));
        if ($ids === []) {
            throw new VentaRechazada("Elija al menos un boleto.");
        }
        if (count($ids) > 60) {
            throw new VentaRechazada("Demasiados boletos en una sola anulación.");
        }

        $porVenta = $this->validar($ids, $puedeOperar);

        $anulados = [];
        $fallidos = [];
        foreach ($porVenta as $ventaId => $boletoIds) {
            try {
                $this->anularVenta($ventaId, $boletoIds, (int) $usuario->getId(), $motivo);
                array_push($anulados, ...$boletoIds);
            } catch (VentaRechazada $e) {
                $fallidos[] = ["venta" => $ventaId, "boletos" => $boletoIds, ...$e->toArray()];
            } catch (CertificacionFallida $e) {
                $fallidos[] = [
                    "venta" => $ventaId,
                    "boletos" => $boletoIds,
                    "error" => $e->getMessage(),
                    "codigo" => "facturacion",
                    "recuperable" => $e->recuperable,
                ];
            }
        }

        return ["anulados" => $anulados, "fallidos" => $fallidos];
    }

    /**
     * Todo lo que se puede comprobar sin efectos.
     *
     * @param list<int>                   $ids
     * @param callable(BoletoVenta): bool $puedeOperar
     *
     * @return array<int, list<int>> ids de boleto por id de venta
     */
    private function validar(array $ids, callable $puedeOperar): array
    {
        /** @var list<BoletoAsiento> $boletos */
        $boletos = $this->em->getRepository(BoletoAsiento::class)->findBy(["id" => $ids]);
        if (count($boletos) !== count($ids)) {
            throw new VentaRechazada("Algún boleto no existe.", "no_encontrado", 404);
        }

        $ahora = $this->reloj->now();
        /** @var array<int, list<BoletoAsiento>> $grupos */
        $grupos = [];
        foreach ($boletos as $b) {
            $venta = $b->getBoletoVenta();
            if (!$puedeOperar($venta)) {
                throw new VentaRechazada("No puede anular boletos de esta venta.", "permiso", 403);
            }
            if ($venta->getEstado() !== EstadoBoletoVenta::CONFIRMADA) {
                throw new VentaRechazada("La venta todavía se está procesando.", "venta_en_proceso", 409);
            }
            ReglasBoletos::exigirEmitido($b);
            ReglasBoletos::exigirAntesDeSalir($b->getSalida(), $ahora, "anular el boleto");
            $grupos[(int) $venta->getId()][] = $b;
        }

        $porVenta = [];
        foreach ($grupos as $ventaId => $seleccion) {
            ReglasBoletos::exigirVentaCompleta($seleccion[0]->getBoletoVenta(), $seleccion);
            $porVenta[$ventaId] = array_map(static fn(BoletoAsiento $b) => (int) $b->getId(), $seleccion);
        }

        return $porVenta;
    }

    /**
     * @param list<int> $boletoIds
     *
     * @throws CertificacionFallida
     */
    private function anularVenta(int $ventaId, array $boletoIds, int $usuarioId, string $motivo): void
    {
        $venta = $this->em->find(BoletoVenta::class, $ventaId) ?? throw new VentaRechazada("La venta no existe.", "no_encontrado", 404);
        if ($venta->getFactura() !== null) {
            // Se confirma primero en el certificador; si falla, no cambia nada.
            $this->facturador->anular($venta, $motivo);
            $this->em->flush();
        }

        try {
            $salidas = $this->transaccion->ejecutar(fn() => $this->aplicar($ventaId, $boletoIds, $usuarioId, $motivo));
        } catch (VentaRechazada $e) {
            $this->logger->error("La factura de la venta {venta} se anuló pero sus boletos no: {error}", ["venta" => $ventaId, "error" => $e->getMessage()]);
            throw $e;
        }

        foreach ($salidas as $salidaId) {
            $this->publicador->cambio($salidaId);
        }
    }

    /**
     * Dentro de la transacción: bloquea las salidas, vuelve a comprobar y anula.
     *
     * @param list<int> $boletoIds
     *
     * @return list<int> salidas afectadas
     */
    private function aplicar(int $ventaId, array $boletoIds, int $usuarioId, string $motivo): array
    {
        $venta = $this->em->find(BoletoVenta::class, $ventaId);
        $usuario = $this->em->find(Usuario::class, $usuarioId);
        /** @var list<BoletoAsiento> $boletos */
        $boletos = array_values(array_map(fn(int $id) => $this->em->find(BoletoAsiento::class, $id), $boletoIds));

        $salidaIds = array_unique(array_map(static fn(BoletoAsiento $b) => (int) $b->getSalida()->getId(), $boletos));
        sort($salidaIds);
        foreach ($salidaIds as $id) {
            $this->em->find(Salida::class, $id, LockMode::PESSIMISTIC_WRITE);
        }

        $ahora = $this->reloj->now();
        $devuelto = null;
        foreach ($boletos as $b) {
            $this->em->refresh($b);
            ReglasBoletos::exigirEmitido($b);
            ReglasBoletos::exigirAntesDeSalir($b->getSalida(), $ahora, "anular el boleto");
            $this->bitacora->anotar($b, ["motivo" => $motivo]);
            $b->setEstado(EstadoBoletoAsiento::ANULADO);
            $precio = $b->getPrecio();
            if ($precio !== null) {
                $devuelto = $devuelto === null ? $precio : $devuelto->add($precio);
            }
        }

        $vivos = $venta->getAsientos()->filter(static fn(BoletoAsiento $b) => !in_array($b->getEstado(), [EstadoBoletoAsiento::ANULADO, EstadoBoletoAsiento::REASIGNADO], true));
        if ($vivos->isEmpty() && in_array($venta->getEstadoFacturacion(), [EstadoFacturacion::CERTIFICADA, EstadoFacturacion::PENDIENTE], true)) {
            $venta->setEstadoFacturacion(EstadoFacturacion::ANULADA);
        }

        $agencia = $venta->getAgencia();
        if ($agencia !== null && $devuelto !== null) {
            $this->saldo->reintegrarAnulacion(
                $agencia,
                (int) $devuelto->getAmount(),
                $devuelto->getCurrency(),
                $venta,
                $usuario,
                sprintf("Anulación de boletos (asientos %s)", implode(", ", array_map(static fn(BoletoAsiento $b) => $b->getAsiento()->getNumero(), $boletos))),
            );
        }

        return array_values($salidaIds);
    }
}
