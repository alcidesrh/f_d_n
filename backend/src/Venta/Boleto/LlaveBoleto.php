<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Código de abordaje de un boleto (ADR-063): lo que lleva el QR del PDF y
 * lee el chequeo por escaneo. `FDN1.<id del boleto>.<firma>`; la firma es un
 * HMAC del id con una llave derivada de `APP_SECRET`, así un código no se
 * inventa a partir de otro. No caduca: lo que decide si el boleto se chequea
 * es su estado y su salida, no el código.
 */
final class LlaveBoleto
{
    private const PREFIJO = "FDN1";
    private const LARGO_FIRMA = 12;

    public function __construct(
        #[Autowire("%kernel.secret%")] private readonly string $secreto,
    ) {}

    public function codigo(int $boletoId): string
    {
        return sprintf("%s.%d.%s", self::PREFIJO, $boletoId, $this->firma($boletoId));
    }

    /** El id del boleto de un código bien firmado, o null. */
    public function boleto(string $codigo): ?int
    {
        if (preg_match('/^' . self::PREFIJO . '\.([1-9]\d{0,17})\.([A-Za-z0-9_-]{' . self::LARGO_FIRMA . '})$/', trim($codigo), $m) !== 1) {
            return null;
        }
        $id = (int) $m[1];

        return hash_equals($this->firma($id), $m[2]) ? $id : null;
    }

    /**
     * El id de la venta de un código de barras de boleto impreso (Code 128,
     * `DatosBoleto::codigoBarras`: el id de la venta con ceros), o null.
     */
    public static function venta(string $codigo): ?int
    {
        $codigo = trim($codigo);

        return preg_match('/^\d{1,18}$/', $codigo) === 1 && (int) $codigo > 0 ? (int) $codigo : null;
    }

    private function firma(int $boletoId): string
    {
        $mac = hash_hmac("sha256", "boleto-abordaje:" . $boletoId, $this->secreto, true);

        return substr(rtrim(strtr(base64_encode($mac), "+/", "-_"), "="), 0, self::LARGO_FIRMA);
    }
}
