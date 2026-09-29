<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Agencia;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Cliente;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Estacion;
use App\Entity\Moneda;
use App\Entity\Recorrido;
use App\Entity\TipoPago;
use App\Entity\Usuario;
use App\Venta\Agencia\SaldoAgencia;
use App\Venta\Excepcion\FacturacionFallida;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\Facturador;
use App\Venta\Mensaje\EnviarBoletoPorCorreo;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Venta en taquilla y en agencias (ADR-021).
 *
 * 1. En una transacción, con el recorrido bloqueado: valida, cotiza, aparta
 *    los asientos (venta `pendiente` si lleva factura) y, en agencias,
 *    descuenta el saldo.
 * 2. Fuera de la transacción (no se bloquea el recorrido mientras responde
 *    el certificador): certifica la factura.
 * 3. Si se certificó, la venta queda `confirmada`; si no, se borra —nada
 *    queda registrado— y el usuario decide: cancelar, reintentar o, con
 *    permiso, reintentar sin factura electrónica (contingencia).
 *
 * Idempotente por `token`: repetir el mismo pedido devuelve la misma venta.
 */
final class RegistroVenta
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly ReglasVenta $reglas,
        private readonly SaldoAgencia $saldo,
        private readonly Facturador $facturador,
        private readonly PublicadorOcupacion $publicador,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws VentaRechazada
     */
    public function vender(SolicitudVenta $s, Usuario $usuario, bool $permiteSinFactura = false): BoletoVenta
    {
        $token = Uuid::fromString($s->token);
        $previa = $this->em->getRepository(BoletoVenta::class)->findOneBy(["tokenPublico" => $token]);
        if ($previa !== null) {
            if ($previa->getEstado() === EstadoBoletoVenta::CONFIRMADA) {
                return $previa;
            }
            throw new VentaRechazada("Esta venta ya se está procesando.", "venta_en_proceso", 409);
        }

        $agencia = $usuario->getAgencia();
        $canal = $agencia !== null ? CanalVenta::AGENCIA : CanalVenta::ESTACION;
        if ($agencia !== null && ($s->cortesia || $s->sinFacturaElectronica)) {
            throw new VentaRechazada("Las agencias solo venden boletos: sin cortesías ni contingencia de factura.", "permiso", 403);
        }
        $conFactura = $canal === CanalVenta::ESTACION && !$s->cortesia;

        $usuarioId = $usuario->getId();
        try {
            /** @var BoletoVenta $venta */
            $venta = $this->transaccion->ejecutar(fn() => $this->registrar($s, $usuarioId, $token, $canal, $conFactura));
        } catch (UniqueConstraintViolationException) {
            // Mismo token enviado dos veces a la vez: la otra petición ya la está registrando.
            throw new VentaRechazada("Esta venta ya se está procesando.", "venta_en_proceso", 409);
        }

        if ($venta->getEstado() === EstadoBoletoVenta::PENDIENTE) {
            $this->certificarOAnular($venta, $permiteSinFactura);
        }

        $this->publicador->cambio((int) $venta->getAsientos()->first()->getRecorrido()->getId());
        if ($venta->isEnviarCorreo() && $venta->getCliente()?->getEmail()) {
            $this->bus->dispatch(new EnviarBoletoPorCorreo((int) $venta->getId()));
        }

        return $venta;
    }

    /** Paso 1 (dentro de la transacción): valida, cotiza y aparta. */
    private function registrar(SolicitudVenta $s, int $usuarioId, Uuid $token, CanalVenta $canal, bool $conFactura): BoletoVenta
    {
        $usuario = $this->em->find(Usuario::class, $usuarioId);
        $recorrido = $this->em->find(Recorrido::class, $s->recorridoId, LockMode::PESSIMISTIC_WRITE)
            ?? throw new VentaRechazada("El recorrido no existe.", "no_encontrado", 404);
        $this->reglas->exigirVendibleEnTaquilla($recorrido);

        $agencia = $usuario->getAgencia();
        if ($agencia !== null) {
            $this->exigirAgenciaPuedeVender($agencia, $recorrido);
        }

        $trayecto = $this->reglas->trayecto($recorrido, $s->trayectoId);
        $tramo = $this->reglas->tramo($recorrido, $trayecto);
        $asientos = $this->reglas->asientos($recorrido, array_column($s->asientos, "asiento"));
        $this->reglas->exigirDisponibles($recorrido, $tramo, $asientos);
        $cotizacion = $this->reglas->cotizar($recorrido, $trayecto, $asientos, $s->cobrarTrayectoCompleto, $s->cortesia);

        $cliente = $this->em->find(Cliente::class, $s->clienteId)
            ?? throw new VentaRechazada("El cliente no existe.");

        $conFacturaAhora = $conFactura && !$s->sinFacturaElectronica && !$cotizacion->total->isZero();
        $venta = (new BoletoVenta())
            ->setTokenPublico($token)
            ->setCanal($canal)
            ->setUsuario($usuario)
            ->setAgencia($agencia)
            ->setEstacion($canal === CanalVenta::ESTACION ? $this->estacion($s, $usuario) : null)
            ->setCliente($cliente)
            ->setTipoPago($s->tipoPagoId ? $this->em->find(TipoPago::class, $s->tipoPagoId) : null)
            ->setMoneda($s->monedaId ? $this->em->find(Moneda::class, $s->monedaId) : null)
            ->setTotal($cotizacion->total)
            ->setCortesia($s->cortesia)
            ->setEnviarCorreo($s->enviarCorreo)
            ->setEstado($conFacturaAhora ? EstadoBoletoVenta::PENDIENTE : EstadoBoletoVenta::CONFIRMADA)
            ->setEstadoFacturacion(match (true) {
                !$conFactura || $cotizacion->total->isZero() => EstadoFacturacion::NO_APLICA,
                default => EstadoFacturacion::PENDIENTE,
            })
            ->setErrorFacturacion($conFactura && $s->sinFacturaElectronica ? "Venta en contingencia: sin factura electrónica." : null)
            ->setCreatedAt(new \DateTime());
        $this->em->persist($venta);

        $pasajeros = $this->pasajeros($s, $cliente);
        foreach ($asientos as $i => $asiento) {
            $boleto = (new BoletoAsiento())
                ->setAsiento($asiento)
                ->setTrayecto($trayecto)
                ->setRecorrido($recorrido)
                ->setCliente($pasajeros[$s->asientos[$i]["cliente"] ?? $cliente->getId()])
                ->setPrecio($cotizacion->precioDe($asiento))
                ->setObservacion($s->observacion);
            $venta->addAsiento($boleto);
            $this->em->persist($boleto);
        }

        if ($agencia !== null) {
            $this->saldo->debitarVenta($agencia, $venta, $usuario);
        }

        return $venta;
    }

    private function certificarOAnular(BoletoVenta $venta, bool $permiteSinFactura): void
    {
        try {
            $this->facturador->certificar($venta);
        } catch (CertificacionFallida $e) {
            $this->anular($venta);
            throw FacturacionFallida::por($e->getMessage(), $e->recuperable, $permiteSinFactura && $e->recuperable);
        } catch (\Throwable $e) {
            $this->logger->error("Error inesperado al certificar la venta {id}: {error}", [
                "id" => $venta->getId(),
                "error" => $e->getMessage(),
                "exception" => $e,
            ]);
            $this->anular($venta);
            $fallo = CertificacionFallida::sinRespuesta($e);
            throw FacturacionFallida::por($fallo->getMessage(), true, $permiteSinFactura);
        }

        $venta->setEstado(EstadoBoletoVenta::CONFIRMADA);
        $this->em->flush();
    }

    /** La factura no salió: la venta desaparece y los asientos quedan libres. */
    private function anular(BoletoVenta $venta): void
    {
        $recorridoId = (int) $venta->getAsientos()->first()->getRecorrido()->getId();
        $this->transaccion->ejecutar(function () use ($venta) {
            $venta->setFactura(null);
            $this->em->remove($venta);
        });
        $this->publicador->cambio($recorridoId);
    }

    private function exigirAgenciaPuedeVender(Agencia $agencia, Recorrido $recorrido): void
    {
        if (!$agencia->isActivo()) {
            throw new VentaRechazada("La agencia está inactiva: no puede vender.", "agencia_inactiva", 403);
        }
        $empresa = $agencia->getEmpresa();
        if ($empresa !== null && $recorrido->getEmpresa()?->getId() !== $empresa->getId()) {
            throw new VentaRechazada("La agencia no vende recorridos de esta empresa.", "agencia_empresa", 403);
        }
    }

    private function estacion(SolicitudVenta $s, Usuario $usuario): ?Estacion
    {
        if ($s->estacionId === null) {
            return $usuario->getEstacion();
        }

        return $this->em->find(Estacion::class, $s->estacionId)
            ?? throw new VentaRechazada("La estación no existe.");
    }

    /**
     * @return array<int, Cliente> por id: el cliente de la venta y los pasajeros indicados
     */
    private function pasajeros(SolicitudVenta $s, Cliente $cliente): array
    {
        $ids = array_values(array_unique(array_filter(array_column($s->asientos, "cliente"))));
        $pasajeros = [$cliente->getId() => $cliente];
        if ($ids === []) {
            return $pasajeros;
        }
        foreach ($this->em->getRepository(Cliente::class)->findBy(["id" => $ids]) as $c) {
            $pasajeros[$c->getId()] = $c;
        }
        if (count(array_diff($ids, array_keys($pasajeros))) > 0) {
            throw new VentaRechazada("Algún pasajero indicado no existe.");
        }

        return $pasajeros;
    }
}
