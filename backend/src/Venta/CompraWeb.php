<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Cliente;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\EstadoPagoWeb;
use App\Entity\Nacion;
use App\Entity\PagoWeb;
use App\Entity\Recorrido;
use App\Entity\ReservaAsiento;
use App\Entity\TipoDocumento;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\ConsultaContribuyente;
use App\Venta\Facturacion\Facturador;
use App\Venta\Mensaje\EnviarBoletoPorCorreo;
use App\Venta\Pago\PasarelaPago;
use App\Venta\Pago\ResultadoPago;
use App\Venta\Pago\SolicitudPago;
use App\Venta\Pago\Tarjeta;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Compra en la página web (ADR-021): cobra el carrito con tarjeta (con 3-D
 * Secure si el banco lo pide), registra la venta como si fuera de taquilla
 * (canal `web`, sin usuario), certifica la factura y envía el boleto en PDF.
 *
 * El dinero manda: cobrado el pago, la venta se registra aunque la factura
 * falle (queda `pendiente` y `app:venta:certificar-pendientes` reintenta).
 * Si los asientos se perdieron entre el cobro y el registro, se reembolsa.
 */
final class CompraWeb
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly Reservas $reservas,
        private readonly ReglasVenta $reglas,
        private readonly PasarelaPago $pasarela,
        private readonly Facturador $facturador,
        private readonly ConsultaContribuyente $contribuyentes,
        private readonly PublicadorOcupacion $publicador,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{estado: string, venta?: BoletoVenta, url?: string, campos?: array<string, string>}
     *
     * @throws VentaRechazada
     */
    public function pagar(Uuid $token, Comprador $comprador, Tarjeta $tarjeta, string $urlRetorno, ?string $ip): array
    {
        $previa = $this->venta($token);
        if ($previa !== null) {
            return ["estado" => "completado", "venta" => $previa];
        }

        $reservas = $this->reservas->vigentes($token);
        if ($reservas === []) {
            throw new VentaRechazada("Su selección de asientos venció. Elija sus asientos de nuevo.", "carrito_vencido", 410);
        }
        $recorrido = $reservas[0]->getRecorrido();
        $this->reglas->exigirVendibleEnLinea($recorrido);
        $cotizacion = $this->reglas->cotizar(
            $recorrido,
            $reservas[0]->getTrayecto(),
            array_map(static fn(ReservaAsiento $r) => $r->getAsiento(), $reservas),
        );
        // Antes de cobrar: lo que impediría facturar se corrige ahora, no después.
        $this->reglas->exigirReceptorFacturable($comprador->nit, $cotizacion->total);
        $this->exigirNitExistente($comprador->nit);
        $this->reservas->extender($token);

        $pago = new PagoWeb($token, $cotizacion->total, $comprador->toArray(), $tarjeta->marcaTarjeta(), $tarjeta->ultimos4());
        $this->em->persist($pago);
        $this->em->flush();

        try {
            $resultado = $this->pasarela->cobrar(new SolicitudPago(
                referencia: $token->toRfc4122(),
                monto: $cotizacion->total,
                tarjeta: $tarjeta,
                correo: $comprador->email,
                descripcion: sprintf(
                    "Boletos %s - %s %s",
                    $reservas[0]->getTrayecto()->getOrigen()->getNombre(),
                    $reservas[0]->getTrayecto()->getDestino()->getNombre(),
                    $recorrido->getFecha()->format("d/m/Y H:i"),
                ),
                urlRetorno: $urlRetorno,
                ip: $ip,
            ));
        } catch (\Throwable $e) {
            $this->logger->error("Pasarela sin respuesta para el carrito {token}: {error}", ["token" => (string) $token, "error" => $e->getMessage()]);
            $pago->registrar(EstadoPagoWeb::RECHAZADO, mensaje: "Sin respuesta de la pasarela: " . $e->getMessage());
            $this->em->flush();
            throw new VentaRechazada("No fue posible comunicarse con el banco. No se realizó ningún cobro; intente de nuevo.", "pasarela", 502);
        }

        return $this->procesar($pago, $resultado);
    }

    /**
     * Vuelta de 3-D Secure: el banco envía aquí al cliente.
     *
     * @param array<string, mixed> $datos
     *
     * @return array{estado: string, venta?: BoletoVenta, mensaje?: string, token: string}
     */
    public function retorno(string $referenciaPasarela, array $datos): array
    {
        $pago = $this->em->getRepository(PagoWeb::class)->findOneBy(["referenciaPasarela" => $referenciaPasarela])
            ?? throw new VentaRechazada("Pago desconocido.", "no_encontrado", 404);
        $token = $pago->getToken()->toRfc4122();

        if ($pago->getEstado() !== EstadoPagoWeb::AUTENTICACION) {
            $venta = $pago->getBoletoVenta();

            return $venta !== null
                ? ["estado" => "completado", "venta" => $venta, "token" => $token]
                : ["estado" => $pago->getEstado()->value, "mensaje" => $pago->getMensaje(), "token" => $token];
        }

        try {
            $resultado = $this->pasarela->confirmarAutenticacion($referenciaPasarela, $datos);
            $r = $this->procesar($pago, $resultado);

            return [...$r, "token" => $token];
        } catch (VentaRechazada $e) {
            return ["estado" => "rechazado", "mensaje" => $e->getMessage(), "token" => $token];
        }
    }

    /** Un NIT que la SAT no conoce haría fallar la factura; si la consulta falla, se sigue. */
    private function exigirNitExistente(string $nit): void
    {
        if ($nit === "CF") {
            return;
        }
        try {
            $existe = $this->contribuyentes->nombreDeNit($nit) !== null;
        } catch (CertificacionFallida) {
            return;
        }
        if (!$existe) {
            throw new VentaRechazada("La SAT no reconoce ese NIT. Revíselo o use CF.", "comprador_nit");
        }
    }

    public function venta(Uuid $token): ?BoletoVenta
    {
        return $this->em->getRepository(BoletoVenta::class)->findOneBy(["tokenPublico" => $token]);
    }

    /** Último intento de pago del carrito. */
    public function ultimoPago(Uuid $token): ?PagoWeb
    {
        return $this->em->getRepository(PagoWeb::class)->findOneBy(["token" => $token], ["id" => "DESC"]);
    }

    /**
     * @return array{estado: string, venta?: BoletoVenta, url?: string, campos?: array<string, string>}
     */
    private function procesar(PagoWeb $pago, ResultadoPago $resultado): array
    {
        switch ($resultado->estado) {
            case ResultadoPago::AUTENTICACION:
                $pago->registrar(EstadoPagoWeb::AUTENTICACION, $resultado->referenciaPasarela);
                $this->em->flush();

                return ["estado" => "autenticacion", "url" => $resultado->url, "campos" => $resultado->campos];

            case ResultadoPago::APROBADO:
                $pago->registrar(EstadoPagoWeb::APROBADO, $resultado->referenciaPasarela, $resultado->autorizacion);
                $this->em->flush();

                return ["estado" => "completado", "venta" => $this->completar($pago)];

            default:
                $pago->registrar(EstadoPagoWeb::RECHAZADO, $resultado->referenciaPasarela, mensaje: $resultado->mensaje);
                $this->em->flush();
                throw new VentaRechazada($resultado->mensaje ?? "El banco rechazó el pago.", "pago_rechazado", 402);
        }
    }

    /** Cobrado: registrar la venta, certificar y enviar el boleto. */
    private function completar(PagoWeb $pago): BoletoVenta
    {
        $pagoId = $pago->getId();
        try {
            /** @var BoletoVenta $venta */
            $venta = $this->transaccion->ejecutar(fn() => $this->registrar($this->em->find(PagoWeb::class, $pagoId)));
        } catch (\Throwable $e) {
            // Cobrado pero sin venta: se devuelve el dinero (asiento perdido o error inesperado).
            $pago = $this->em->find(PagoWeb::class, $pagoId);
            $this->pasarela->reembolsar((string) $pago->getReferenciaPasarela(), $pago->getMonto());
            $motivo = $e instanceof VentaRechazada ? $e->getMessage() : "error al registrar la venta";
            $pago->registrar(EstadoPagoWeb::REEMBOLSADO, mensaje: $motivo);
            $this->em->flush();
            $this->logger->error("Compra web {token} reembolsada: {motivo}", [
                "token" => (string) $pago->getToken(),
                "motivo" => $e->getMessage(),
                "exception" => $e,
            ]);
            throw new VentaRechazada(
                "No pudimos confirmar sus asientos ({$motivo}). Se reembolsó el cobro a su tarjeta.",
                "reembolsado",
                409,
            );
        }

        try {
            $this->facturador->certificar($venta);
        } catch (CertificacionFallida $e) {
            $venta->setErrorFacturacion($e->getMessage());
            $this->logger->warning("Venta web {id} sin factura (se reintentará): {error}", ["id" => $venta->getId(), "error" => $e->getMessage()]);
        } catch (\Throwable $e) {
            $venta->setErrorFacturacion("Error inesperado del certificador.");
            $this->logger->error("Venta web {id}: error del certificador: {error}", ["id" => $venta->getId(), "error" => $e->getMessage(), "exception" => $e]);
        }
        $this->em->flush();

        $this->publicador->cambio((int) $venta->getAsientos()->first()->getRecorrido()->getId());
        $this->bus->dispatch(new EnviarBoletoPorCorreo((int) $venta->getId()));

        return $venta;
    }

    private function registrar(PagoWeb $pago): BoletoVenta
    {
        $token = $pago->getToken();
        $reservas = $this->em->getRepository(ReservaAsiento::class)->findBy(["token" => $token]);
        if ($reservas === []) {
            throw new VentaRechazada("la selección de asientos ya no existe");
        }
        $recorrido = $this->em->find(Recorrido::class, $reservas[0]->getRecorrido()->getId(), LockMode::PESSIMISTIC_WRITE);
        $trayecto = $reservas[0]->getTrayecto();
        $asientos = array_map(static fn(ReservaAsiento $r) => $r->getAsiento(), $reservas);
        // Las reservas propias no estorban (aunque hayan vencido durante el pago).
        $this->reglas->exigirDisponibles($recorrido, $this->reglas->tramo($recorrido, $trayecto), $asientos, $token->toRfc4122());
        $cotizacion = $this->reglas->cotizar($recorrido, $trayecto, $asientos);
        if (!$cotizacion->total->equals($pago->getMonto())) {
            throw new VentaRechazada("la tarifa cambió durante el pago");
        }

        $cliente = $this->cliente(Comprador::desdeArray($pago->getComprador()));
        $venta = (new BoletoVenta())
            ->setTokenPublico($token)
            ->setCanal(CanalVenta::WEB)
            ->setCliente($cliente)
            ->setTotal($cotizacion->total)
            ->setEnviarCorreo(true)
            ->setReferenciaPago($pago->getAutorizacion())
            ->setEstado(EstadoBoletoVenta::CONFIRMADA)
            ->setEstadoFacturacion($cotizacion->total->isZero() ? EstadoFacturacion::NO_APLICA : EstadoFacturacion::PENDIENTE)
            ->setCreatedAt(new \DateTime());
        $this->em->persist($venta);

        foreach ($asientos as $asiento) {
            $boleto = (new BoletoAsiento())
                ->setAsiento($asiento)
                ->setTrayecto($trayecto)
                ->setRecorrido($recorrido)
                ->setCliente($cliente)
                ->setPrecio($cotizacion->precioDe($asiento));
            $venta->addAsiento($boleto);
            $this->em->persist($boleto);
        }
        foreach ($reservas as $r) {
            $this->em->remove($r);
        }
        $pago->completar($venta);

        return $venta;
    }

    private function cliente(Comprador $c): Cliente
    {
        $cliente = $c->nit !== "CF"
            ? $this->em->getRepository(Cliente::class)->findOneBy(["nit" => $c->nit], ["id" => "DESC"])
            : null;
        if ($cliente === null) {
            $cliente = (new Cliente())->setNombre($c->nombre)->setApellido($c->apellido)->setNit($c->nit);
            $cliente->setCreatedAt(new \DateTime());
            $this->em->persist($cliente);
        }
        $cliente->setEmail($c->email);
        if ($c->telefono) {
            $cliente->setTelefono($c->telefono);
        }
        if ($c->numeroDocumento) {
            $cliente
                ->setNumeroDocumento($c->numeroDocumento)
                ->setTipoDocumento($c->tipoDocumentoId ? $this->em->find(TipoDocumento::class, $c->tipoDocumentoId) : null);
        }
        if ($c->nacionalidadId) {
            $cliente->setNacionalidad($this->em->find(Nacion::class, $c->nacionalidadId));
        }

        return $cliente;
    }
}
