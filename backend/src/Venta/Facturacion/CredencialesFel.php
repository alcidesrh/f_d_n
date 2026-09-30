<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use App\Entity\CredencialFel;
use App\Entity\Empresa;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Credenciales del certificador por NIT emisor. Primero las de la empresa
 * (`CredencialFel`, clave cifrada con una llave derivada de `APP_SECRET`:
 * si se cambia APP_SECRET hay que volver a cargarlas); si no tiene, las
 * globales `FEL_FORCON_USUARIO`/`FEL_FORCON_CLAVE` (una sola empresa).
 */
final class CredencialesFel
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire("%kernel.secret%")]
        private readonly string $secreto,
        #[Autowire(env: "default::FEL_FORCON_USUARIO")]
        private readonly ?string $usuarioGlobal = null,
        #[Autowire(env: "default::FEL_FORCON_CLAVE")]
        private readonly ?string $claveGlobal = null,
    ) {}

    /**
     * @return array{0: string, 1: string} usuario y clave
     *
     * @throws CertificacionFallida si no hay credenciales
     */
    public function para(?string $nitEmisor): array
    {
        $credencial = $nitEmisor !== null ? $this->deNit($nitEmisor) : null;
        if ($credencial !== null) {
            return [$credencial->getUsuario(), $this->descifrar($credencial->getClaveCifrada())];
        }
        if ($this->usuarioGlobal && $this->claveGlobal) {
            return [$this->usuarioGlobal, $this->claveGlobal];
        }
        // Servicios que no dependen del emisor (consulta de NIT): cualquier empresa.
        if ($nitEmisor === null && ($credencial = $this->cualquiera()) !== null) {
            return [$credencial->getUsuario(), $this->descifrar($credencial->getClaveCifrada())];
        }

        throw new CertificacionFallida(
            sprintf("No hay credenciales del certificador para el NIT emisor %s (app:fel:credencial).", $nitEmisor ?? "—"),
            false,
            "sin_credenciales",
        );
    }

    /** Guarda (o reemplaza) las credenciales de una empresa. */
    public function guardar(Empresa $empresa, string $usuario, string $clave): void
    {
        $cifrada = $this->cifrar($clave);
        $actual = $this->em->getRepository(CredencialFel::class)->findOneBy(["empresa" => $empresa]);
        if ($actual !== null) {
            $actual->cambiar($usuario, $cifrada);
        } else {
            $this->em->persist(new CredencialFel($empresa, $usuario, $cifrada));
        }
        $this->em->flush();
    }

    public function cifrar(string $clave): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($clave, $nonce, $this->llave()));
    }

    public function descifrar(string $cifrada): string
    {
        $crudo = base64_decode($cifrada, true);
        $claro = $crudo !== false && strlen($crudo) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
            ? sodium_crypto_secretbox_open(
                substr($crudo, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                substr($crudo, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                $this->llave(),
            )
            : false;
        if ($claro === false) {
            throw new CertificacionFallida(
                "No se pudo leer la clave del certificador (¿cambió APP_SECRET?). Vuelva a cargarla con app:fel:credencial.",
                false,
                "credenciales",
            );
        }

        return $claro;
    }

    private function deNit(string $nit): ?CredencialFel
    {
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy(["nit" => $nit]);

        return $empresa !== null
            ? $this->em->getRepository(CredencialFel::class)->findOneBy(["empresa" => $empresa])
            : null;
    }

    private function cualquiera(): ?CredencialFel
    {
        return $this->em->getRepository(CredencialFel::class)->findOneBy([], ["id" => "ASC"]);
    }

    private function llave(): string
    {
        return sodium_crypto_generichash("fdn-credencial-fel|" . $this->secreto, "", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
