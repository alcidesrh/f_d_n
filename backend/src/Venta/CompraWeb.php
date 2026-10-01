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
use App\Venta\EnLinea\AjustesPagina;
use App\Venta\EnLinea\Recargo;
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
 * Compra en la página web (ADR-021, ADR-023): cobra el carrito con tarjeta
 * (con los pasos de 3-D Secure que pida la pasarela) en un solo cobro, con el
 * comercio de la empresa de la ida. Registra una venta por salida como si
 * fuera de taquilla (canal `web`, sin usuario): en ida y vuelta, dos ventas,
 * cada una facturada por la empresa de su salida. Envía los boletos en PDF.
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
        private readonly AjustesPagina $ajustes,
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
        $previas = $this->ventas($token);
        if ($previas !== []) {
            return ["estado" => "completado", "ventas" => $previas];
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

        $viajes = $this->reservas->viajes($token);
        if ($viajes === []) {
            throw new VentaRechazada("Su selección de asientos venció. Elija sus asientos de nuevo.", "carrito_vencido", 410);
        }
        $recargo = $this->ajustes->recargo();
        $total = null;
        foreach ($viajes as $reservas) {
            $salida = $reservas[0]->getSalida();
            $this->reglas->exigirVendibleEnLinea($salida);
            $salida->getEmpresa() ?? throw new VentaRechazada("Esta salida no tiene empresa: no se puede cobrar en línea.");
            $cotizacion = $this->reglas->cotizarEnLinea(
                $salida,
                $reservas[0]->getTrayecto(),
                array_map(static fn(ReservaAsiento $r) => $r->getAsiento(), $reservas),
                $recargo,
            );
            // Antes de cobrar: lo que impediría facturar se corrige ahora, no después (una factura por viaje).
            $this->reglas->exigirReceptorFacturable($comprador->nit, $cotizacion->total);
            $total = $total === null ? $cotizacion->total : $total->add($cotizacion->total);
        }
        $this->exigirNitExistente($comprador->nit);
        $this->reservas->extender($token);

        // Cobra el comercio de la empresa de la ida.
        $pago = new PagoWeb($token, $total, $comprador->toArray(), $tarjeta->marcaTarjeta(), $tarjeta->ultimos4(), $viajes[0][0]->getSalida()->getEmpresa(), $recargo->porciento, count($viajes));
        $this->em->persist($pago);
        $this->em->flush();

        return $this->cobrar($pago, $this->solicitud($pago, $tarjeta, $direccion, $urlRetorno, $navegador));
    }

    /** Empresa que cobra el carrito (para la huella del dispositivo). */
    public function empresaDelCarrito(Uuid $token): ?int
    {
        $viajes = $this->reservas->viajes($token);

        return $viajes !== [] ? $viajes[0][0]->getSalida()->getEmpresa()?->getId() : null;
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

    /**
     * Ventas de un carrito pagado: la ida y, si hubo, el regreso.
     *
     * @return list<BoletoVenta>
     */
    public function ventas(Uuid $token): array
    {
        $ventas = $this->em->getRepository(BoletoVenta::class)->findBy(
            ["tokenPublico" => [$token, BoletoVenta::tokenRegreso($token)]],
            ["id" => "ASC"],
        );

        return array_values($ventas);
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

                return ["estado" => "completado", "ventas" => $this->completar($pago)];

            default:
                $pago->registrar(EstadoPagoWeb::RECHAZADO, $resultado->referenciaPasarela, mensaje: $resultado->mensaje);
                $this->em->flush();
                throw new VentaRechazada($resultado->mensaje ?? "El banco rechazó el pago.", "pago_rechazado", 402);
        }
    }

    /**
     * Cobrado: registrar las ventas (todas o ninguna), certificar y enviar los boletos.
     *
     * @return list<BoletoVenta>
     */
    private function completar(PagoWeb $pago): array
    {
        $pagoId = $pago->getId();
        try {
            /** @var list<BoletoVenta> $ventas */
            $ventas = $this->transaccion->ejecutar(fn() => $this->registrar($this->em->find(PagoWeb::class, $pagoId)));
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

        foreach ($ventas as $venta) {
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
        }

        // Un solo correo con los boletos de todos los viajes.
        $ids = array_map(static fn(BoletoVenta $v) => (int) $v->getId(), $ventas);
        $this->bus->dispatch(new EnviarBoletoPorCorreo($ids[0], array_slice($ids, 1)));

        return $ventas;
    }

    /**
     * Una venta por viaje del carrito, con el recargo fijado al empezar el
     * pago. Si algún asiento se perdió o el precio cambió, no se registra
     * ninguna (y `completar` reembolsa).
     *
     * @return list<BoletoVenta>
     */
    private function registrar(PagoWeb $pago): array
    {
        $token = $pago->getToken();
        // También las vencidas durante el pago: siguen siendo de este carrito.
        $viajes = Reservas::agrupar($this->em->getRepository(ReservaAsiento::class)->findBy(["token" => $token]));
        if ($viajes === [] || count($viajes) !== $pago->getViajes()) {
            throw new VentaRechazada("la selección de asientos ya no existe");
        }
        $ids = array_map(static fn(array $r) => (int) $r[0]->getSalida()->getId(), $viajes);
        sort($ids);
        foreach ($ids as $id) {
            $this->em->find(Salida::class, $id, LockMode::PESSIMISTIC_WRITE);
        }

        $recargo = Recargo::de($pago->getRecargoPorciento());
        $cliente = $this->cliente(Comprador::desdeArray($pago->getComprador()));
        $ventas = [];
        $total = null;
        foreach ($viajes as $i => $reservas) {
            $salida = $reservas[0]->getSalida();
            $trayecto = $reservas[0]->getTrayecto();
            $asientos = array_map(static fn(ReservaAsiento $r) => $r->getAsiento(), $reservas);
            // Las reservas propias no estorban (aunque hayan vencido durante el pago).
            $this->reglas->exigirDisponibles($salida, $this->reglas->tramo($salida, $trayecto), $asientos, $token->toRfc4122());
            $cotizacion = $this->reglas->cotizarEnLinea($salida, $trayecto, $asientos, $recargo);
            $total = $total === null ? $cotizacion->total : $total->add($cotizacion->total);

            $venta = (new BoletoVenta())
                ->setTokenPublico($i === 0 ? $token : BoletoVenta::tokenRegreso($token))
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
            $ventas[] = $venta;
        }
        if ($total === null || !$total->equals($pago->getMonto())) {
            throw new VentaRechazada("la tarifa cambió durante el pago");
        }
        foreach ($viajes as $reservas) {
            foreach ($reservas as $r) {
                $this->em->remove($r);
            }
        }
        $pago->completar($ventas[0], $ventas[1] ?? null);

        return $ventas;
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
