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
use App\Entity\Salida;
use App\Entity\ReservaAsiento;
use App\Entity\TipoDocumento;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\ConsultaContribuyente;
use App\Venta\Facturacion\Facturador;
use App\Venta\Mensaje\EnviarBoletoPorCorreo;
use App\Venta\Pago\Continuacion;
use App\Venta\Pago\DireccionFacturacion;
use App\Venta\Pago\Navegador;
use App\Venta\Pago\PagoIncierto;
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
 * Compra en la página web (ADR-021): cobra el carrito con tarjeta (con los
 * pasos de 3-D Secure que pida la pasarela), registra la venta como si fuera de taquilla
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
     * Inicia el cobro del carrito o, con `$continuar` (lo que envió el
     * navegador tras un paso de 3-D Secure), sigue el cobro en curso. En cada
     * paso la página vuelve a enviar la tarjeta: nunca se guarda.
     *
     * @param array<string, string>|null $continuar
     *
     * @return array<string, mixed> `{estado: "completado", venta}` o el paso del navegador (`ResultadoPago::paraNavegador()`)
     *
     * @throws VentaRechazada
     */
    public function pagar(
        Uuid $token,
        Comprador $comprador,
        Tarjeta $tarjeta,
        DireccionFacturacion $direccion,
        string $urlRetorno,
        Navegador $navegador,
        ?array $continuar = null,
    ): array {
        $previa = $this->venta($token);
        if ($previa !== null) {
            return ["estado" => "completado", "venta" => $previa];
        }

        if ($continuar !== null) {
            $pago = $this->ultimoPago($token);
            if ($pago === null || $pago->getEstado() !== EstadoPagoWeb::AUTENTICACION || $pago->getEstadoPasarela() === []) {
                throw new VentaRechazada("Este pago ya no está en curso. Intente pagar de nuevo.", "pago_no_en_curso", 409);
            }
            if ($pago->getUltimos4() !== $tarjeta->ultimos4() || $pago->getMarca() !== $tarjeta->marcaTarjeta()) {
                throw new VentaRechazada("La tarjeta no coincide con la del pago en curso.", "pago_tarjeta", 409);
            }
            $estado = $pago->getEstadoPasarela();
            // Cada paso se usa una sola vez (un doble envío no cobra dos veces).
            $tomado = $this->em->createQuery("UPDATE App\\Entity\\PagoWeb p SET p.estadoPasarela = NULL WHERE p.id = :id AND p.estadoPasarela IS NOT NULL")
                ->setParameter("id", $pago->getId())
                ->execute();
            if ($tomado !== 1) {
                throw new VentaRechazada("Este pago ya se está procesando.", "pago_en_proceso", 409);
            }
            $this->reservas->extender($token);
            $solicitud = $this->solicitud($pago, $tarjeta, $direccion, $urlRetorno, $navegador);

            return $this->cobrar($pago, $solicitud, new Continuacion($estado, $continuar));
        }

        $reservas = $this->reservas->vigentes($token);
        if ($reservas === []) {
            throw new VentaRechazada("Su selección de asientos venció. Elija sus asientos de nuevo.", "carrito_vencido", 410);
        }
        $salida = $reservas[0]->getSalida();
        $this->reglas->exigirVendibleEnLinea($salida);
        $cotizacion = $this->reglas->cotizar(
            $salida,
            $reservas[0]->getTrayecto(),
            array_map(static fn(ReservaAsiento $r) => $r->getAsiento(), $reservas),
        );
        // Antes de cobrar: lo que impediría facturar se corrige ahora, no después.
        $this->reglas->exigirReceptorFacturable($comprador->nit, $cotizacion->total);
        $this->exigirNitExistente($comprador->nit);
        $empresa = $salida->getEmpresa() ?? throw new VentaRechazada("Este salida no tiene empresa: no se puede cobrar en línea.");
        $this->reservas->extender($token);

        $pago = new PagoWeb($token, $cotizacion->total, $comprador->toArray(), $tarjeta->marcaTarjeta(), $tarjeta->ultimos4(), $empresa);
        $this->em->persist($pago);
        $this->em->flush();

        return $this->cobrar($pago, $this->solicitud($pago, $tarjeta, $direccion, $urlRetorno, $navegador));
    }

    /** Empresa que cobra el carrito (para la huella del dispositivo). */
    public function empresaDelCarrito(Uuid $token): ?int
    {
        $reservas = $this->reservas->vigentes($token);

        return $reservas !== [] ? $reservas[0]->getSalida()->getEmpresa()?->getId() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function cobrar(PagoWeb $pago, SolicitudPago $solicitud, ?Continuacion $continuacion = null): array
    {
        try {
            $resultado = $this->pasarela->cobrar($solicitud, $continuacion);
        } catch (PagoIncierto $e) {
            $pago->registrar(EstadoPagoWeb::RECHAZADO, mensaje: "Sin respuesta del banco al cobrar: revisar si se cobró. " . $e->getMessage());
            $this->em->flush();
            throw new VentaRechazada(
                "No recibimos la respuesta de su banco. Antes de intentar de nuevo, revise si su tarjeta tiene el cargo: si lo tiene, comuníquese con nosotros y se lo devolveremos.",
                "pago_incierto",
                502,
            );
        } catch (\Throwable $e) {
            $this->logger->error("Pasarela sin respuesta para el carrito {token}: {error}", ["token" => (string) $pago->getToken(), "error" => $e->getMessage(), "exception" => $e]);
            $pago->registrar(EstadoPagoWeb::RECHAZADO, mensaje: "Sin respuesta de la pasarela: " . $e->getMessage());
            $this->em->flush();
            throw new VentaRechazada("No fue posible comunicarse con el banco. No se realizó ningún cobro; intente de nuevo.", "pasarela", 502);
        }

        return $this->procesar($pago, $resultado);
    }

    private function solicitud(PagoWeb $pago, Tarjeta $tarjeta, DireccionFacturacion $direccion, string $urlRetorno, Navegador $navegador): SolicitudPago
    {
        $comprador = Comprador::desdeArray($pago->getComprador());

        return new SolicitudPago(
            referencia: $pago->getToken()->toRfc4122(),
            empresaId: (int) $pago->getEmpresa()?->getId(),
            monto: $pago->getMonto(),
            tarjeta: $tarjeta,
            direccion: $direccion,
            nombre: $comprador->nombre,
            apellido: $comprador->apellido,
            correo: $comprador->email,
            telefono: $comprador->telefono,
            descripcion: "Boletos Transportes Fuente del Norte",
            urlRetorno: $urlRetorno,
            navegador: $navegador,
        );
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
     * @return array<string, mixed>
     */
    private function procesar(PagoWeb $pago, ResultadoPago $resultado): array
    {
        switch ($resultado->estado) {
            case ResultadoPago::DISPOSITIVO:
            case ResultadoPago::AUTENTICACION:
                $pago->esperarNavegador($resultado->referenciaPasarela, $resultado->estadoPasarela);
                $this->em->flush();

                return $resultado->paraNavegador();

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
            $motivo = $e instanceof VentaRechazada ? $e->getMessage() : "error al registrar la venta";
            try {
                $this->pasarela->reembolsar((string) $pago->getReferenciaPasarela(), $pago->getMonto(), (int) $pago->getEmpresa()?->getId());
                $pago->registrar(EstadoPagoWeb::REEMBOLSADO, mensaje: $motivo);
            } catch (\Throwable $r) {
                $this->logger->critical("Compra web {token}: cobrada sin venta y sin reembolso automático ({error}): devolverla a mano.", [
                    "token" => (string) $pago->getToken(),
                    "error" => $r->getMessage(),
                ]);
                $pago->registrar(EstadoPagoWeb::REEMBOLSO_PENDIENTE, mensaje: "{$motivo}; reembolso automático fallido: " . $r->getMessage());
            }
            $this->em->flush();
            $this->logger->error("Compra web {token} reembolsada: {motivo}", [
                "token" => (string) $pago->getToken(),
                "motivo" => $e->getMessage(),
                "exception" => $e,
            ]);
            throw new VentaRechazada(
                $pago->getEstado() === EstadoPagoWeb::REEMBOLSADO
                    ? "No pudimos confirmar sus asientos ({$motivo}). Se reembolsó el cobro a su tarjeta."
                    : "No pudimos confirmar sus asientos ({$motivo}). Le devolveremos el cobro; si no lo ve en unos días, comuníquese con nosotros.",
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

        $this->publicador->cambio((int) $venta->getAsientos()->first()->getSalida()->getId());
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
        $salida = $this->em->find(Salida::class, $reservas[0]->getSalida()->getId(), LockMode::PESSIMISTIC_WRITE);
        $trayecto = $reservas[0]->getTrayecto();
        $asientos = array_map(static fn(ReservaAsiento $r) => $r->getAsiento(), $reservas);
        // Las reservas propias no estorban (aunque hayan vencido durante el pago).
        $this->reglas->exigirDisponibles($salida, $this->reglas->tramo($salida, $trayecto), $asientos, $token->toRfc4122());
        $cotizacion = $this->reglas->cotizar($salida, $trayecto, $asientos);
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
                ->setSalida($salida)
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
