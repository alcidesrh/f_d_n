<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BoletoVenta;
use App\Entity\Enum\EstadoSalida;
use App\Entity\MensajeContacto;
use App\Entity\Nacion;
use App\Entity\Salida;
use App\Entity\ReservaAsiento;
use App\Entity\TipoDocumento;
use App\Venta\Boleto\BoletoPdf;
use App\Venta\Boleto\Comprobantes;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\CompraWeb;
use App\Venta\Comprador;
use App\Venta\ConsultaVenta;
use App\Venta\HorasSalida;
use App\Venta\EnLinea\AjustesPagina;
use App\Venta\EnLinea\SolicitudCarrito;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Pago\PasarelaPago;
use App\Venta\Pago\DireccionFacturacion;
use App\Venta\Pago\Navegador;
use App\Venta\Pago\PasarelaActiva;
use App\Venta\Pago\Tarjeta;
use App\Venta\PublicadorOcupacion;
use App\Venta\ReglasVenta;
use App\Venta\Reservas;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * API pública de la página web (ADR-021), sin sesión: consulta de horarios,
 * carrito de asientos (reservas) y pago. El carrito se identifica por su
 * token (UUID aleatorio que solo conoce el navegador que lo creó).
 *
 * No expone datos de clientes: la ocupación solo dice qué asientos están
 * libres. Limitado por IP (`framework.rate_limiter`).
 */
#[AsController]
#[Route("/api/publico", name: "api_publico_")]
final class PublicoController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConsultaVenta $consulta,
        private readonly ReglasVenta $reglas,
        private readonly Reservas $reservas,
        private readonly CompraWeb $compras,
        private readonly PasarelaPago $pasarela,
        private readonly ClockInterface $reloj,
        private readonly Comprobantes $comprobantes,
        private readonly HorasSalida $horas,
        private readonly RateLimiterFactoryInterface $publicoLimiter,
        private readonly RateLimiterFactoryInterface $publicoPagoLimiter,
        private readonly RateLimiterFactoryInterface $publicoContactoLimiter,
        private readonly AjustesPagina $ajustes,
    ) {}

    #[Route("/estaciones", name: "estaciones", methods: ["GET"])]
    public function estaciones(Request $request): JsonResponse
    {
        return $this->limitado($request, fn() => $this->consulta->estacionesEnLinea());
    }

    /** Estaciones con dirección y departamento (página de estaciones). */
    #[Route("/estaciones/directorio", name: "estaciones_directorio", methods: ["GET"])]
    public function directorio(Request $request): JsonResponse
    {
        return $this->limitado($request, fn() => $this->consulta->directorioEstaciones());
    }

    #[Route("/destinos", name: "destinos", methods: ["GET"])]
    public function destinos(Request $request): JsonResponse
    {
        return $this->limitado($request, fn() => $this->consulta->destinosDesde($request->query->getInt("origen")));
    }

    /** Documentos y naciones para el formulario del comprador. */
    #[Route("/catalogos", name: "catalogos", methods: ["GET"])]
    public function catalogos(Request $request): JsonResponse
    {
        return $this->limitado($request, fn() => [
            "tiposDocumento" => array_map(static fn(TipoDocumento $t) => ["id" => $t->getId(), "nombre" => $t->getNombre()], $this->em->getRepository(TipoDocumento::class)->findBy(["activo" => true], ["nombre" => "ASC"])),
            "naciones" => array_map(static fn(Nacion $n) => ["id" => $n->getId(), "nombre" => $n->getNombre()], $this->em->getRepository(Nacion::class)->findBy([], ["nombre" => "ASC"])),
            "cierreMinutos" => $this->ajustes->actual()->getCierreMinutos(),
            "reservaMinutos" => Reservas::DURACION_MINUTOS,
            "maxAsientos" => SolicitudCarrito::MAX_ASIENTOS,
            "ventaEnLinea" => $this->ajustes->actual()->getVentaEnLinea(),
        ]);
    }

    /** `?origen&destino&fecha=AAAA-MM-DD` */
    #[Route("/salidas", name: "salidas", methods: ["GET"])]
    public function salidas(Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($request) {
            $dia = \DateTimeImmutable::createFromFormat("!Y-m-d", (string) $request->query->get("fecha"));
            if ($dia === false || $dia < $this->reloj->now()->setTime(0, 0)) {
                throw new VentaRechazada("Elija una fecha de hoy en adelante.");
            }

            return $this->consulta->salidasEnLinea($dia, $request->query->getInt("origen"), $request->query->getInt("destino"));
        });
    }

    /** `?trayecto={id}&carrito={token}`: croquis y asientos libres (los del carrito salen como propios). */
    #[Route("/salidas/{id<\d+>}", name: "salida", methods: ["GET"])]
    public function salida(Salida $salida, Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($salida, $request) {
            if ($salida->getEstado() !== EstadoSalida::PROGRAMADA) {
                throw new VentaRechazada("Este salida ya no está a la venta.", "no_encontrado", 404);
            }
            $trayecto = $this->reglas->trayecto($salida, $request->query->getInt("trayecto") ?: null);
            $detalle = $this->consulta->detalle($salida);
            $clases = array_values(array_unique(array_map(static fn(array $e) => $e["clase"] ?? null, array_filter($detalle["croquis"], static fn(array $e) => $e["tipo"] === "asiento"))));
            $tarifas = $this->precios($salida, $trayecto, $clases);

            return [
                "id" => $salida->getId(),
                "salida" => $detalle["salida"],
                "empresa" => $detalle["empresa"]["nombre"] ?? null,
                "bus" => $detalle["bus"]["gama"] ?? null,
                "trayecto" => [
                    "id" => $trayecto->getId(),
                    "origen" => $trayecto->getOrigen()->getNombre(),
                    "destino" => $trayecto->getDestino()->getNombre(),
                ],
                "paradas" => $detalle["paradas"],
                "croquis" => array_map(static fn(array $e) => array_diff_key($e, ["conBoletos" => true]), $detalle["croquis"]),
                "precios" => $tarifas,
                "ocupacion" => array_map(
                    static fn(array $o) => ["asiento" => $o["asiento"], "estado" => $o["estado"]],
                    $this->consulta->ocupacion($salida, $this->reglas->tramo($salida, $trayecto), $this->token($request->query->get("carrito"))?->toRfc4122(), false),
                ),
                "cierre" => $detalle["cierreEnLinea"],
                "topico" => PublicadorOcupacion::topico((int) $salida->getId()),
            ];
        });
    }

    /**
     * `{ token?, viajes: [{ salida, trayecto?, asientos: [ids] }, ...] }` → carrito.
     *
     * "Pagar asientos" (ADR-023): aparta todos los asientos de la ida y del
     * regreso, o ninguno. Si otro los tomó: 409 `asientos_no_disponibles`
     * con `viajes: [{ viaje, salida, asientos, numeros }]`. Reemplaza lo que
     * el carrito tuviera (crea uno si no hay token).
     */
    #[Route("/carritos", name: "carrito_reservar", methods: ["POST"])]
    public function reservar(Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($request) {
            $datos = $request->toArray();
            $token = $this->reservas->reservar(
                $this->token($datos["token"] ?? null),
                SolicitudCarrito::desdeArray(is_array($datos["viajes"] ?? null) ? $datos["viajes"] : []),
            );

            return $this->carrito($token);
        });
    }

    #[Route("/carritos/{token}", name: "carrito", methods: ["GET"])]
    public function verCarrito(string $token, Request $request): JsonResponse
    {
        return $this->limitado($request, fn() => $this->carrito($this->tokenObligatorio($token)));
    }

    #[Route("/carritos/{token}", name: "carrito_vaciar", methods: ["DELETE"])]
    public function vaciar(string $token, Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($token) {
            $this->reservas->vaciar($this->tokenObligatorio($token));

            return ["ok" => true];
        });
    }

    /**
     * `{ comprador: {...}, tarjeta: { numero, expira: "MM/AA", cvv, titular },
     * facturacion: { pais, region, ciudad, direccion, codigoPostal },
     * navegador: { anchoPantalla, altoPantalla, profundidadColor, diferenciaHoraria, idioma },
     * continuar?: {...} }`.
     *
     * Responde `{ estado: "completado", compra }` o el paso que debe hacer el
     * navegador (3-D Secure), tras el cual repite la petición con `continuar`
     * (lo que recibió del banco):
     * - `{ estado: "dispositivo", url, campos, origenes }`: POST oculto de
     *   `campos` a `url` en un iframe; espera un `postMessage` de `origenes`.
     * - `{ estado: "autenticacion", url, campos, ancho, alto }`: POST de
     *   `campos` a `url` en un iframe visible; el banco vuelve a
     *   `/api/publico/pagos/retorno`, que avisa a la página por `postMessage`.
     *
     * Rechazos: 402 con el motivo del banco.
     */
    #[Route("/carritos/{token}/pago", name: "carrito_pagar", methods: ["POST"])]
    public function pagar(string $token, Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($token, $request) {
            $uuid = $this->tokenObligatorio($token);
            $datos = $request->toArray();
            $comprador = Comprador::desdeArray((array) ($datos["comprador"] ?? []));
            $tarjeta = Tarjeta::desdeArray((array) ($datos["tarjeta"] ?? []), $this->reloj->now());
            $direccion = DireccionFacturacion::desdeArray((array) ($datos["facturacion"] ?? []));
            $navegador = Navegador::de(
                $request->getClientIp(),
                $request->headers->get("User-Agent"),
                $request->headers->get("Accept"),
                $request->headers->get("Accept-Language"),
                (array) ($datos["navegador"] ?? []),
            );
            $continuar = isset($datos["continuar"]) ? self::camposNavegador((array) $datos["continuar"]) : null;
            $retorno = $this->generateUrl("api_publico_pago_retorno", [], UrlGeneratorInterface::ABSOLUTE_URL);

            $resultado = $this->compras->pagar($uuid, $comprador, $tarjeta, $direccion, $retorno, $navegador, $continuar);
            if ($resultado["estado"] === "completado") {
                return ["estado" => "completado", "compras" => $this->comprobantes($resultado["ventas"])];
            }

            return $resultado;
        }, $this->publicoPagoLimiter);
    }

    /** Script de huella del dispositivo que pide el antifraude del banco (o `{ script: null }`). */
    #[Route("/carritos/{token}/huella", name: "carrito_huella", methods: ["GET"])]
    public function huella(string $token, Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($token) {
            $uuid = $this->tokenObligatorio($token);
            $empresa = $this->compras->empresaDelCarrito($uuid);
            try {
                $huella = $empresa !== null ? $this->pasarela->huella($empresa, $uuid->toRfc4122()) : null;
            } catch (\RuntimeException) {
                $huella = null;
            }

            return ["script" => $huella["script"] ?? null];
        });
    }

    /**
     * Vuelta del banco tras el desafío 3-D Secure, dentro del iframe de la
     * página: avisa a la página (`postMessage` al mismo origen) con lo que
     * envió el banco, y la página continúa el pago.
     */
    #[Route("/pagos/retorno", name: "pago_retorno", methods: ["GET", "POST"])]
    public function retorno(Request $request): Response
    {
        $datos = self::camposNavegador([...$request->query->all(), ...$request->request->all()]);
        $mensaje = json_encode(["tipo" => "fdn-3ds", "datos" => $datos], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $origen = json_encode($request->getSchemeAndHttpHost(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

        return new Response(<<<HTML
            <!doctype html><html lang="es"><meta charset="utf-8"><title>Verificando…</title>
            <body style="font-family:system-ui;text-align:center;padding:2rem">Verificando con su banco…
            <script>(window.parent !== window ? window.parent : window.opener)?.postMessage({$mensaje}, {$origen});</script>
            </body></html>
            HTML, 200, ["Cache-Control" => "no-store"]);
    }

    /** Simulador del banco (ACS 3-D Secure). Solo existe con la pasarela simulada. */
    #[Route("/pagos/simulador-3ds", name: "pago_simulador", methods: ["GET", "POST"])]
    public function simulador3ds(Request $request): Response
    {
        if (!$this->pasarela instanceof PasarelaActiva || !$this->pasarela->esSimulada()) {
            throw $this->createNotFoundException();
        }
        $campo = static fn(string $k) => htmlspecialchars((string) ($request->request->get($k) ?? $request->query->get($k)), ENT_QUOTES);
        $referencia = $campo("referencia");
        // El retorno es siempre el propio: el simulador no redirige a otros sitios.
        $retorno = htmlspecialchars($this->generateUrl("api_publico_pago_retorno", [], UrlGeneratorInterface::ABSOLUTE_URL), ENT_QUOTES);

        return new Response(<<<HTML
            <!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Banco simulado · 3-D Secure</title>
            <body style="font-family:system-ui;max-width:26rem;margin:1.5rem auto;padding:0 1rem">
            <h1 style="font-size:1.1rem">Banco simulado — verificación 3-D Secure</h1>
            <p>Referencia <code>{$referencia}</code>. Elija el resultado de la autenticación:</p>
            <form method="post" action="{$retorno}" style="display:flex;gap:.5rem">
              <button name="resultado" value="Y" style="padding:.6rem 1rem">Autenticar</button>
              <button name="resultado" value="N" style="padding:.6rem 1rem">Rechazar</button>
            </form></body></html>
            HTML);
    }

    /** Resultado de la compra y comprobantes (ida y regreso), con el token del carrito. */
    #[Route("/compras/{token}", name: "compra", methods: ["GET"])]
    public function compra(string $token, Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($token) {
            $uuid = $this->tokenObligatorio($token);
            $ventas = $this->compras->ventas($uuid);
            if ($ventas !== []) {
                return ["estado" => "completado", "compras" => $this->comprobantes($ventas)];
            }
            $pago = $this->compras->ultimoPago($uuid);

            return ["estado" => $pago?->getEstado()->value ?? "sin_pago", "mensaje" => $pago?->getMensaje()];
        });
    }

    #[Route("/compras/{token}/boleto.pdf", name: "compra_pdf", methods: ["GET"])]
    public function compraPdf(string $token, Request $request, BoletoPdf $pdf): Response
    {
        $ventas = $this->compras->ventas($this->tokenObligatorio($token));
        if ($ventas === []) {
            throw $this->createNotFoundException();
        }

        return new Response($pdf->generar(...$ventas), 200, [
            "Content-Type" => "application/pdf",
            "Content-Disposition" => sprintf('%s; filename="%s"', $request->query->getBoolean("ver") ? "inline" : "attachment", BoletoPdf::nombreArchivo($ventas[0])),
            "Cache-Control" => "private, no-store",
        ]);
    }

    /**
     * Formulario de contacto: `{ nombre, email, telefono?, mensaje, idioma?, web? }`.
     * `web` es una trampa para robots (campo oculto): si viene lleno, se
     * responde bien pero no se guarda.
     */
    #[Route("/contacto", name: "contacto", methods: ["POST"])]
    public function contacto(
        Request $request,
        MailerInterface $mailer,
        LoggerInterface $logger,
        #[Autowire(env: "default:pagina_contacto_correo_defecto:PAGINA_CONTACTO_CORREO")] string $buzon,
        #[Autowire(env: "default:venta_correo_remitente_defecto:VENTA_CORREO_REMITENTE")] string $remitente,
    ): JsonResponse {
        return $this->limitado($request, function () use ($request, $mailer, $logger, $buzon, $remitente) {
            $datos = $request->toArray();
            if (trim((string) ($datos["web"] ?? "")) !== "") {
                return ["ok" => true];
            }
            $texto = static fn(string $k, int $max) => mb_substr(trim((string) ($datos[$k] ?? "")), 0, $max);
            $nombre = $texto("nombre", 120);
            $email = $texto("email", 180);
            $mensaje = $texto("mensaje", 4000);
            if ($nombre === "" || $mensaje === "" || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new VentaRechazada("Escriba su nombre, un correo válido y su mensaje.", "contacto_invalido");
            }
            $idioma = in_array($datos["idioma"] ?? null, ["es", "en", "fr", "de", "it"], true) ? $datos["idioma"] : "es";
            $m = new MensajeContacto($nombre, $email, $texto("telefono", 30) ?: null, $mensaje, $idioma, $this->reloj->now());
            $this->em->persist($m);
            $this->em->flush();
            try {
                $mailer->send((new Email())
                    ->from($remitente)
                    ->to($buzon)
                    ->replyTo($email)
                    ->subject(sprintf("Contacto desde la página: %s", $nombre))
                    ->text(sprintf("De: %s <%s>\nTeléfono: %s\nIdioma: %s\n\n%s", $nombre, $email, $m->getTelefono() ?? "—", $idioma, $mensaje)));
            } catch (\Throwable $e) {
                $logger->warning("Contacto {id}: no se envió el correo al buzón ({error}); queda en el dashboard.", ["id" => $m->getId(), "error" => $e->getMessage()]);
            }

            return ["ok" => true];
        }, $this->publicoContactoLimiter);
    }

    /**
     * Campos que el navegador reenvía del banco: solo texto corto.
     *
     * @param array<mixed> $datos
     *
     * @return array<string, string>
     */
    private static function camposNavegador(array $datos): array
    {
        $limpios = [];
        foreach (array_slice($datos, 0, 20, true) as $k => $v) {
            if (is_string($k) && strlen($k) <= 64 && is_scalar($v)) {
                $limpios[$k] = mb_substr((string) $v, 0, 4000);
            }
        }

        return $limpios;
    }

    /**
     * @param list<BoletoVenta> $ventas
     *
     * @return list<array<string, mixed>>
     */
    private function comprobantes(array $ventas): array
    {
        return array_map(fn(BoletoVenta $v) => $this->comprobantes->de($v), $ventas);
    }

    /**
     * Carrito: un viaje (o dos, ida y regreso) con sus asientos y precios.
     *
     * @return array<string, mixed>
     */
    private function carrito(Uuid $token): array
    {
        $viajes = $this->reservas->viajes($token);
        if ($viajes === []) {
            return ["token" => $token->toRfc4122(), "viajes" => [], "total" => null, "expira" => null];
        }
        $total = null;
        $expira = null;
        $lista = [];
        foreach ($viajes as $reservas) {
            $primera = $reservas[0];
            $cotizacion = $this->reglas->cotizarEnLinea(
                $primera->getSalida(),
                $primera->getTrayecto(),
                array_map(static fn(ReservaAsiento $r) => $r->getAsiento(), $reservas),
            );
            $total = $total === null ? $cotizacion->total : $total->add($cotizacion->total);
            foreach ($reservas as $r) {
                $expira = $expira === null ? $r->getExpiraEn() : min($expira, $r->getExpiraEn());
            }
            $lista[] = [
                "salida" => [
                    "id" => $primera->getSalida()->getId(),
                    "salida" => $primera->getSalida()->getFecha()->format(DATE_ATOM),
                    "salidaOrigen" => $this->horas->salidaDesde($primera->getSalida(), (int) $primera->getTrayecto()->getOrigen()->getId())->format(DATE_ATOM),
                    "llegada" => $this->horas->enParada($primera->getSalida(), (int) $primera->getTrayecto()->getDestino()->getId())?->format(DATE_ATOM),
                    "empresa" => $primera->getSalida()->getEmpresa()?->getNombre(),
                ],
                "trayecto" => [
                    "id" => $primera->getTrayecto()->getId(),
                    "origen" => $primera->getTrayecto()->getOrigen()->getNombre(),
                    "destino" => $primera->getTrayecto()->getDestino()->getNombre(),
                ],
                ...$cotizacion->toArray(),
            ];
        }

        return [
            "token" => $token->toRfc4122(),
            "expira" => $expira?->format(DATE_ATOM),
            "viajes" => $lista,
            "total" => DatosBoleto::importe($total),
        ];
    }

    /**
     * @param list<string> $clases
     *
     * @return list<array{clase: string, precio: mixed}>
     */
    private function precios(Salida $salida, \App\Entity\Trayecto $trayecto, array $clases): array
    {
        $asientos = [];
        foreach ($salida->getBus()?->getAsientos() ?? [] as $a) {
            $asientos[$a->getClase()->value] ??= $a;
        }
        $precios = [];
        foreach (array_filter($clases) as $clase) {
            if (!isset($asientos[$clase])) {
                continue;
            }
            try {
                $c = $this->reglas->cotizarEnLinea($salida, $trayecto, [$asientos[$clase]]);
                $precios[] = ["clase" => $clase, "precio" => DatosBoleto::importe($c->total)];
            } catch (VentaRechazada) {
                // Clase sin tarifa: no se vende en línea.
            }
        }

        return $precios;
    }

    private function token(mixed $valor): ?Uuid
    {
        return is_string($valor) && Uuid::isValid($valor) ? Uuid::fromString($valor) : null;
    }

    private function tokenObligatorio(string $valor): Uuid
    {
        return $this->token($valor) ?? throw new VentaRechazada("Compra no encontrada.", "no_encontrado", 404);
    }

    private function limitado(Request $request, callable $operacion, ?RateLimiterFactoryInterface $limitador = null): JsonResponse
    {
        $limite = ($limitador ?? $this->publicoLimiter)->create((string) $request->getClientIp())->consume();
        if (!$limite->isAccepted()) {
            return $this->json(
                ["error" => "Demasiadas solicitudes. Espere un momento e intente de nuevo.", "codigo" => "limite"],
                Response::HTTP_TOO_MANY_REQUESTS,
                ["Retry-After" => max(1, $limite->getRetryAfter()->getTimestamp() - time())],
            );
        }
        try {
            return $this->json($operacion());
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }
}
