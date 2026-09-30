<?php

declare(strict_types=1);

namespace App\Venta;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cifra las claves de servicios externos que se guardan en la base de datos
 * (certificador FEL, pasarela de pago). La llave se deriva de `APP_SECRET` y
 * de un contexto por servicio: si cambia APP_SECRET hay que volver a cargarlas.
 */
final class CifradoCredenciales
{
    public function __construct(
        #[Autowire("%kernel.secret%")]
        private readonly string $secreto,
    ) {}

    public function cifrar(string $contexto, string $claro): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($claro, $nonce, $this->llave($contexto)));
    }

    /** null si no se puede descifrar (otra llave o dato dañado). */
    public function descifrar(string $contexto, string $cifrado): ?string
    {
        $crudo = base64_decode($cifrado, true);
        if ($crudo === false || strlen($crudo) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $claro = sodium_crypto_secretbox_open(
            substr($crudo, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($crudo, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->llave($contexto),
        );

        return $claro === false ? null : $claro;
    }

    private function llave(string $contexto): string
    {
        return sodium_crypto_generichash($contexto . "|" . $this->secreto, "", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
