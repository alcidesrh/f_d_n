<?php

declare(strict_types=1);

namespace App\Venta\EnLinea\Modificacion;

use App\Venta\Excepcion\VentaRechazada;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Acceso a «Mis compras» sin usuario ni contraseña: una llave firmada
 * (HMAC-SHA256 con el secreto de la aplicación) atada al correo del
 * comprador y con vencimiento. No se guarda en la base: la firma basta.
 *
 * La página la recibe sin que el cliente haga nada al terminar una compra
 * (queda en el navegador), en el enlace del correo del boleto (para entrar
 * desde otro dispositivo) o, si no tiene ninguna, pidiendo un enlace a su
 * correo. Quien tiene la llave ve y modifica las compras de ese correo: es
 * lo mismo que ya da el correo del boleto (PDF y factura).
 */
final class LlaveCliente
{
    public const VIGENCIA_DIAS = 180;
    private const VERSION = "v1";
    private const CONTEXTO = "fdn.mis-compras";

    public function __construct(
        #[Autowire("%kernel.secret%")]
        private readonly string $secreto,
        private readonly ClockInterface $reloj,
    ) {}

    /** Llave para el correo (normalizado), válida `VIGENCIA_DIAS`. */
    public function emitir(string $correo, ?int $dias = null): string
    {
        $correo = self::normalizar($correo);
        if ($correo === "") {
            throw new \InvalidArgumentException("Sin correo no hay llave.");
        }
        $vence = $this->reloj->now()->modify(sprintf("+%d days", $dias ?? self::VIGENCIA_DIAS))->getTimestamp();
        $carga = self::b64(implode("|", [self::VERSION, (string) $vence, $correo]));

        return $carga . "." . self::b64($this->firma($carga));
    }

    /**
     * El correo de una llave válida.
     *
     * @throws VentaRechazada 401 si falta, está alterada o venció
     */
    public function correo(?string $llave): string
    {
        $llave = trim((string) $llave);
        $partes = explode(".", $llave);
        if (count($partes) !== 2 || strlen($llave) > 600) {
            throw self::sinAcceso();
        }
        [$carga, $firma] = $partes;
        $esperada = self::b64($this->firma($carga));
        if (!hash_equals($esperada, $firma)) {
            throw self::sinAcceso();
        }
        $datos = explode("|", (string) self::deB64($carga), 3);
        if (count($datos) !== 3 || $datos[0] !== self::VERSION || !ctype_digit($datos[1])) {
            throw self::sinAcceso();
        }
        if ((int) $datos[1] <= $this->reloj->now()->getTimestamp()) {
            throw new VentaRechazada("Su acceso a «Mis compras» venció. Pida un enlace nuevo a su correo.", "llave_vencida", 401);
        }

        return $datos[2];
    }

    public static function normalizar(?string $correo): string
    {
        return mb_strtolower(trim((string) $correo));
    }

    private function firma(string $carga): string
    {
        return hash_hmac("sha256", self::CONTEXTO . "|" . $carga, $this->secreto, true);
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), "+/", "-_"), "=");
    }

    private static function deB64(string $texto): string|false
    {
        return base64_decode(strtr($texto, "-_", "+/"), true);
    }

    private static function sinAcceso(): VentaRechazada
    {
        return new VentaRechazada("Para ver sus compras, abra el enlace que le enviamos por correo o pida uno nuevo.", "sin_llave", 401);
    }
}
