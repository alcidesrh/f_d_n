<?php

declare(strict_types=1);

namespace App\Controller;

use App\Correo\Configuracion\AjustesCorreo;
use App\Entity\BoletoVenta;
use App\Entity\PagoWeb;
use App\Venta\Boleto\BoletoPdf;
use App\Venta\EnLinea\GestionCompraWeb;
use App\Venta\EnLinea\Modificacion\CobroModificacion;
use App\Venta\EnLinea\Modificacion\LlaveCliente;
use App\Venta\EnLinea\Modificacion\MisCompras;
use App\Venta\EnLinea\Modificacion\ModificacionCompras;
use App\Venta\EnLinea\Modificacion\SolicitudModificacion;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\Facturador;
use App\Venta\Pago\DireccionFacturacion;
use App\Venta\Pago\Navegador;
use App\Venta\Pago\PasarelaPago;
use App\Venta\Pago\Tarjeta;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * «Mis compras» de la página web: el cliente ve sus compras en línea,
 * descarga la factura de las pasadas y modifica las próximas, sin usuario ni
 * contraseña. Se identifica con una llave firmada atada a su correo
 * (`LlaveCliente`), que viaja en la cabecera `X-Llave-Cliente`: la página la
 * recibe al terminar una compra, en el enlace del correo del boleto o
 * pidiéndola a su correo (`POST /acceso`).
 *
 * No hay ruta para anular ni cancelar: el cliente no puede hacerlo.
 */
#[AsController]
#[Route("/api/publico/mis-compras", name: "api_publico_mis_compras_")]
final class MisComprasController extends AbstractController
{
    public const CABECERA = "X-Llave-Cliente";

    public function __construct(
        private readonly LlaveCliente $llaves,
        private readonly MisCompras $compras,
        private readonly ModificacionCompras $modificaciones,
        private readonly ClockInterface $reloj,
        private readonly RateLimiterFactoryInterface $publicoLimiter,
        private readonly RateLimiterFactoryInterface $publicoPagoLimiter,
        private readonly RateLimiterFactoryInterface $publicoContactoLimiter,
    ) {}

    /**
     * `{ correo, idioma? }`: envía al correo un enlace de acceso si tiene
     * compras en línea. Responde igual las tenga o no (no revela quién compra).
     */
    #[Route("/acceso", name: "acceso", methods: ["POST"])]
    public function acceso(Request $request, MailerInterface $mailer, AjustesCorreo $ajustesCorreo, LoggerInterface $logger): JsonResponse
    {
        return $this->limitado($request, function () use ($request, $mailer, $ajustesCorreo, $logger) {
            $datos = $request->toArray();
            $correo = LlaveCliente::normalizar((string) ($datos["correo"] ?? ""));
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new VentaRechazada("Escriba un correo electrónico válido.", "correo_invalido");
            }
            $idioma = in_array($datos["idioma"] ?? null, ["es", "en", "fr", "de", "it"], true) ? $datos["idioma"] : "es";
            if ($this->compras->tieneCompras($correo)) {
                try {
                    $mailer->send((new TemplatedEmail())
                        ->from($ajustesCorreo->actual()->getRemitenteBoletos())
                        ->to($correo)
                        ->subject("Acceso a sus compras – Transportes Fuente del Norte")
                        ->htmlTemplate("venta/acceso_compras_correo.html.twig")
                        ->context(["url" => $this->enlace($idioma, $correo), "dias" => LlaveCliente::VIGENCIA_DIAS]));
                } catch (\Throwable $e) {
                    $logger->error("Mis compras: no se envió el enlace de acceso ({error}).", ["error" => $e->getMessage()]);
                }
            }

            return ["ok" => true];
        }, $this->publicoContactoLimiter);
    }

    /** Los viajes del cliente: próximos (el que sale antes, primero) y pasados (el más reciente, primero). */
    #[Route("", name: "lista", methods: ["GET"])]
    public function lista(Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($request) {
            $correo = $this->correo($request);

            // La llave se renueva al usarla: el navegador que entra seguido no la pierde.
            return [...$this->compras->de($correo), "llave" => $this->llaves->emitir($correo)];
        });
    }

    /** PDF del viaje: los boletos con los datos de su factura electrónica. `?ver=1` lo abre en el navegador. */
    #[Route("/viajes/{id<\d+>}/boleto.pdf", name: "pdf", methods: ["GET"])]
    public function pdf(int $id, Request $request, BoletoPdf $pdf): Response
    {
        $limite = $this->publicoLimiter->create((string) $request->getClientIp())->consume();
        if (!$limite->isAccepted()) {
            return new Response("", Response::HTTP_TOO_MANY_REQUESTS);
        }
        $venta = $this->compras->venta($this->correo($request), $id)["venta"];

        return new Response($pdf->generar($venta), 200, [
            "Content-Type" => "application/pdf",
            "Content-Disposition" => sprintf('%s; filename="%s"', $request->query->getBoolean("ver") ? "inline" : "attachment", BoletoPdf::nombreArchivo($venta)),
            "Cache-Control" => "private, no-store",
        ]);
    }

    /** `{ cambios: [{ boleto, salida, trayecto?, asiento }], nuevos: [{ salida, trayecto?, asiento }] }` → lo que costaría. */
    #[Route("/viajes/{id<\d+>}/cotizar", name: "cotizar", methods: ["POST"])]
    public function cotizar(int $id, Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($id, $request) {
            $venta = $this->compras->venta($this->correo($request), $id)["venta"];

            return $this->modificaciones->cotizar($venta, SolicitudModificacion::desdeArray($request->toArray()));
        });
    }

    /**
     * Confirma la modificación: `{ cambios, nuevos, token?, tarjeta?, facturacion?, navegador?, continuar? }`.
     * Sin costo se aplica y responde `{ estado: "completado", … }`. Con costo
     * hace falta la tarjeta (402 `requiere_pago` si no viene) y sigue los
     * pasos de 3-D Secure como el pago de un carrito: responde el paso del
     * navegador con `token`, y la página repite la petición con ese `token`
     * y `continuar`.
     */
    #[Route("/viajes/{id<\d+>}/modificar", name: "modificar", methods: ["POST"])]
    public function modificar(int $id, Request $request): JsonResponse
    {
        return $this->limitado($request, function () use ($id, $request) {
            $correo = $this->correo($request);
            ["venta" => $venta, "pago" => $pago] = $this->compras->venta($correo, $id);
            $datos = $request->toArray();
            $cobro = null;
            if (isset($datos["tarjeta"])) {
                $cobro = new CobroModificacion(
                    Tarjeta::desdeArray((array) $datos["tarjeta"], $this->reloj->now()),
                    DireccionFacturacion::desdeArray((array) ($datos["facturacion"] ?? [])),
                    Navegador::de(
                        $request->getClientIp(),
                        $request->headers->get("User-Agent"),
                        $request->headers->get("Accept"),
                        $request->headers->get("Accept-Language"),
                        (array) ($datos["navegador"] ?? []),
                    ),
                    $this->generateUrl("api_publico_pago_retorno", [], UrlGeneratorInterface::ABSOLUTE_URL),
                    isset($datos["continuar"]) ? self::camposNavegador((array) $datos["continuar"]) : null,
                );
            }
            $token = is_string($datos["token"] ?? null) && Uuid::isValid($datos["token"]) ? Uuid::fromString($datos["token"]) : null;

            return $this->modificaciones->modificar($venta, $pago, $correo, SolicitudModificacion::desdeArray($datos), $cobro, $token);
        }, $this->publicoPagoLimiter);
    }

    /** `?token=`: script de huella del dispositivo que pide el antifraude del banco (o `{ script: null }`). */
    #[Route("/viajes/{id<\d+>}/huella", name: "huella", methods: ["GET"])]
    public function huella(int $id, Request $request, PasarelaPago $pasarela): JsonResponse
    {
        return $this->limitado($request, function () use ($id, $request, $pasarela) {
            $venta = $this->compras->venta($this->correo($request), $id)["venta"];
            $token = (string) $request->query->get("token");
            $empresa = $this->modificaciones->empresaDe($venta);
            try {
                $huella = $empresa !== null && Uuid::isValid($token) ? $pasarela->huella($empresa, $token) : null;
            } catch (\RuntimeException) {
                $huella = null;
            }

            return ["script" => $huella["script"] ?? null];
        });
    }

    /**
     * `{ nit, nombreFactura? }`: cambia a quién se factura la compra. Solo
     * mientras todos sus viajes se pueden modificar. Si cambia lo que se
     * envía al certificador, anula la factura de cada viaje y emite otra.
     */
    #[Route("/compras/{id<\d+>}/facturacion", name: "facturacion", methods: ["PUT"])]
    public function facturacion(int $id, Request $request, GestionCompraWeb $gestion, Facturador $facturador): JsonResponse
    {
        return $this->limitado($request, function () use ($id, $request, $gestion, $facturador) {
            $correo = $this->correo($request);
            $viajes = array_values(array_filter(
                [...($lista = $this->compras->de($correo))["futuros"], ...$lista["pasados"]],
                static fn(array $v) => $v["compra"] === $id,
            ));
            if ($viajes === []) {
                throw new VentaRechazada("No encontramos esa compra.", "viaje_no_encontrado", 404);
            }
            foreach ($viajes as $v) {
                if (!$v["editable"]) {
                    throw new VentaRechazada($v["motivo"] ?? "Los datos de la factura solo se cambian antes del viaje.", "modificacion_cerrada", 409);
                }
            }
            ["pago" => $pago] = $this->compras->venta($correo, $viajes[0]["id"]);
            /** @var PagoWeb $pago */
            $ventas = array_values(array_filter([$pago->getBoletoVenta(), $pago->getBoletoVentaRegreso()]));
            $antes = array_map(static fn(BoletoVenta $v) => $facturador->huella($v), $ventas);

            $datos = $request->toArray();
            $gestion->editarComprador($pago, array_intersect_key($datos, array_flip(["nit", "nombreFactura"])));

            $reemitidas = [];
            foreach ($ventas as $i => $v) {
                $vigente = $v->getFactura();
                if ($vigente !== null && !$vigente->isAnulada() && $facturador->huella($v) !== $antes[$i]) {
                    $reemitidas[] = $gestion->reemitirVenta($v, "Cambio de los datos de facturación por el cliente en la página");
                }
            }

            return ["ok" => true, "reemitidas" => $reemitidas];
        }, $this->publicoPagoLimiter);
    }

    /** Enlace a «Mis compras» de la página con la llave del correo. */
    private function enlace(string $idioma, string $correo): string
    {
        return $this->generateUrl("pagina", ["ruta" => "{$idioma}/mis-compras", "llave" => $this->llaves->emitir($correo)], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function correo(Request $request): string
    {
        return $this->llaves->correo($request->headers->get(self::CABECERA));
    }

    /**
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

        return $this->json($operacion());
    }
}
