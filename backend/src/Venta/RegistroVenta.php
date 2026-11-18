<?php

declare(strict_types=1);

namespace App\Venta;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Limite;
use App\Acceso\Ubicacion;
use App\Acceso\Ubicador;
use App\Autorizacion\Autorizable;
use App\Autorizacion\Pases;
use App\Entity\Agencia;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Encomienda\TarifasEncomienda;
use App\Entity\Cliente;
use App\Entity\Encomienda;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\Dispensa;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Estacion;
use App\Entity\Moneda;
use App\Entity\Salida;
use App\Entity\TipoPago;
use App\Entity\Trayecto;
use App\Entity\Usuario;
use App\Venta\Agencia\SaldoAgencia;
use App\Venta\Excepcion\FacturacionFallida;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\Facturador;
use App\Venta\Mensaje\EnviarBoletoPorCorreo;
use App\Vigilancia\ConteoDiario;
use App\Vigilancia\UmbralesDiarios;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Venta en taquilla y en agencias (ADR-021).
 *
 * 1. En una transacción, con el salida bloqueado: valida, cotiza, aparta
 *    los asientos (venta `pendiente` si lleva factura) y, en agencias,
 *    descuenta el saldo.
 * 2. Fuera de la transacción (no se bloquea el salida mientras responde
 *    el certificador): certifica la factura.
 * 3. Si se certificó, la venta queda `confirmada`; si no, se borra —nada
 *    queda registrado— y el usuario decide: cancelar, reintentar o, con
 *    permiso, reintentar sin factura electrónica (contingencia).
 *
 * Idempotente por `token`: repetir el mismo pedido devuelve la misma venta.
 *
 * Alcance (ADR-039): `venta.vender` —y `venta.cortesia` o
 * `venta.sin_factura` si se piden— sobre la empresa de la salida y el lugar
 * de emisión (la estación, o la agencia del usuario); las cortesías, con el
 * límite diario del rol.
 */
final class RegistroVenta
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly ReglasVenta $reglas,
        private readonly SaldoAgencia $saldo,
        private readonly Facturador $facturador,
        private readonly TarifasEncomienda $tarifasEncomienda,
        private readonly PublicadorOcupacion $publicador,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly Autorizacion $autorizacion,
        private readonly Pases $pases,
        private readonly ConteoDiario $conteo,
        private readonly UmbralesDiarios $umbrales,
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
        if ($s->encomiendas !== [] && ($agencia !== null || $s->cortesia)) {
            throw new VentaRechazada("Las encomiendas se registran en la venta de una estación, y no en una cortesía.", "encomienda_no_permitida", 403);
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
        if ($venta->isCortesia()) {
            // Límite de aviso del rol (ADR-044): avisa a quien aprueba si con esta llegó al número del día.
            $hechas = $this->conteo->cortesias($usuario);
            $boletos = $venta->getAsientos()->toArray();
            $this->umbrales->trasOperar(
                $usuario,
                Limite::AVISO_CORTESIAS_POR_DIA,
                $hechas - count($boletos),
                $hechas,
                Ubicador::venta($venta),
                array_values(array_map(static fn(BoletoAsiento $b) => ["tipo" => "BoletoAsiento", "id" => (int) $b->getId()], $boletos)),
            );
        }

        $this->publicador->cambio((int) $venta->getAsientos()->first()->getSalida()->getId());
        if ($venta->isEnviarCorreo() && $venta->getCliente()?->getEmail()) {
            $this->bus->dispatch(new EnviarBoletoPorCorreo((int) $venta->getId()));
        }

        return $venta;
    }

    /** Paso 1 (dentro de la transacción): valida, cotiza y aparta. */
    private function registrar(SolicitudVenta $s, int $usuarioId, Uuid $token, CanalVenta $canal, bool $conFactura): BoletoVenta
    {
        $usuario = $this->em->find(Usuario::class, $usuarioId);
        $salida = $this->em->find(Salida::class, $s->salidaId, LockMode::PESSIMISTIC_WRITE)
            ?? throw new VentaRechazada("El salida no existe.", "no_encontrado", 404);
        $this->reglas->exigirVendibleEnTaquilla($salida);

        $agencia = $usuario->getAgencia();
        if ($agencia !== null) {
            $this->exigirAgenciaPuedeVender($agencia, $salida);
        }
        $estacion = $canal === CanalVenta::ESTACION ? $this->estacion($s, $usuario) : null;
        $this->exigirAlcance($s, $usuario, Ubicador::operacion($salida->getEmpresa()?->getId(), $usuario, $estacion?->getId()));

        $trayecto = $this->reglas->trayecto($salida, $s->trayectoId);
        $tramo = $this->reglas->tramo($salida, $trayecto);
        $asientos = $this->reglas->asientos($salida, array_column($s->asientos, "asiento"));
        $this->reglas->exigirDisponibles($salida, $tramo, $asientos);
        $cotizacion = $this->reglas->cotizar($salida, $trayecto, $asientos, $s->cobrarTrayectoCompleto, $s->cortesia);

        $cliente = $this->em->find(Cliente::class, $s->clienteId)
            ?? throw new VentaRechazada("El cliente no existe.");
        if ($conFactura) {
            $this->reglas->exigirReceptorFacturable($cliente->getNit(), $cotizacion->total);
        }

        $conFacturaAhora = $conFactura && !$s->sinFacturaElectronica && !$cotizacion->total->isZero();
        $venta = (new BoletoVenta())
            ->setTokenPublico($token)
            ->setCanal($canal)
            ->setUsuario($usuario)
            ->setAgencia($agencia)
            ->setEstacion($estacion)
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
            // Contingencia: el número de acceso de la SAT se asigna al vender y se imprime.
            ->setNumeroAcceso($conFactura && $s->sinFacturaElectronica ? Facturador::nuevoNumeroAcceso() : null)
            ->setCreatedAt(new \DateTime());
        $this->em->persist($venta);

        $pasajeros = $this->pasajeros($s, $cliente);
        foreach ($asientos as $i => $asiento) {
            $boleto = (new BoletoAsiento())
                ->setAsiento($asiento)
                ->setTrayecto($trayecto)
                ->ocuparTramo($tramo->desde, $tramo->hasta)
                ->setSalida($salida)
                ->setCliente($pasajeros[$s->asientos[$i]["cliente"] ?? $cliente->getId()])
                ->setPrecio($cotizacion->precioDe($asiento))
                ->setObservacion($s->observacion);
            $venta->addAsiento($boleto);
            $this->em->persist($boleto);
        }

        $this->agregarEncomiendas($s, $venta, $salida, $trayecto, $cliente, $usuario);

        if ($agencia !== null) {
            $this->saldo->debitarVenta($agencia, $venta, $usuario);
        }

        return $venta;
    }

    /**
     * Las encomiendas del pasajero (ADR-035): de donde sube a donde baja, a
     * su nombre, embarcadas en la salida del boleto. Su importe se suma al
     * total de la venta (y a su factura).
     */
    private function agregarEncomiendas(SolicitudVenta $s, BoletoVenta $venta, Salida $salida, Trayecto $trayecto, Cliente $cliente, Usuario $usuario): void
    {
        if ($s->encomiendas === []) {
            return;
        }
        $total = $venta->getTotal();
        $boleto = $venta->getAsientos()->first() ?: null;
        foreach ($s->encomiendas as $pedido) {
            ['cotizacion' => $cotizacion, 'articulos' => $articulos] = $this->tarifasEncomienda->cotizar($trayecto->getOrigen(), $trayecto->getDestino(), $pedido["articulos"]);
            $encomienda = (new Encomienda())
                ->setEmpresa($salida->getEmpresa() ?? throw new VentaRechazada("La salida no tiene empresa."))
                ->setOrigen($trayecto->getOrigen())
                ->setDestino($trayecto->getDestino())
                ->setRuta($salida->getRuta())
                ->setRemitente($cliente)
                ->setDestinatario($cliente)
                ->setModalidad(ModalidadEncomienda::CON_BOLETO)
                ->setEstado(EstadoEncomienda::EMBARCADA)
                ->setSalida($salida)
                ->setBoleto($boleto)
                ->setTotal($cotizacion->total)
                ->setDescuento($cotizacion->descuento)
                ->setUsuario($usuario)
                ->setEstacion($venta->getEstacion())
                ->setObservacion($pedido["observacion"]);
            foreach ($articulos as $a) {
                $encomienda->addArticulo($a);
            }
            $venta->addEncomienda($encomienda);
            $this->em->persist($encomienda);
            $total = $total->add($cotizacion->total);
        }
        $venta->setTotal($total);
        if ($venta->getEstadoFacturacion() === EstadoFacturacion::PENDIENTE) {
            $this->reglas->exigirReceptorFacturable($cliente->getNit(), $total);
        }
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
        $salidaId = (int) $venta->getAsientos()->first()->getSalida()->getId();
        $this->transaccion->ejecutar(function () use ($venta) {
            $venta->setFactura(null);
            $this->em->remove($venta);
        });
        $this->publicador->cambio($salidaId);
    }

    private function exigirAgenciaPuedeVender(Agencia $agencia, Salida $salida): void
    {
        if (!$agencia->isActivo()) {
            throw new VentaRechazada("La agencia está inactiva: no puede vender.", "agencia_inactiva", 403);
        }
        if (!$agencia->vende($salida->getEmpresa())) {
            throw new VentaRechazada("La agencia no vende salidas de esta empresa.", "agencia_empresa", 403);
        }
    }

    /** @throws VentaRechazada */
    private function exigirAlcance(SolicitudVenta $s, Usuario $usuario, Ubicacion $donde): void
    {
        $permisos = $this->autorizacion->de($usuario);
        if (!$permisos->puede(Capacidad::VENTA_VENDER->value, $donde)) {
            throw new VentaRechazada("Fuera de su alcance: no vende salidas de esta empresa en esta estación.", "alcance", 403);
        }
        if ($s->sinFacturaElectronica && !$permisos->puede(Capacidad::VENTA_SIN_FACTURA->value, $donde)) {
            throw new VentaRechazada("Fuera de su alcance: no puede vender sin factura electrónica aquí.", "alcance", 403);
        }
        if (!$s->cortesia) {
            return;
        }
        // Sin el permiso o pasado el límite, con una autorización para esta salida (ADR-040); se usa en esta transacción.
        $asientos = count($s->asientos);
        if (!$permisos->puede(Capacidad::VENTA_CORTESIA->value, $donde)) {
            $this->pases->usar($this->pases->exigir(
                $usuario,
                Autorizable::salida(Capacidad::VENTA_CORTESIA, Dispensa::PERMISO, $s->salidaId, $asientos),
                "Fuera de su alcance: no puede emitir cortesías aquí.",
                "alcance",
            ), $usuario);

            return;
        }
        $limite = $permisos->limite(Capacidad::VENTA_CORTESIA->value, Limite::CORTESIAS_POR_DIA, $donde);
        if ($limite !== null && $this->conteo->cortesias($usuario) + $asientos > $limite) {
            $this->pases->usar($this->pases->exigir(
                $usuario,
                Autorizable::salida(Capacidad::VENTA_CORTESIA, Dispensa::LIMITE, $s->salidaId, $asientos),
                sprintf("Llegó al límite de %d asientos de cortesía por día.", $limite),
                "limite",
            ), $usuario);
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
