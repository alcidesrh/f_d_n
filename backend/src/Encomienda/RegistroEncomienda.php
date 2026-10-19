<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Cliente;
use App\Entity\Empresa;
use App\Entity\Encomienda;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Estacion;
use App\Entity\Moneda;
use App\Entity\Ruta;
use App\Entity\TipoPago;
use App\Entity\Usuario;
use App\Venta\Excepcion\FacturacionFallida;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\Facturador;
use App\Venta\ReglasVenta;
use App\Venta\Transaccion;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Money\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Registro de una encomienda sin boleto en una estación (ADR-035), como la
 * venta en taquilla (ADR-021):
 *
 * 1. En una transacción: valida, cotiza y la registra recibida en la bodega
 *    del origen.
 * 2. Si es pagada, fuera de la transacción certifica su factura al remitente
 *    (o a quien se indique). Si el certificador falla, la encomienda se borra
 *    y el usuario decide: cancelar, reintentar o, con permiso, contingencia.
 *
 * Por cobrar se factura al entregarla; interna no se cobra. Idempotente por `token`.
 */
final class RegistroEncomienda
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly TarifasEncomienda $tarifas,
        private readonly ReglasVenta $reglasVenta,
        private readonly Facturador $facturador,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws EncomiendaRechazada|FacturacionFallida
     */
    public function registrar(SolicitudEncomienda $s, Usuario $usuario, bool $permiteSinFactura = false): Encomienda
    {
        $token = Uuid::fromString($s->token);
        $previa = $this->em->getRepository(Encomienda::class)->findOneBy(["token" => $token]);
        if ($previa !== null) {
            if ($previa->getEstadoFacturacion() !== EstadoFacturacion::PENDIENTE || $previa->getNumeroAcceso() !== null) {
                return $previa;
            }
            throw new EncomiendaRechazada("Esta encomienda ya se está registrando.", "en_proceso", 409);
        }

        $usuarioId = (int) $usuario->getId();
        try {
            /** @var Encomienda $encomienda */
            $encomienda = $this->transaccion->ejecutar(fn() => $this->crear($s, $usuarioId, $token));
        } catch (UniqueConstraintViolationException) {
            throw new EncomiendaRechazada("Esta encomienda ya se está registrando.", "en_proceso", 409);
        }

        if ($encomienda->getEstadoFacturacion() === EstadoFacturacion::PENDIENTE && $encomienda->getNumeroAcceso() === null) {
            $this->certificarOBorrar($encomienda, $permiteSinFactura);
        }

        return $encomienda;
    }

    private function crear(SolicitudEncomienda $s, int $usuarioId, Uuid $token): Encomienda
    {
        $usuario = $this->em->find(Usuario::class, $usuarioId);
        $origen = $s->origenId !== null ? $this->em->find(Estacion::class, $s->origenId) : $usuario->getEstacion();
        if ($origen === null) {
            throw new EncomiendaRechazada("Indique la estación de origen (su usuario no tiene estación).");
        }
        if ($usuario->getEstacion() !== null && $usuario->getEstacion()->getId() !== $origen->getId()) {
            throw new EncomiendaRechazada("Solo se reciben encomiendas en su estación.", "otra_estacion", 403);
        }
        $destino = $this->em->find(Estacion::class, $s->destinoId)
            ?? throw new EncomiendaRechazada("La estación de destino no existe.");
        $empresa = $this->em->find(Empresa::class, $s->empresaId)
            ?? throw new EncomiendaRechazada("La empresa no existe.");
        if ($usuario->getEmpresa() !== null && $usuario->getEmpresa()->getId() !== $empresa->getId()) {
            throw new EncomiendaRechazada("Solo registra encomiendas de su empresa.", "otra_empresa", 403);
        }
        $ruta = null;
        if ($s->rutaId !== null) {
            $ruta = $this->em->find(Ruta::class, $s->rutaId) ?? throw new EncomiendaRechazada("La ruta no existe.");
            if (!ReglasEncomienda::llegaADestino(RutasEncomienda::enclaves($ruta), (int) $origen->getId(), (int) $destino->getId())) {
                throw new EncomiendaRechazada("La ruta elegida no va del origen al destino.", "fuera_de_ruta");
            }
        }
        $remitente = $this->em->find(Cliente::class, $s->remitenteId) ?? throw new EncomiendaRechazada("El remitente no existe.");
        $destinatario = $this->em->find(Cliente::class, $s->destinatarioId) ?? throw new EncomiendaRechazada("El destinatario no existe.");
        $clienteFactura = $s->clienteFacturaId !== null
            ? ($this->em->find(Cliente::class, $s->clienteFacturaId) ?? throw new EncomiendaRechazada("El cliente a facturar no existe."))
            : $remitente;

        ['cotizacion' => $cotizacion, 'articulos' => $articulos] = $this->tarifas->cotizar($origen, $destino, $s->articulos, $s->modalidad === ModalidadEncomienda::POR_COBRAR);
        $interna = $s->modalidad === ModalidadEncomienda::INTERNA;
        $pagada = $s->modalidad === ModalidadEncomienda::PAGADA && !$cotizacion->total->isZero();
        if ($pagada) {
            $this->reglasVenta->exigirReceptorFacturable($clienteFactura->getNit(), $cotizacion->total);
        }

        $encomienda = (new Encomienda())
            ->setToken($token)
            ->setEmpresa($empresa)
            ->setOrigen($origen)
            ->setDestino($destino)
            ->setRuta($ruta)
            ->setRemitente($remitente)
            ->setDestinatario($destinatario)
            ->setModalidad($s->modalidad)
            ->setEstado(EstadoEncomienda::RECIBIDA)
            ->setUbicacion($origen)
            // La interna no se cobra: se registra sin precio.
            ->setTotal($interna ? Money::GTQ(0) : $cotizacion->total)
            ->setDescuento($interna ? Money::GTQ(0) : $cotizacion->descuento)
            ->setClienteFactura($pagada ? $clienteFactura : null)
            ->setTipoPago($pagada && $s->tipoPagoId ? $this->em->find(TipoPago::class, $s->tipoPagoId) : null)
            ->setMoneda($pagada && $s->monedaId ? $this->em->find(Moneda::class, $s->monedaId) : null)
            ->setEstadoFacturacion($pagada ? EstadoFacturacion::PENDIENTE : EstadoFacturacion::NO_APLICA)
            ->setErrorFacturacion($pagada && $s->sinFacturaElectronica ? "Encomienda en contingencia: sin factura electrónica." : null)
            ->setNumeroAcceso($pagada && $s->sinFacturaElectronica ? Facturador::nuevoNumeroAcceso() : null)
            ->setUsuario($usuario)
            ->setEstacion($origen)
            ->setObservacion($s->observacion);
        foreach ($articulos as $a) {
            $encomienda->addArticulo($a);
        }
        $this->em->persist($encomienda);

        return $encomienda;
    }

    private function certificarOBorrar(Encomienda $encomienda, bool $permiteSinFactura): void
    {
        try {
            $this->facturador->certificarEncomienda($encomienda);
        } catch (CertificacionFallida $e) {
            $this->borrar($encomienda);
            throw FacturacionFallida::por($e->getMessage(), $e->recuperable, $permiteSinFactura && $e->recuperable);
        } catch (\Throwable $e) {
            $this->logger->error("Error inesperado al certificar la encomienda {id}: {error}", [
                "id" => $encomienda->getId(),
                "error" => $e->getMessage(),
                "exception" => $e,
            ]);
            $this->borrar($encomienda);
            throw FacturacionFallida::por(CertificacionFallida::sinRespuesta($e)->getMessage(), true, $permiteSinFactura);
        }
        $this->em->flush();
    }

    /** La factura no salió: la encomienda no queda registrada. */
    private function borrar(Encomienda $encomienda): void
    {
        $this->transaccion->ejecutar(function () use ($encomienda) {
            $encomienda->setFactura(null);
            $this->em->remove($encomienda);
        });
    }
}
