<?php

declare(strict_types=1);

namespace App\Venta\EnLinea;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\EstadoPagoWeb;
use App\Entity\Enum\TransicionBoleto;
use App\Entity\PagoWeb;
use App\Entity\Salida;
use App\Venta\Boleto\Flujo\FlujoBoleto;
use App\Venta\Comprador;
use App\Venta\CompraWeb;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\Facturador;
use App\Venta\Mensaje\EnviarBoletoPorCorreo;
use App\Venta\PublicadorOcupacion;
use App\Venta\ReglasVenta;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Acciones del dashboard sobre una compra de la página web (ida y, si hay,
 * regreso): corregir el comprador, posponer el viaje, reemitir la factura y
 * reenviar el boleto. Anular y reasignar van por `AnulacionBoletos` y
 * `ReasignacionBoletos` con los boletos de la compra.
 */
final class GestionCompraWeb
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly CompraWeb $compras,
        private readonly ReglasVenta $reglas,
        private readonly Facturador $facturador,
        private readonly PublicadorOcupacion $publicador,
        private readonly FlujoBoleto $flujo,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Corrige nombre, apellido, correo, teléfono, NIT y nombre de la factura
     * del comprador (con NIT, la razón social de la SAT). La factura ya
     * emitida no cambia: para eso, `reemitirFactura`. Con otro
     * NIT las ventas pasan al cliente de ese NIT (o a uno nuevo): el cliente
     * de un NIT lo comparten otras compras y no se le cambia el NIT.
     *
     * @param array<string, mixed> $datos
     *
     * @throws VentaRechazada
     */
    public function editarComprador(PagoWeb $pago, array $datos): PagoWeb
    {
        $actual = $pago->getComprador();
        $nuevo = Comprador::desdeArray([
            ...$actual,
            ...array_intersect_key($datos, array_flip(["nombre", "apellido", "email", "telefono", "nit", "nombreFactura"])),
        ]);
        $ventas = $this->ventas($pago);
        foreach ($ventas as $v) {
            $this->reglas->exigirReceptorFacturable($nuevo->nit, $v->getTotal());
        }
        $cambiaNit = $nuevo->nit !== Comprador::desdeArray($actual)->nit;
        if ($cambiaNit && !array_key_exists("nombreFactura", $datos)) {
            $nuevo = $nuevo->conNombreFactura(null); // la del NIT anterior ya no aplica
        }
        $razonSocial = $cambiaNit ? $this->compras->exigirNitExistente($nuevo->nit) : null;
        if ($razonSocial !== null) {
            $nuevo = $nuevo->conNombreFactura($razonSocial);
        }

        $this->transaccion->ejecutar(function () use ($pago, $nuevo, $ventas, $cambiaNit) {
            $pago->cambiarComprador($nuevo->toArray());
            foreach ($ventas as $v) {
                $v->setNombreFactura($nuevo->nombreFactura);
            }
            $anterior = $ventas[0]->getCliente();
            if ($anterior === null || $cambiaNit) {
                $cliente = $this->compras->cliente($nuevo);
                foreach ($ventas as $v) {
                    foreach ($v->getAsientos() as $b) {
                        // En la web el pasajero es el comprador: sigue al cliente de la venta.
                        if ($anterior !== null && $b->getCliente() === $anterior) {
                            $b->setCliente($cliente);
                        }
                    }
                    $v->setCliente($cliente);
                }

                return;
            }
            $anterior->setNombre($nuevo->nombre)->setApellido($nuevo->apellido)->setEmail($nuevo->email);
            if ($nuevo->telefono) {
                $anterior->setTelefono($nuevo->telefono);
            }
        });

        return $pago;
    }

    /**
     * Pospone los boletos emitidos que todavía no salen: el asiento queda
     * libre y lo demás (venta, factura, importe) sigue igual. Se reanuda
     * reasignándolos a otra salida de la misma empresa.
     *
     * @return list<int> boletos pospuestos
     *
     * @throws VentaRechazada si no hay nada que posponer
     */
    public function posponer(PagoWeb $pago, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === "") {
            throw new VentaRechazada("Indique el motivo.", "falta_motivo");
        }
        $ventas = $this->ventas($pago);
        $ventaIds = array_map(static fn(BoletoVenta $v) => (int) $v->getId(), $ventas);

        $resultado = $this->transaccion->ejecutar(function () use ($ventaIds, $motivo) {
            $ventas = array_map(fn(int $id) => $this->em->find(BoletoVenta::class, $id), $ventaIds);
            $boletos = array_merge(...array_map(
                static fn(BoletoVenta $v) => array_values($v->getAsientos()->filter(static fn(BoletoAsiento $b) => $b->getEstado() === EstadoBoletoAsiento::EMITIDO)->toArray()),
                $ventas,
            ));
            $salidaIds = array_values(array_unique(array_map(static fn(BoletoAsiento $b) => (int) $b->getSalida()->getId(), $boletos)));
            sort($salidaIds);
            foreach ($salidaIds as $id) {
                $this->em->find(Salida::class, $id, LockMode::PESSIMISTIC_WRITE);
            }

            $pospuestos = [];
            $afectadas = [];
            $motivoNo = null;
            foreach ($boletos as $b) {
                $this->em->refresh($b);
                $rechazo = $this->flujo->rechazo($b, TransicionBoleto::POSPONER);
                if ($rechazo !== null) {
                    $motivoNo ??= $rechazo;

                    continue;
                }
                $this->flujo->aplicar($b, TransicionBoleto::POSPONER, ["motivo" => $motivo]);
                $pospuestos[] = (int) $b->getId();
                $afectadas[(int) $b->getSalida()->getId()] = true;
            }
            if ($pospuestos === []) {
                throw $motivoNo ?? new VentaRechazada("La compra no tiene boletos emitidos que posponer.", "nada_que_posponer");
            }

            return ["boletos" => $pospuestos, "salidas" => array_keys($afectadas)];
        });

        foreach ($resultado["salidas"] as $salidaId) {
            $this->publicador->cambio($salidaId);
        }

        return $resultado["boletos"];
    }

    /**
     * Anula en el certificador la factura de cada viaje (si la tiene) y
     * certifica una nueva con los datos actuales del comprador. No envía nada
     * al cliente: el boleto en PDF se genera al pedirlo, ya con la factura
     * nueva. Lo que se hizo en un viaje no se deshace si el otro falla.
     *
     * @return list<array{venta: int, factura: ?array{serie: ?string, numero: ?int}, error: ?string}>
     *
     * @throws VentaRechazada si no hay nada que facturar
     */
    public function reemitirFactura(PagoWeb $pago): array
    {
        $ventas = array_values(array_filter(
            $this->ventas($pago),
            static fn(BoletoVenta $v) => $v->getEstadoFacturacion() !== EstadoFacturacion::NO_APLICA
                && $v->getEstadoFacturacion() !== EstadoFacturacion::ANULADA
                && $v->getAsientos()->exists(static fn($i, BoletoAsiento $b) => !in_array($b->getEstado(), [EstadoBoletoAsiento::ANULADO, EstadoBoletoAsiento::REASIGNADO], true)),
        ));
        if ($ventas === []) {
            throw new VentaRechazada("La compra no tiene viajes por facturar (anulados o sin cobro).", "nada_que_facturar");
        }
        foreach ($ventas as $v) {
            $this->reglas->exigirReceptorFacturable($v->getCliente()?->getNit(), $v->getTotal());
        }

        $resultado = [];
        foreach ($ventas as $v) {
            try {
                if ($v->getFactura() !== null) {
                    // Primero se anula la anterior; si falla, la venta sigue con ella.
                    $this->facturador->anular($v, "Reemisión con los datos corregidos del cliente");
                    $this->em->flush();
                }
                $factura = $this->facturador->certificar($v, reemision: true);
                $this->em->flush();
                $resultado[] = ["venta" => (int) $v->getId(), "factura" => ["serie" => $factura->getSerie(), "numero" => $factura->getDte()], "error" => null];
            } catch (CertificacionFallida $e) {
                if ($v->getFactura()?->isAnulada()) {
                    // La anterior ya no vale y la nueva no salió: queda por certificar (la reintenta el cron).
                    $v->setFactura(null)->setEstadoFacturacion(EstadoFacturacion::PENDIENTE)->setErrorFacturacion($e->getMessage());
                    $this->em->flush();
                }
                $this->logger->warning("Reemisión de la factura de la venta web {id}: {error}", ["id" => $v->getId(), "error" => $e->getMessage()]);
                $resultado[] = ["venta" => (int) $v->getId(), "factura" => null, "error" => $e->getMessage()];
            }
        }

        return $resultado;
    }

    /**
     * Vuelve a enviar el boleto (todos los viajes en un correo) al comprador
     * o a otro correo.
     *
     * @throws VentaRechazada
     */
    public function reenviar(PagoWeb $pago, ?string $correo = null): string
    {
        $ventas = $this->ventas($pago);
        $correo = trim((string) $correo) ?: (string) ($pago->getComprador()["email"] ?? "");
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new VentaRechazada("Indique un correo válido.", "correo_invalido");
        }
        $ids = array_map(static fn(BoletoVenta $v) => (int) $v->getId(), $ventas);
        $this->bus->dispatch(new EnviarBoletoPorCorreo($ids[0], array_slice($ids, 1), $correo));

        return $correo;
    }

    /**
     * La ida y, si hay, el regreso de una compra completada.
     *
     * @return non-empty-list<BoletoVenta>
     *
     * @throws VentaRechazada
     */
    private function ventas(PagoWeb $pago): array
    {
        $ventas = array_values(array_filter([$pago->getBoletoVenta(), $pago->getBoletoVentaRegreso()]));
        if ($pago->getEstado() !== EstadoPagoWeb::COMPLETADO || $ventas === []) {
            throw new VentaRechazada("La compra no está completada: no tiene boletos.", "compra_no_completada", 409);
        }
        foreach ($ventas as $v) {
            if ($v->getEstado() !== EstadoBoletoVenta::CONFIRMADA) {
                throw new VentaRechazada("La venta todavía se está procesando.", "venta_en_proceso", 409);
            }
        }

        return $ventas;
    }
}
