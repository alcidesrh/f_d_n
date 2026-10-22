<?php

declare(strict_types=1);

namespace App\Venta\Anulacion;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Limite;
use App\Acceso\Ubicador;
use App\Autorizacion\Autorizable;
use App\Autorizacion\DispensasEnCurso;
use App\Autorizacion\Pases;
use App\Encomienda\MovimientosEncomienda;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\Dispensa;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\OperacionBoleto;
use App\Entity\Enum\TransicionBoleto;
use App\Entity\Salida;
use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;
use App\Venta\Agencia\SaldoAgencia;
use App\Venta\Boleto\Flujo\FlujoBoleto;
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
 * Anulación de boletos antes de la hora de salida (o pospuestos).
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
 *
 * Sin el permiso sobre la venta, pasado el límite diario de anulaciones del
 * rol o con la salida ya pasada de hora, se anula con una autorización
 * aprobada (ADR-040), que se usa en la transacción de su venta.
 */
final class AnulacionBoletos
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly Facturador $facturador,
        private readonly SaldoAgencia $saldo,
        private readonly PublicadorOcupacion $publicador,
        private readonly FlujoBoleto $flujo,
        private readonly MovimientosEncomienda $encomiendas,
        private readonly LoggerInterface $logger,
        private readonly Autorizacion $autorizacion,
        private readonly Pases $pases,
        private readonly DispensasEnCurso $dispensas,
        private readonly ClockInterface $reloj,
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

        $porVenta = $this->validar($ids, $usuario, $puedeOperar);

        $anulados = [];
        $fallidos = [];
        foreach ($porVenta as $ventaId => ["boletos" => $boletoIds, "pases" => $pases]) {
            try {
                $this->anularVenta($ventaId, $boletoIds, (int) $usuario->getId(), $motivo, $pases);
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
     * Todo lo que se puede comprobar sin efectos, y las autorizaciones que
     * hacen falta (se usan luego, en la transacción de cada venta).
     *
     * @param list<int>                   $ids
     * @param callable(BoletoVenta): bool $puedeOperar
     *
     * @return array<int, array{boletos: list<int>, pases: list<int>}> por id de venta: sus boletos y las autorizaciones a usar
     */
    private function validar(array $ids, Usuario $usuario, callable $puedeOperar): array
    {
        /** @var list<BoletoAsiento> $boletos */
        $boletos = $this->em->getRepository(BoletoAsiento::class)->findBy(["id" => $ids]);
        if (count($boletos) !== count($ids)) {
            throw new VentaRechazada("Algún boleto no existe.", "no_encontrado", 404);
        }

        /** @var array<int, list<BoletoAsiento>> $grupos */
        $grupos = [];
        foreach ($boletos as $b) {
            $grupos[(int) $b->getBoletoVenta()->getId()][] = $b;
        }

        $ahora = $this->reloj->now();
        $hechas = null;
        $porVenta = [];
        foreach ($grupos as $ventaId => $seleccion) {
            $venta = $seleccion[0]->getBoletoVenta();
            $boletoIds = array_map(static fn(BoletoAsiento $b) => (int) $b->getId(), $seleccion);
            $pases = [];
            if (!$puedeOperar($venta)) {
                $pases[] = $this->pases->exigir($usuario, Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::PERMISO, $boletoIds), "No puede anular boletos de esta venta.", "permiso");
            }
            $pases = [...$pases, ...$this->exigirHora($seleccion, $usuario, $ahora)];
            $limite = $this->autorizacion->de($usuario)->limite(Capacidad::BOLETO_ANULAR->value, Limite::ANULACIONES_POR_DIA, Ubicador::venta($venta));
            if ($limite !== null) {
                $hechas ??= $this->anuladosHoy($usuario);
                if ($hechas + count($boletoIds) > $limite) {
                    $pases[] = $this->pases->exigir($usuario, Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::LIMITE, $boletoIds), sprintf("Llegó al límite de %d boletos anulados por día.", $limite), "limite");
                }
                $hechas += count($boletoIds);
            }
            ReglasBoletos::exigirVentaCompleta($venta, $seleccion);
            $porVenta[$ventaId] = ["boletos" => $boletoIds, "pases" => array_map(static fn(SolicitudAutorizacion $p) => (int) $p->getId(), $pases)];
        }

        return $porVenta;
    }

    /**
     * Lo que el flujo exige a cada boleto; si a alguno solo lo detiene la hora
     * de su salida, la autorización fuera de hora que lo cubre (y la dispensa
     * para el guard del flujo, que vale toda la petición).
     *
     * @param list<BoletoAsiento> $seleccion
     *
     * @return list<SolicitudAutorizacion>
     */
    private function exigirHora(array $seleccion, Usuario $usuario, \DateTimeImmutable $ahora): array
    {
        $fueraDeHora = [];
        $rechazoHora = null;
        foreach ($seleccion as $b) {
            $rechazo = $this->flujo->rechazo($b, TransicionBoleto::ANULAR);
            if ($rechazo === null) {
                continue;
            }
            if (!ReglasBoletos::soloPorHora($b, $ahora)) {
                throw $rechazo;
            }
            $fueraDeHora[] = (int) $b->getId();
            $rechazoHora ??= $rechazo;
        }
        if ($rechazoHora === null) {
            return [];
        }
        // Con la dispensa, que no lo detenga otra cosa (antes de pedir la autorización).
        $this->dispensas->permitirFueraDeHora(...$fueraDeHora);
        foreach ($seleccion as $b) {
            $this->flujo->exigir($b, TransicionBoleto::ANULAR);
        }

        return [$this->pases->exigir($usuario, Autorizable::boletos(Capacidad::BOLETO_ANULAR, Dispensa::HORA, $fueraDeHora), $rechazoHora->getMessage(), $rechazoHora->codigo, 409)];
    }

    /** Boletos que anuló hoy `$usuario` (bitácora), para su límite diario. */
    private function anuladosHoy(Usuario $usuario): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM bitacora WHERE entidad = 'boleto' AND operacion = :operacion AND usuario_id = :usuario AND fecha >= :hoy",
            ["operacion" => OperacionBoleto::ANULADO->value, "usuario" => $usuario->getId(), "hoy" => (new \DateTimeImmutable("today"))->format("Y-m-d H:i:s")],
        );
    }

    /**
     * @param list<int> $boletoIds
     * @param list<int> $pases     autorizaciones a usar en la transacción
     *
     * @throws CertificacionFallida
     */
    private function anularVenta(int $ventaId, array $boletoIds, int $usuarioId, string $motivo, array $pases): void
    {
        $venta = $this->em->find(BoletoVenta::class, $ventaId) ?? throw new VentaRechazada("La venta no existe.", "no_encontrado", 404);
        if ($venta->getFactura() !== null) {
            // Se confirma primero en el certificador; si falla, no cambia nada.
            $this->facturador->anular($venta, $motivo);
            $this->em->flush();
        }

        try {
            $salidas = $this->transaccion->ejecutar(fn() => $this->aplicar($ventaId, $boletoIds, $usuarioId, $motivo, $pases));
        } catch (VentaRechazada $e) {
            $this->logger->error("La factura de la venta {venta} se anuló pero sus boletos no: {error}", ["venta" => $ventaId, "error" => $e->getMessage()]);
            throw $e;
        }

        foreach ($salidas as $salidaId) {
            $this->publicador->cambio($salidaId);
        }
    }

    /**
     * Dentro de la transacción: bloquea las salidas, usa las autorizaciones,
     * vuelve a comprobar y anula.
     *
     * @param list<int> $boletoIds
     * @param list<int> $pases
     *
     * @return list<int> salidas afectadas
     */
    private function aplicar(int $ventaId, array $boletoIds, int $usuarioId, string $motivo, array $pases): array
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

        foreach ($pases as $id) {
            $pase = $this->em->find(SolicitudAutorizacion::class, $id) ?? throw new VentaRechazada("La autorización ya no existe.", "autorizacion_cerrada", 409);
            $this->pases->usar($pase, $usuario);
        }

        $devuelto = null;
        foreach ($boletos as $b) {
            $this->em->refresh($b);
            $this->flujo->aplicar($b, TransicionBoleto::ANULAR, ["motivo" => $motivo, "autorizaciones" => $pases ?: null]);
            $precio = $b->getPrecio();
            if ($precio !== null) {
                $devuelto = $devuelto === null ? $precio : $devuelto->add($precio);
            }
        }

        $vivos = $venta->getAsientos()->filter(static fn(BoletoAsiento $b) => !in_array($b->getEstado(), [EstadoBoletoAsiento::ANULADO, EstadoBoletoAsiento::REASIGNADO], true));
        if ($vivos->isEmpty() && in_array($venta->getEstadoFacturacion(), [EstadoFacturacion::CERTIFICADA, EstadoFacturacion::PENDIENTE], true)) {
            $venta->setEstadoFacturacion(EstadoFacturacion::ANULADA);
        }
        // Sin pasajeros, sus encomiendas no viajan (ADR-035).
        if ($vivos->isEmpty()) {
            $this->encomiendas->alAnularVenta($venta, $motivo);
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
