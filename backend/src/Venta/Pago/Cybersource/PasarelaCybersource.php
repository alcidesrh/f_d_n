<?php

declare(strict_types=1);

namespace App\Venta\Pago\Cybersource;

use App\Venta\Pago\Continuacion;
use App\Venta\Pago\PagoIncierto;
use App\Venta\Pago\PasarelaPago;
use App\Venta\Pago\ResultadoPago;
use App\Venta\Pago\SolicitudPago;
use Money\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Cybersource (Visa) con Payer Authentication (3-D Secure 2, Cardinal),
 * el mismo flujo que usaba la página anterior:
 *
 * 1. `authentication-setups` → el navegador envía el JWT a la URL de
 *    recolección de datos del dispositivo (DDC) en un iframe oculto.
 * 2. Pago con `CONSUMER_AUTHENTICATION` + captura: si el banco no pide
 *    desafío, queda cobrado; si lo pide (`PENDING_AUTHENTICATION`), el
 *    navegador abre el ACS (`stepUpUrl`) en un iframe.
 * 3. Pago con `VALIDATE_CONSUMER_AUTHENTICATION` + captura.
 *
 * Un cobro autorizado sin evidencia de autenticación 3-D Secure se anula
 * (el comercio no quiere cargos sin traslado de responsabilidad).
 * Cada empresa cobra con su comercio (`CredencialPago`).
 */
final class PasarelaCybersource implements PasarelaPago
{
    /**
     * Orígenes de los avisos (`postMessage`) del DDC de Cardinal, además del
     * de la URL que devuelve el setup.
     */
    public const ORIGENES_DDC = [
        "https://centinelapi.cardinalcommerce.com",
        "https://centinelapistag.cardinalcommerce.com",
    ];

    private const RUTA_SETUP = "/risk/v1/authentication-setups";
    private const RUTA_PAGOS = "/pts/v2/payments";

    public function __construct(
        private readonly ClienteCybersource $cliente,
        private readonly CredencialesCybersource $credenciales,
        private readonly LoggerInterface $logger,
        /** Org ID de ThreatMetrix (huella del dispositivo para Decision Manager); vacío = sin huella. */
        #[Autowire(env: "default::CYBERSOURCE_ORG_ID")]
        private readonly ?string $orgId = null,
    ) {}

    public function cobrar(SolicitudPago $solicitud, ?Continuacion $continuacion = null): ResultadoPago
    {
        $comercio = $this->credenciales->de($solicitud->empresaId);

        return match ($continuacion?->estado["paso"] ?? null) {
            null => $this->iniciar($comercio, $solicitud),
            "enrolar" => $this->pagar($comercio, $solicitud, [
                "actionList" => ["CONSUMER_AUTHENTICATION"],
            ], [
                "referenceId" => $continuacion->estado["referencia"] ?? "",
                "returnUrl" => $solicitud->urlRetorno,
            ]),
            "validar" => $this->pagar($comercio, $solicitud, [
                "actionList" => ["VALIDATE_CONSUMER_AUTHENTICATION"],
            ], [
                "authenticationTransactionId" => $continuacion->estado["transaccion"] ?? "",
            ], puedePedirDesafio: false),
            default => throw new \LogicException("Paso de cobro desconocido."),
        };
    }

    public function reembolsar(string $referenciaPasarela, Money $monto, int $empresaId): void
    {
        $comercio = $this->credenciales->de($empresaId);
        if (!$this->anular($comercio, $referenciaPasarela, $monto)) {
            throw new \RuntimeException(sprintf("Cybersource no aceptó anular ni reembolsar el pago %s.", $referenciaPasarela));
        }
    }

    public function huella(int $empresaId, string $referencia): ?array
    {
        if (!$this->orgId) {
            return null;
        }
        $sesion = $this->credenciales->de($empresaId)->id . self::sesion($referencia);

        return ["script" => sprintf(
            "https://h.online-metrix.net/fp/tags.js?org_id=%s&session_id=%s",
            rawurlencode($this->orgId),
            rawurlencode($sesion),
        )];
    }

    /** Paso 1: setup de 3-D Secure; el navegador hace la recolección de datos del dispositivo. */
    private function iniciar(Comercio $comercio, SolicitudPago $s): ResultadoPago
    {
        [$codigo, $r] = $this->cliente->post($comercio, self::RUTA_SETUP, [
            "clientReferenceInformation" => ["code" => $s->referencia],
            "paymentInformation" => $this->tarjeta($s),
        ]);
        $auth = $r["consumerAuthenticationInformation"] ?? [];
        if ($codigo >= 300 || empty($auth["accessToken"]) || empty($auth["deviceDataCollectionUrl"]) || empty($auth["referenceId"])) {
            $this->logger->warning("Cybersource: setup de 3-D Secure fallido ({codigo}): {respuesta}", ["codigo" => $codigo, "respuesta" => self::paraLog($r)]);

            return ResultadoPago::rechazado($codigo >= 500 || $r === []
                ? "No fue posible iniciar la verificación con su banco. No se realizó ningún cobro; intente de nuevo."
                : self::mensaje($r));
        }
        $url = (string) $auth["deviceDataCollectionUrl"];
        $origen = self::origen($url);

        return ResultadoPago::dispositivo(
            $url,
            ["JWT" => (string) $auth["accessToken"]],
            ["paso" => "enrolar", "referencia" => (string) $auth["referenceId"]],
            array_values(array_unique(array_filter([$origen, ...self::ORIGENES_DDC]))),
        );
    }

    /**
     * Pasos 2 y 3: pago con autenticación y captura.
     *
     * @param array<string, mixed> $procesamiento
     * @param array<string, mixed> $autenticacion
     */
    private function pagar(Comercio $comercio, SolicitudPago $s, array $procesamiento, array $autenticacion, bool $puedePedirDesafio = true): ResultadoPago
    {
        $cuerpo = [
            "clientReferenceInformation" => ["code" => $s->referencia],
            "processingInformation" => [...$procesamiento, "capture" => true],
            "paymentInformation" => $this->tarjeta($s),
            "orderInformation" => [
                "amountDetails" => self::importe($s->monto),
                "billTo" => $this->facturarA($s),
            ],
            "consumerAuthenticationInformation" => $autenticacion,
            "deviceInformation" => $this->dispositivo($s),
        ];
        try {
            [$codigo, $r] = $this->cliente->post($comercio, self::RUTA_PAGOS, $cuerpo);
        } catch (TransportExceptionInterface $e) {
            $this->logger->critical("Cybersource: sin respuesta al cobrar {ref}: {error}. Revisar en el Business Center si se cobró.", [
                "ref" => $s->referencia,
                "error" => $e->getMessage(),
            ]);
            throw new PagoIncierto("Sin respuesta de Cybersource al cobrar.", 0, $e);
        }
        $estado = (string) ($r["status"] ?? "");
        $id = isset($r["id"]) ? (string) $r["id"] : null;
        $auth = $r["consumerAuthenticationInformation"] ?? [];
        $this->logger->info("Cybersource: cobro {ref} → HTTP {codigo} {estado} ({id})", [
            "ref" => $s->referencia,
            "codigo" => $codigo,
            "estado" => $estado,
            "id" => $id,
        ]);
        if ($codigo >= 500) {
            $this->logger->critical("Cybersource: error {codigo} al cobrar {ref}: {respuesta}. Revisar en el Business Center si se cobró.", [
                "codigo" => $codigo,
                "ref" => $s->referencia,
                "respuesta" => self::paraLog($r),
            ]);
            throw new PagoIncierto("Error {$codigo} de Cybersource al cobrar.");
        }

        if ($estado === "PENDING_AUTHENTICATION" && $puedePedirDesafio && !empty($auth["stepUpUrl"]) && !empty($auth["accessToken"])) {
            [$ancho, $alto] = self::ventana($auth["pareq"] ?? null);

            return ResultadoPago::autenticacion(
                $id ?? "",
                (string) $auth["stepUpUrl"],
                ["JWT" => (string) $auth["accessToken"]],
                ["paso" => "validar", "transaccion" => (string) ($auth["authenticationTransactionId"] ?? "")],
                $ancho,
                $alto,
            );
        }

        if ($estado === "AUTHORIZED" && $id !== null) {
            if (!self::autenticado($auth)) {
                $this->logger->warning("Cybersource: cobro {id} autorizado sin autenticación 3-D Secure: se anula.", ["id" => $id]);
                $this->anularOAvisar($comercio, $id, $s->monto);

                return ResultadoPago::rechazado("Su banco no completó la verificación 3-D Secure de la tarjeta. No se realizó ningún cobro; intente de nuevo o use otra tarjeta.", $id);
            }

            return ResultadoPago::aprobado($id, (string) ($r["processorInformation"]["approvalCode"] ?? $id));
        }

        // Autorizado pero retenido por el análisis de riesgo (o autenticado sin autorizar): se anula.
        if ($id !== null && in_array($estado, ["AUTHORIZED_PENDING_REVIEW", "AUTHORIZED_RISK_DECLINED", "PENDING_REVIEW", "AUTHENTICATION_SUCCESSFUL"], true)) {
            $this->anularOAvisar($comercio, $id, $s->monto);
        }
        $this->logger->notice("Cybersource: cobro {ref} rechazado ({estado}): {respuesta}", [
            "ref" => $s->referencia,
            "estado" => $estado,
            "respuesta" => self::paraLog($r),
        ]);

        return ResultadoPago::rechazado(self::mensaje($r), $id);
    }

    /** Anula (void) y, si ya no se puede, reembolsa. */
    private function anular(Comercio $comercio, string $id, Money $monto): bool
    {
        $ruta = self::RUTA_PAGOS . "/" . rawurlencode($id);
        try {
            [$codigo, $r] = $this->cliente->post($comercio, "{$ruta}/voids", ["clientReferenceInformation" => ["code" => $id]]);
            if ($codigo < 300 && in_array($r["status"] ?? null, ["VOIDED", "PENDING"], true)) {
                $this->logger->notice("Cybersource: pago {id} anulado.", ["id" => $id]);

                return true;
            }
            [$codigo, $r] = $this->cliente->post($comercio, "{$ruta}/refunds", [
                "clientReferenceInformation" => ["code" => $id],
                "orderInformation" => ["amountDetails" => self::importe($monto)],
            ]);
            if ($codigo < 300 && in_array($r["status"] ?? null, ["PENDING", "REFUNDED", "TRANSMITTED"], true)) {
                $this->logger->notice("Cybersource: pago {id} reembolsado.", ["id" => $id]);

                return true;
            }
            $this->logger->critical("Cybersource: no se pudo anular ni reembolsar {id}: {respuesta}", ["id" => $id, "respuesta" => self::paraLog($r)]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->critical("Cybersource: sin respuesta al anular {id}: {error}", ["id" => $id, "error" => $e->getMessage()]);
        }

        return false;
    }

    private function anularOAvisar(Comercio $comercio, string $id, Money $monto): void
    {
        if (!$this->anular($comercio, $id, $monto)) {
            $this->logger->critical("Cybersource: el pago {id} quedó cobrado sin venta: devolverlo desde el Business Center.", ["id" => $id]);
        }
    }

    /** @return array<string, mixed> */
    private function tarjeta(SolicitudPago $s): array
    {
        $t = $s->tarjeta;

        return ["card" => [
            "type" => $t->marcaTarjeta() === "mastercard" ? "002" : "001",
            "number" => $t->numero(),
            "expirationMonth" => sprintf("%02d", $t->mesExpira),
            "expirationYear" => (string) $t->anioExpira,
            "securityCode" => $t->cvv(),
        ]];
    }

    /** @return array<string, string> */
    private function facturarA(SolicitudPago $s): array
    {
        [$nombre, $apellido] = self::nombreTitular($s->tarjeta->titular, $s->nombre, $s->apellido);
        $d = $s->direccion;

        return array_filter([
            "firstName" => $nombre,
            "lastName" => $apellido,
            "email" => $s->correo,
            "phoneNumber" => $s->telefono,
            "address1" => $d->direccion,
            "locality" => $d->ciudad,
            "administrativeArea" => $d->region,
            "postalCode" => $d->codigoPostal,
            "country" => $d->pais,
        ], static fn($v) => $v !== null && $v !== "");
    }

    /** @return array<string, mixed> */
    private function dispositivo(SolicitudPago $s): array
    {
        $n = $s->navegador;

        return array_filter([
            "ipAddress" => $n->ip,
            "fingerprintSessionId" => $this->orgId ? self::sesion($s->referencia) : null,
            "userAgentBrowserValue" => $n->agente,
            "httpAcceptBrowserValue" => $n->accept,
            "httpBrowserLanguage" => $n->idioma,
            "httpBrowserJavaEnabled" => false,
            "httpBrowserJavaScriptEnabled" => true,
            "httpBrowserColorDepth" => $n->profundidadColor !== null ? (string) $n->profundidadColor : null,
            "httpBrowserScreenHeight" => $n->altoPantalla !== null ? (string) $n->altoPantalla : null,
            "httpBrowserScreenWidth" => $n->anchoPantalla !== null ? (string) $n->anchoPantalla : null,
            "httpBrowserTimeDifference" => $n->diferenciaHoraria !== null ? (string) $n->diferenciaHoraria : null,
        ], static fn($v) => $v !== null);
    }

    /** @return array{totalAmount: string, currency: string} */
    public static function importe(Money $monto): array
    {
        $centavos = (int) $monto->getAmount();

        return [
            "totalAmount" => sprintf("%s%d.%02d", $centavos < 0 ? "-" : "", intdiv(abs($centavos), 100), abs($centavos) % 100),
            "currency" => $monto->getCurrency()->getCode(),
        ];
    }

    /**
     * Evidencia de autenticación 3-D Secure en la respuesta del pago: el
     * criptograma llega como `cavv` (Visa), `token` o `ucafAuthenticationData`
     * con `paresStatus` Y (Mastercard); una tarjeta no inscrita cuenta si el
     * intento de autenticación se registró.
     *
     * @param array<string, mixed> $auth
     */
    public static function autenticado(array $auth): bool
    {
        if (!empty($auth["cavv"]) || !empty($auth["token"])) {
            return true;
        }
        if (!empty($auth["ucafAuthenticationData"]) && ($auth["paresStatus"] ?? null) === "Y") {
            return true;
        }

        return ($auth["cardEnrolled"] ?? "U") === "N"
            && in_array($auth["authenticationStatus"] ?? null, ["SUCCESSFUL", "ATTEMPTED"], true);
    }

    /**
     * Tamaño del iframe del desafío según `challengeWindowSize` (dentro del
     * `pareq`, JSON en base64): 01 250×400, 02 390×400, 03 500×600, 04 600×400,
     * 05 pantalla completa.
     *
     * @return array{0: string, 1: string} ancho y alto (CSS)
     */
    public static function ventana(mixed $pareq): array
    {
        $datos = is_string($pareq) ? json_decode((string) base64_decode($pareq, true), true) : null;

        return match (is_array($datos) ? ($datos["challengeWindowSize"] ?? null) : null) {
            "01" => ["250px", "400px"],
            "02" => ["390px", "400px"],
            "03" => ["500px", "600px"],
            "04" => ["600px", "400px"],
            default => ["100%", "100%"],
        };
    }

    /**
     * Mensaje para el comprador a partir del motivo que da Cybersource.
     *
     * @param array<string, mixed> $r
     */
    public static function mensaje(array $r): string
    {
        $motivo = (string) ($r["errorInformation"]["reason"] ?? $r["reason"] ?? $r["status"] ?? "");
        $texto = match ($motivo) {
            "INSUFFICIENT_FUND" => "Su banco rechazó el pago: fondos insuficientes.",
            "EXCEEDS_CREDIT_LIMIT" => "Su banco rechazó el pago: excede el límite de la tarjeta.",
            "EXPIRED_CARD" => "Su banco rechazó el pago: la tarjeta está vencida.",
            "INVALID_CVN", "CV_FAILED" => "Su banco rechazó el pago: el código de seguridad (CVV) no coincide.",
            "AVS_FAILED" => "Su banco rechazó el pago: la dirección de facturación no coincide con la registrada en su banco.",
            "STOLEN_LOST_CARD", "INVALID_ACCOUNT", "CARD_TYPE_NOT_ACCEPTED", "UNAUTHORIZED_CARD", "ACCOUNT_NOT_ALLOWED_CNP" => "Su banco no permite usar esta tarjeta para compras en línea. Use otra tarjeta o consulte a su banco.",
            "CONSUMER_AUTHENTICATION_FAILED", "AUTHENTICATION_FAILED", "CUSTOMER_AUTHENTICATION_REQUIRED" => "La verificación 3-D Secure con su banco no se completó. No se realizó ningún cobro.",
            "DECISION_PROFILE_REJECT", "SCORE_EXCEEDS_THRESHOLD", "DECISION_PROFILE_REVIEW" => "El pago no fue aprobado por el análisis de seguridad del banco. Intente con otra tarjeta.",
            "INVALID_DATA", "MISSING_FIELD", "INVALID_CARD", "INVALID_MERCHANT_CONFIGURATION" => "El banco no aceptó los datos del pago. Revise el número, la fecha y la dirección de su tarjeta.",
            "SYSTEM_ERROR", "SERVER_TIMEOUT", "SERVICE_TIMEOUT", "PROCESSOR_UNAVAILABLE" => "El banco no está disponible en este momento. Intente de nuevo en unos minutos.",
            default => "Su banco rechazó el pago. Intente con otra tarjeta o consulte a su banco.",
        };

        return $motivo !== "" ? "{$texto} (código {$motivo})" : $texto;
    }

    /** Id de la sesión de huella: el token del carrito sin guiones. */
    public static function sesion(string $referencia): string
    {
        return str_replace("-", "", $referencia);
    }

    /** @return array{0: string, 1: string} */
    private static function nombreTitular(string $titular, string $nombre, ?string $apellido): array
    {
        $partes = preg_split('/\s+/', trim($titular)) ?: [];
        if (count($partes) >= 2) {
            $ultimo = (string) array_pop($partes);

            return [mb_substr(implode(" ", $partes), 0, 60), mb_substr($ultimo, 0, 60)];
        }

        return [mb_substr($nombre, 0, 60), mb_substr($apellido ?: $nombre, 0, 60)];
    }

    private static function origen(string $url): ?string
    {
        $p = parse_url($url);

        return isset($p["scheme"], $p["host"]) ? $p["scheme"] . "://" . $p["host"] . (isset($p["port"]) ? ":" . $p["port"] : "") : null;
    }

    /**
     * Respuesta para el log, sin tokens ni criptogramas.
     *
     * @param array<string, mixed> $r
     */
    private static function paraLog(array $r): string
    {
        unset($r["_links"]);
        if (isset($r["consumerAuthenticationInformation"]) && is_array($r["consumerAuthenticationInformation"])) {
            $r["consumerAuthenticationInformation"] = array_diff_key(
                $r["consumerAuthenticationInformation"],
                array_flip(["accessToken", "cavv", "token", "ucafAuthenticationData", "pareq", "xid"]),
            );
        }

        return mb_substr((string) json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 2000);
    }
}
