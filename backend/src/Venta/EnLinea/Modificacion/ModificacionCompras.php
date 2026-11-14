<?php

declare(strict_types=1);

namespace App\Venta\EnLinea\Modificacion;

use App\Entity\Asiento;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoPagoWeb;
use App\Entity\Enum\TransicionBoleto;
use App\Entity\ModificacionCompra;
use App\Entity\PagoWeb;
use App\Entity\ReservaAsiento;
use App\Entity\Salida;
use App\Entity\Trayecto;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\Boleto\Flujo\FlujoBoleto;
use App\Venta\Comprador;
use App\Venta\Disponibilidad;
use App\Venta\EnLinea\AjustesPagina;
use App\Venta\EnLinea\GestionCompraWeb;
use App\Venta\EnLinea\SolicitudCarrito;
use App\Venta\Excepcion\AsientosNoDisponibles;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\Facturador;
use App\Venta\HorasSalida;
use App\Venta\Mensaje\EnviarBoletoPorCorreo;
use App\Venta\Ocupacion;
use App\Venta\Pago\Continuacion;
use App\Venta\Pago\PagoIncierto;
use App\Venta\Pago\PasarelaPago;
use App\Venta\Pago\ResultadoPago;
use App\Venta\Pago\SolicitudPago;
use App\Venta\PublicadorOcupacion;
use App\Venta\ReglasBoletos;
use App\Venta\ReglasVenta;
use App\Venta\Reservas;
use App\Venta\Tramo;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Money\Money;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * El cliente modifica un viaje comprado en la página («Mis compras»): por
 * cada asiento comprado puede cambiar la hora (otra salida), la ruta (otro
 * trayecto) y el asiento (también de otra clase), y puede agregar asientos.
 * No puede quitar asientos: no anula ni cancela compras.
 *
 * - Solo antes de `PoliticaModificacion::minutosAntes` de la salida y hasta
 *   `modificacionesPorAsiento` veces el mismo asiento; el destino debe
 *   venderse en línea y ser de la misma empresa (la factura es de un emisor).
 * - Asiento por asiento: si el nuevo cuesta más se cobra la diferencia; si
 *   cuesta menos, el boleto conserva el precio pagado y no se devuelve nada.
 *   Más los asientos nuevos y la penalización de `ConfiguracionPagina`.
 * - Con algo que cobrar, los asientos de destino se apartan (como un
 *   carrito) mientras paga con tarjeta y 3-D Secure; cobrado, se aplica en
 *   una transacción con las salidas bloqueadas y, si algo falla, se
 *   reembolsa. Sin cobro se aplica al instante.
 * - El boleto cambiado pasa a `reasignado` (como en taquilla, ADR-028) y el
 *   nuevo nace en la misma venta. Si cambia lo que se envía al certificador
 *   (renglones, monto, receptor), se anula la factura y se emite otra.
 */
final class ModificacionCompras
{
    private const MOTIVO = "Modificación de la compra por el cliente en la página";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly ReglasVenta $reglas,
        private readonly AjustesPagina $ajustes,
        private readonly HorasSalida $horas,
        private readonly FlujoBoleto $flujo,
        private readonly Disponibilidad $disponibilidad,
        private readonly Reservas $reservas,
        private readonly PasarelaPago $pasarela,
        private readonly Facturador $facturador,
        private readonly GestionCompraWeb $gestion,
        private readonly PublicadorOcupacion $publicador,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * Qué costaría la modificación (sin apartar ni cobrar nada).
     *
     * @return array<string, mixed>
     *
     * @throws VentaRechazada
     */
    public function cotizar(BoletoVenta $venta, SolicitudModificacion $s): array
    {
        return $this->planArray($this->plan($venta, $s, null, verificarDisponibles: true));
    }

    /**
     * Confirma la modificación. Sin costo se aplica ya; con costo inicia el
     * cobro (o, con `$cobro->continuar`, sigue el cobro `$token` tras un paso
     * de 3-D Secure).
     *
     * @return array<string, mixed> `{ estado: "completado", ... }` o el paso del navegador con `token`
     *
     * @throws VentaRechazada
     */
    public function modificar(BoletoVenta $venta, PagoWeb $compra, string $correo, SolicitudModificacion $s, ?CobroModificacion $cobro, ?Uuid $token): array
    {
        if ($cobro?->continuar !== null) {
            return $this->continuar($venta, $compra, $token, $cobro);
        }

        $plan = $this->plan($venta, $s, null, verificarDisponibles: true);
        if (!$plan["total"]->isPositive()) {
            $registro = new ModificacionCompra(Uuid::v4(), $venta, $s->toArray(), $plan["total"], $plan["penalizacion"], LlaveCliente::normalizar($correo));

            return $this->terminar($this->aplicar((int) $venta->getId(), $s, null, null, $registro), $correo);
        }
        if ($cobro === null) {
            throw new VentaRechazada(
                sprintf("Para confirmar el cambio pague %s.", DatosBoleto::importe($plan["total"])["texto"] ?? ""),
                "requiere_pago",
                402,
                ["total" => DatosBoleto::importe($plan["total"])],
            );
        }

        if ($token === null || $this->em->getRepository(ModificacionCompra::class)->count(["token" => $token]) > 0) {
            $token = Uuid::v4();
        }
        $this->apartar($token, $plan);
        $registro = new ModificacionCompra($token, $venta, $s->toArray(), $plan["total"], $plan["penalizacion"], LlaveCliente::normalizar($correo));
        $registro->conTarjeta($cobro->tarjeta->marcaTarjeta(), $cobro->tarjeta->ultimos4());
        $this->em->persist($registro);
        $this->em->flush();

        return $this->cobrar($registro, $compra, $cobro, null);
    }

    /** Empresa que cobra la modificación: la del viaje (su comercio en la pasarela). */
    public function empresaDe(BoletoVenta $venta): ?int
    {
        $b = MisCompras::vivos($venta)[0] ?? $venta->getAsientos()->first() ?: null;

        return $b?->getSalida()?->getEmpresa()?->getId();
    }

    /**
     * Sigue un cobro tras un paso de 3-D Secure (cada paso se usa una vez).
     *
     * @return array<string, mixed>
     */
    private function continuar(BoletoVenta $venta, PagoWeb $compra, ?Uuid $token, CobroModificacion $cobro): array
    {
        $registro = $token !== null ? $this->em->getRepository(ModificacionCompra::class)->findOneBy(["token" => $token]) : null;
        if (
            $registro === null
            || $registro->getBoletoVenta()->getId() !== $venta->getId()
            || $registro->getEstado() !== EstadoPagoWeb::AUTENTICACION
            || $registro->getEstadoPasarela() === []
        ) {
            throw new VentaRechazada("Este pago ya no está en curso. Intente de nuevo.", "pago_no_en_curso", 409);
        }
        if ($registro->getUltimos4() !== $cobro->tarjeta->ultimos4() || $registro->getMarca() !== $cobro->tarjeta->marcaTarjeta()) {
            throw new VentaRechazada("La tarjeta no coincide con la del pago en curso.", "pago_tarjeta", 409);
        }
        $estado = $registro->getEstadoPasarela();
        $tomado = $this->em->createQuery("UPDATE App\\Entity\\ModificacionCompra m SET m.estadoPasarela = NULL WHERE m.id = :id AND m.estadoPasarela IS NOT NULL")
            ->setParameter("id", $registro->getId())
            ->execute();
        if ($tomado !== 1) {
            throw new VentaRechazada("Este pago ya se está procesando.", "pago_en_proceso", 409);
        }
        $this->reservas->extender($registro->getToken());

        return $this->cobrar($registro, $compra, $cobro, new Continuacion($estado, $cobro->continuar ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    private function cobrar(ModificacionCompra $registro, PagoWeb $compra, CobroModificacion $cobro, ?Continuacion $continuacion): array
    {
        $comprador = Comprador::desdeArray($compra->getComprador());
        $solicitud = new SolicitudPago(
            referencia: $registro->getToken()->toRfc4122(),
            empresaId: (int) $this->empresaDe($registro->getBoletoVenta()),
            monto: $registro->getMonto(),
            tarjeta: $cobro->tarjeta,
            direccion: $cobro->direccion,
            nombre: $comprador->nombre,
            apellido: $comprador->apellido,
            correo: $comprador->email,
            telefono: $comprador->telefono,
            descripcion: "Modificación de boletos Transportes Fuente del Norte",
            urlRetorno: $cobro->urlRetorno,
            navegador: $cobro->navegador,
        );
        try {
            $resultado = $this->pasarela->cobrar($solicitud, $continuacion);
        } catch (PagoIncierto $e) {
            $registro->registrar(EstadoPagoWeb::RECHAZADO, mensaje: "Sin respuesta del banco al cobrar: revisar si se cobró. " . $e->getMessage());
            $this->em->flush();
            $this->reservas->vaciar($registro->getToken());
            throw new VentaRechazada(
                "No recibimos la respuesta de su banco. Antes de intentar de nuevo, revise si su tarjeta tiene el cargo: si lo tiene, comuníquese con nosotros y se lo devolveremos.",
                "pago_incierto",
                502,
            );
        } catch (\Throwable $e) {
            $this->logger->error("Pasarela sin respuesta al cobrar la modificación {token}: {error}", ["token" => (string) $registro->getToken(), "error" => $e->getMessage(), "exception" => $e]);
            $registro->registrar(EstadoPagoWeb::RECHAZADO, mensaje: "Sin respuesta de la pasarela: " . $e->getMessage());
            $this->em->flush();
            $this->reservas->vaciar($registro->getToken());
            throw new VentaRechazada("No fue posible comunicarse con el banco. No se realizó ningún cobro; intente de nuevo.", "pasarela", 502);
        }

        switch ($resultado->estado) {
            case ResultadoPago::DISPOSITIVO:
            case ResultadoPago::AUTENTICACION:
                $registro->esperarNavegador($resultado->referenciaPasarela, $resultado->estadoPasarela);
                $this->em->flush();

                return [...$resultado->paraNavegador(), "token" => $registro->getToken()->toRfc4122()];

            case ResultadoPago::APROBADO:
                $registro->registrar(EstadoPagoWeb::APROBADO, $resultado->referenciaPasarela, $resultado->autorizacion);
                $this->em->flush();

                return $this->completar($registro);

            default:
                $registro->registrar(EstadoPagoWeb::RECHAZADO, $resultado->referenciaPasarela, mensaje: $resultado->mensaje);
                $this->em->flush();
                $this->reservas->vaciar($registro->getToken());
                throw new VentaRechazada($resultado->mensaje ?? "El banco rechazó el pago.", "pago_rechazado", 402);
        }
    }

    /**
     * Cobrado: aplicar lo pedido tal como se cotizó; si ya no se puede (un
     * asiento se perdió, cambió el precio), reembolsar.
     *
     * @return array<string, mixed>
     */
    private function completar(ModificacionCompra $registro): array
    {
        $registroId = (int) $registro->getId();
        $ventaId = (int) $registro->getBoletoVenta()->getId();
        $token = $registro->getToken();
        $monto = $registro->getMonto();
        $correo = $registro->getCorreo();
        try {
            $resultado = $this->aplicar($ventaId, SolicitudModificacion::desdeArray($registro->getCambios()), $token, $monto, $registro);
        } catch (\Throwable $e) {
            $registro = $this->em->find(ModificacionCompra::class, $registroId);
            $motivo = $e instanceof VentaRechazada ? $e->getMessage() : "error al aplicar el cambio";
            try {
                $this->pasarela->reembolsar((string) $registro->getReferenciaPasarela(), $registro->getMonto(), (int) $this->empresaDe($registro->getBoletoVenta()));
                $registro->registrar(EstadoPagoWeb::REEMBOLSADO, mensaje: $motivo);
            } catch (\Throwable $r) {
                $this->logger->critical("Modificación web {token}: cobrada sin aplicar y sin reembolso automático ({error}): devolverla a mano.", [
                    "token" => (string) $token,
                    "error" => $r->getMessage(),
                ]);
                $registro->registrar(EstadoPagoWeb::REEMBOLSO_PENDIENTE, mensaje: "{$motivo}; reembolso automático fallido: " . $r->getMessage());
            }
            $this->em->flush();
            $this->reservas->vaciar($token);
            $this->logger->error("Modificación web {token} reembolsada: {motivo}", ["token" => (string) $token, "motivo" => $e->getMessage(), "exception" => $e]);
            throw new VentaRechazada(
                $registro->getEstado() === EstadoPagoWeb::REEMBOLSADO
                    ? "No pudimos aplicar su cambio ({$motivo}). Se reembolsó el cobro a su tarjeta y su compra sigue como estaba."
                    : "No pudimos aplicar su cambio ({$motivo}). Le devolveremos el cobro; si no lo ve en unos días, comuníquese con nosotros.",
                "reembolsado",
                409,
            );
        }

        return $this->terminar($resultado, $correo);
    }

    /**
     * Aparta los asientos de destino mientras el cliente paga (como un
     * carrito): si otro los toma, no aparta ninguno.
     *
     * @param array<string, mixed> $plan
     */
    private function apartar(Uuid $token, array $plan): void
    {
        $destinos = [...$plan["cambios"], ...$plan["nuevos"]];
        $salidaIds = array_values(array_unique(array_map(static fn(array $d) => (int) $d["salida"]->getId(), $destinos)));
        sort($salidaIds);
        $this->transaccion->ejecutar(function () use ($token, $destinos, $salidaIds, $plan) {
            foreach ($salidaIds as $id) {
                $this->em->find(Salida::class, $id, LockMode::PESSIMISTIC_WRITE);
            }
            $this->exigirDisponibles($destinos, $plan["moviendo"], $token->toRfc4122());
            $expira = min(
                $this->reloj->now()->modify(sprintf("+%d minutes", Reservas::DURACION_MINUTOS)),
                ...array_map(fn(array $d) => $this->reglas->limiteReservas($d["salida"]), $destinos),
            );
            foreach ($destinos as $d) {
                $this->em->persist((new ReservaAsiento($token, $d["salida"], $d["asiento"], $d["trayecto"], $expira))->ocuparTramo($d["tramo"]->desde, $d["tramo"]->hasta));
            }
        });
        foreach ($salidaIds as $id) {
            $this->publicador->cambio($id);
        }
    }

    /**
     * Aplica la modificación en una transacción con las salidas (de origen y
     * de destino) bloqueadas. Con `$montoEsperado` (lo cobrado), exige que
     * cueste exactamente eso.
     *
     * @return array{venta: int, salidas: list<int>, huella: ?string, registro: int}
     */
    private function aplicar(int $ventaId, SolicitudModificacion $s, ?Uuid $token, ?Money $montoEsperado, ModificacionCompra $registro): array
    {
        $registroId = $registro->getId();

        return $this->transaccion->ejecutar(function () use ($ventaId, $s, $token, $montoEsperado, $registro, $registroId) {
            $origenes = array_map(
                static fn(BoletoAsiento $b) => (int) $b->getSalida()->getId(),
                $this->em->getRepository(BoletoAsiento::class)->findBy(["id" => array_column($s->cambios, "boleto")]),
            );
            $salidaIds = array_values(array_unique([...$origenes, ...$s->salidasDestino()]));
            sort($salidaIds);
            foreach ($salidaIds as $id) {
                $this->em->find(Salida::class, $id, LockMode::PESSIMISTIC_WRITE);
            }
            $venta = $this->em->find(BoletoVenta::class, $ventaId) ?? throw new VentaRechazada("La venta ya no existe.", "no_encontrado", 404);
            $this->em->refresh($venta);
            foreach ($venta->getAsientos() as $b) {
                $this->em->refresh($b);
            }
            $huella = $this->facturador->huella($venta);

            $plan = $this->plan($venta, $s, $token, verificarDisponibles: false);
            if ($montoEsperado !== null && !$plan["total"]->equals($montoEsperado)) {
                throw new VentaRechazada("el precio cambió mientras pagaba", "precio_cambiado", 409);
            }

            // Primero se liberan los asientos actuales: se puede pasar a uno que hoy ocupa otro boleto que también cambia.
            foreach ($plan["cambios"] as $c) {
                $this->flujo->aplicar($c["boleto"], TransicionBoleto::REASIGNAR, [
                    "haciaSalida" => $c["salida"]->getId(),
                    "haciaAsiento" => $c["asiento"]->getNumero(),
                    "motivo" => self::MOTIVO,
                    "canal" => CanalVenta::WEB->value,
                ]);
            }
            $this->em->flush();
            $this->exigirDisponibles([...$plan["cambios"], ...$plan["nuevos"]], [], $token?->toRfc4122(), estricto: true);

            $agregado = new Money(0, $venta->getTotal()->getCurrency());
            foreach ($plan["cambios"] as $c) {
                /** @var BoletoAsiento $viejo */
                $viejo = $c["boleto"];
                $nuevo = (new BoletoAsiento())
                    ->setAsiento($c["asiento"])
                    ->setTrayecto($c["trayecto"])
                    ->ocuparTramo($c["tramo"]->desde, $c["tramo"]->hasta)
                    ->setSalida($c["salida"])
                    ->setCliente($viejo->getCliente())
                    ->setPrecio($c["precio"])
                    ->setObservacion($viejo->getObservacion())
                    ->setModificaciones($viejo->getModificaciones() + 1)
                    ->setReasignadoDe($viejo);
                $venta->addAsiento($nuevo);
                $this->em->persist($nuevo);
                $agregado = $agregado->add($c["diferencia"]);
            }
            foreach ($plan["nuevos"] as $n) {
                $nuevo = (new BoletoAsiento())
                    ->setAsiento($n["asiento"])
                    ->setTrayecto($n["trayecto"])
                    ->ocuparTramo($n["tramo"]->desde, $n["tramo"]->hasta)
                    ->setSalida($n["salida"])
                    ->setCliente($venta->getCliente())
                    ->setPrecio($n["precio"]);
                $venta->addAsiento($nuevo);
                $this->em->persist($nuevo);
                $agregado = $agregado->add($n["precio"]);
            }
            $venta->setTotal($venta->getTotal()->add($agregado));
            $venta->sumarCargoModificacion($plan["penalizacion"]);

            if ($token !== null) {
                $this->em->createQuery("DELETE FROM App\\Entity\\ReservaAsiento r WHERE r.token = :token")
                    ->setParameter("token", $token, "uuid")
                    ->execute();
            }
            $registro = $registroId !== null ? $this->em->find(ModificacionCompra::class, $registroId) : $registro;
            if ($registroId === null) {
                $this->em->persist($registro);
            }
            $registro->registrar(EstadoPagoWeb::COMPLETADO);
            $this->em->flush();

            return [
                "venta" => $ventaId,
                "salidas" => $salidaIds,
                "huella" => $huella,
                "registro" => (int) $registro->getId(),
            ];
        });
    }

    /**
     * Tras el commit: avisa a los croquis, reemite la factura si cambió lo
     * que describe y envía el boleto actualizado.
     *
     * @param array{venta: int, salidas: list<int>, huella: ?string, registro: int} $r
     *
     * @return array<string, mixed>
     */
    private function terminar(array $r, string $correo): array
    {
        foreach ($r["salidas"] as $id) {
            $this->publicador->cambio($id);
        }
        $venta = $this->em->find(BoletoVenta::class, $r["venta"]);
        $registro = $this->em->find(ModificacionCompra::class, $r["registro"]);

        $factura = null;
        $vigente = $venta->getFactura();
        if ($vigente !== null && !$vigente->isAnulada() && $this->facturador->huella($venta) !== $r["huella"]) {
            // Cambió lo que se envía al certificador: se anula la factura original y se emite otra.
            $factura = $this->gestion->reemitirVenta($venta, self::MOTIVO);
            if ($factura["error"] === null) {
                $registro->marcarRefacturada();
                $this->em->flush();
            }
        }
        // Sin factura certificada (pendiente o en contingencia), la tarea de pendientes la certifica con los datos nuevos.

        $this->bus->dispatch(new EnviarBoletoPorCorreo((int) $venta->getId(), [], LlaveCliente::normalizar($correo)));

        return [
            "estado" => "completado",
            "viaje" => $venta->getId(),
            "cobrado" => DatosBoleto::importe($registro->getMonto()),
            "refacturada" => $factura !== null && $factura["error"] === null,
            "errorFactura" => $factura["error"] ?? null,
        ];
    }

    /**
     * Valida lo pedido y lo cotiza (sin escribir nada).
     *
     * @return array{
     *     cambios: list<array{boleto: BoletoAsiento, salida: Salida, trayecto: Trayecto, tramo: Tramo, asiento: Asiento, pagado: Money, nuevo: Money, precio: Money, diferencia: Money, sinReembolso: Money}>,
     *     nuevos: list<array{salida: Salida, trayecto: Trayecto, tramo: Tramo, asiento: Asiento, precio: Money}>,
     *     moviendo: array<string, true>,
     *     penalizacion: Money,
     *     total: Money,
     * }
     *
     * @throws VentaRechazada
     */
    private function plan(BoletoVenta $venta, SolicitudModificacion $s, ?Uuid $token, bool $verificarDisponibles): array
    {
        if ($venta->getCanal() !== CanalVenta::WEB || $venta->getEstado() !== EstadoBoletoVenta::CONFIRMADA) {
            throw new VentaRechazada("Este viaje no se puede modificar en línea.", "modificacion_cerrada", 409);
        }
        $ahora = $this->reloj->now();
        $politica = $this->ajustes->politicaModificacion();
        $vivos = MisCompras::vivos($venta);
        if ($vivos === []) {
            throw new VentaRechazada("Este viaje está anulado.", "modificacion_cerrada", 409);
        }
        // El viaje tiene que estar abierto (también para agregar asientos).
        $politica->exigirAbierta($this->subida($vivos[0]), $ahora);
        $empresa = $vivos[0]->getSalida()->getEmpresa()?->getId();

        /** @var array<int, BoletoAsiento> $porId */
        $porId = [];
        foreach ($vivos as $b) {
            $porId[(int) $b->getId()] = $b;
        }
        $moneda = $venta->getTotal()->getCurrency();
        $recargo = $this->ajustes->recargo();

        $cambios = [];
        $moviendo = [];
        foreach ($s->cambios as $c) {
            $b = $porId[$c["boleto"]] ?? throw new VentaRechazada("Ese asiento no es de este viaje.", "viaje_no_encontrado", 404);
            $this->flujo->exigir($b, TransicionBoleto::REASIGNAR);
            $politica->exigirAbierta($this->subida($b), $ahora);
            $politica->exigirRestantes($b->getModificaciones(), (int) $b->getAsiento()->getNumero());
            $destino = $this->salida($c["salida"]);
            ReglasBoletos::exigirEmpresaCompatible($b->getSalida(), $destino);
            $this->reglas->exigirVendibleEnLinea($destino);
            $trayecto = $this->reglas->trayecto($destino, $c["trayecto"] ?? (int) $b->getTrayecto()->getId());
            $tramo = $this->reglas->tramo($destino, $trayecto);
            $asiento = $this->reglas->asientos($destino, [$c["asiento"]])[0];
            if ($b->getSalida()->getId() === $destino->getId() && $b->getTrayecto()->getId() === $trayecto->getId() && $b->getAsiento()->getId() === $asiento->getId()) {
                throw new VentaRechazada(sprintf("El asiento %s ya es ese.", $asiento->getNumero()), "sin_cambio");
            }
            $moviendo[$b->getSalida()->getId() . "-" . $b->getAsiento()->getId()] = true;
            $cambios[] = ["boleto" => $b, "salida" => $destino, "trayecto" => $trayecto, "tramo" => $tramo, "asiento" => $asiento];
        }
        $nuevos = [];
        foreach ($s->nuevos as $n) {
            $destino = $this->salida($n["salida"]);
            if ($destino->getEmpresa()?->getId() !== $empresa) {
                throw new VentaRechazada("La salida elegida es de otra empresa: agregue asientos de la misma empresa de su viaje o haga una compra nueva.", "empresa_distinta");
            }
            $this->reglas->exigirVendibleEnLinea($destino);
            $trayecto = $this->reglas->trayecto($destino, $n["trayecto"] ?? (int) $vivos[0]->getTrayecto()->getId());
            $nuevos[] = [
                "salida" => $destino,
                "trayecto" => $trayecto,
                "tramo" => $this->reglas->tramo($destino, $trayecto),
                "asiento" => $this->reglas->asientos($destino, [$n["asiento"]])[0],
            ];
        }
        if (count($vivos) + count($nuevos) > SolicitudCarrito::MAX_ASIENTOS) {
            throw new VentaRechazada(sprintf("Un viaje lleva a lo sumo %d asientos.", SolicitudCarrito::MAX_ASIENTOS), "carrito_lleno");
        }

        // Precios en línea de hoy (tarifa + recargo), una cotización por salida y trayecto.
        $precios = $this->precios([...$cambios, ...$nuevos], $recargo);
        $diferencias = [];
        foreach ($cambios as $i => $c) {
            $pagado = $c["boleto"]->getPrecio() ?? new Money(0, $moneda);
            $nuevo = $precios[$this->clave($c)];
            $cambios[$i] = [...$c, "pagado" => $pagado, "nuevo" => $nuevo, ...ReglasModificacion::cambio($pagado, $nuevo)];
            $diferencias[] = $cambios[$i]["diferencia"];
        }
        foreach ($nuevos as $i => $n) {
            $nuevos[$i]["precio"] = $precios[$this->clave($n)];
        }
        $penalizacion = $politica->penalizacion->para(count($cambios), $moneda->getCode());
        $total = ReglasModificacion::total($diferencias, array_column($nuevos, "precio"), $penalizacion);
        $this->reglas->exigirReceptorFacturable($venta->getCliente()?->getNit(), $venta->getTotal()->add($total));

        if ($verificarDisponibles) {
            $this->exigirDisponibles([...$cambios, ...$nuevos], $moviendo, $token?->toRfc4122());
        }

        return ["cambios" => $cambios, "nuevos" => $nuevos, "moviendo" => $moviendo, "penalizacion" => $penalizacion, "total" => $total];
    }

    /**
     * @param list<array{salida: Salida, trayecto: Trayecto, asiento: Asiento}> $destinos
     *
     * @return array<string, Money> precio por `clave()`
     */
    private function precios(array $destinos, \App\Venta\EnLinea\Recargo $recargo): array
    {
        $grupos = [];
        foreach ($destinos as $d) {
            $grupos[$d["salida"]->getId() . "-" . $d["trayecto"]->getId()][] = $d;
        }
        $precios = [];
        foreach ($grupos as $grupo) {
            $asientos = array_map(static fn(array $d) => $d["asiento"], $grupo);
            $cotizacion = $this->reglas->cotizarEnLinea($grupo[0]["salida"], $grupo[0]["trayecto"], $asientos, $recargo);
            foreach ($grupo as $d) {
                $precios[$this->clave($d)] = $cotizacion->precioDe($d["asiento"]);
            }
        }

        return $precios;
    }

    /** @param array{salida: Salida, trayecto: Trayecto, asiento: Asiento} $d */
    private function clave(array $d): string
    {
        return $d["salida"]->getId() . "-" . $d["trayecto"]->getId() . "-" . $d["asiento"]->getId();
    }

    /**
     * Que los asientos de destino estén libres en su tramo. Los que hoy ocupa
     * un boleto de la venta que también cambia (`$moviendo`) cuentan como
     * libres; las reservas propias (`$token`) no estorban.
     *
     * @param list<array{salida: Salida, tramo: Tramo, asiento: Asiento}> $destinos
     * @param array<string, true>                                          $moviendo
     *
     * @throws AsientosNoDisponibles
     */
    private function exigirDisponibles(array $destinos, array $moviendo, ?string $token, bool $estricto = false): void
    {
        $grupos = [];
        foreach ($destinos as $d) {
            $grupos[$d["salida"]->getId() . "-" . $d["tramo"]->desde . "-" . $d["tramo"]->hasta][] = $d;
        }
        $ocupados = [];
        foreach ($grupos as $grupo) {
            $salida = $grupo[0]["salida"];
            if ($estricto) {
                $this->reglas->exigirDisponibles($salida, $grupo[0]["tramo"], array_map(static fn(array $d) => $d["asiento"], $grupo), $token);

                continue;
            }
            $estados = $this->disponibilidad->estados($salida, $grupo[0]["tramo"], $token);
            $ids = array_map(static fn(array $d) => (int) $d["asiento"]->getId(), $grupo);
            foreach (Ocupacion::noDisponibles($ids, $estados) as $id) {
                if (!isset($moviendo[$salida->getId() . "-" . $id])) {
                    foreach ($grupo as $d) {
                        if ($d["asiento"]->getId() === $id) {
                            $ocupados[] = (int) $d["asiento"]->getNumero();
                        }
                    }
                }
            }
        }
        if ($ocupados !== []) {
            sort($ocupados);
            throw AsientosNoDisponibles::numeros($ocupados);
        }
    }

    /**
     * @param array<string, mixed> $plan
     *
     * @return array<string, mixed>
     */
    private function planArray(array $plan): array
    {
        $destino = fn(Salida $salida, Trayecto $trayecto, Asiento $asiento) => [
            "salida" => $salida->getId(),
            "salidaOrigen" => ($subida = $this->horas->subida($salida, (int) $trayecto->getOrigen()->getId()))["hora"]->format(DATE_ATOM),
            "salidaPrecision" => $subida["precision"]->value,
            "rutaOrigen" => $salida->getRuta()?->getOrigen()?->getNombre(),
            "trayecto" => $trayecto->getId(),
            "origen" => $trayecto->getOrigen()->getNombre(),
            "destino" => $trayecto->getDestino()->getNombre(),
            "asiento" => $asiento->getNumero(),
            "asientoId" => $asiento->getId(),
            "clase" => $asiento->getClase()->value,
        ];
        $suma = static fn(array $montos, Money $cero) => array_reduce($montos, static fn(Money $t, Money $m) => $t->add($m), $cero);
        $cero = new Money(0, $plan["total"]->getCurrency());

        return [
            "cambios" => array_map(static fn(array $c) => [
                "boleto" => $c["boleto"]->getId(),
                "desde" => $destino($c["boleto"]->getSalida(), $c["boleto"]->getTrayecto(), $c["boleto"]->getAsiento()),
                "hacia" => $destino($c["salida"], $c["trayecto"], $c["asiento"]),
                "pagado" => DatosBoleto::importe($c["pagado"]),
                "nuevo" => DatosBoleto::importe($c["nuevo"]),
                "diferencia" => DatosBoleto::importe($c["diferencia"]),
                "sinReembolso" => $c["sinReembolso"]->isPositive() ? DatosBoleto::importe($c["sinReembolso"]) : null,
            ], $plan["cambios"]),
            "nuevos" => array_map(static fn(array $n) => [
                ...$destino($n["salida"], $n["trayecto"], $n["asiento"]),
                "precio" => DatosBoleto::importe($n["precio"]),
            ], $plan["nuevos"]),
            "diferencias" => DatosBoleto::importe($suma(array_column($plan["cambios"], "diferencia"), $cero)),
            "asientosNuevos" => DatosBoleto::importe($suma(array_column($plan["nuevos"], "precio"), $cero)),
            "sinReembolso" => DatosBoleto::importe($suma(array_column($plan["cambios"], "sinReembolso"), $cero)),
            "penalizacion" => DatosBoleto::importe($plan["penalizacion"]),
            "total" => DatosBoleto::importe($plan["total"]),
            "requierePago" => $plan["total"]->isPositive(),
        ];
    }

    private function salida(int $id): Salida
    {
        return $this->em->find(Salida::class, $id) ?? throw new VentaRechazada("La salida elegida ya no existe.", "no_encontrado", 404);
    }

    private function subida(BoletoAsiento $b): \DateTimeImmutable
    {
        return $this->horas->subida($b->getSalida(), (int) $b->getTrayecto()->getOrigen()->getId())["hora"];
    }
}
