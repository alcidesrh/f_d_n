<?php

declare(strict_types=1);

namespace App\Correo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Los dominios del correo de la empresa:
 *
 * - `CORREO_DOMINIO`: el de las direcciones (`{usuario}@sistemafdn.com`), el
 *   que va en el remitente.
 * - `CORREO_DOMINIO_ENTRADA` (opcional): donde se reciben, si es otro. MailPace
 *   solo recibe en un subdominio (`correo.sistemafdn.com`); entonces el
 *   remitente sigue siendo `@sistemafdn.com` y las respuestas vuelven por
 *   `Reply-To` al subdominio.
 *
 * Una dirección es "de la casa" si su dominio es cualquiera de los dos.
 */
final class Dominios
{
    public readonly string $principal;
    public readonly string $entrada;

    public function __construct(
        #[Autowire(env: "default::CORREO_DOMINIO")] ?string $principal = null,
        #[Autowire(env: "default::CORREO_DOMINIO_ENTRADA")] ?string $entrada = null,
    ) {
        $this->principal = strtolower(trim((string) $principal)) ?: "sistemafdn.com";
        $this->entrada = strtolower(trim((string) $entrada)) ?: $this->principal;
    }

    public function direccion(string $local): string
    {
        return $local . "@" . $this->principal;
    }

    /** Dirección a la que se responde (la de entrada), o null si es la misma del remitente. */
    public function responderA(string $local): ?string
    {
        return $this->entrada !== $this->principal ? $local . "@" . $this->entrada : null;
    }

    /** La parte local si la dirección es de la casa; si no, null. */
    public function local(string $direccion): ?string
    {
        [$local, $dominio] = Direcciones::partes($direccion);
        if ($local === "" || ($dominio !== $this->principal && $dominio !== $this->entrada)) {
            return null;
        }
        // `ana+reportes@` llega a `ana@` (subdirecciones).
        $mas = strpos($local, "+");

        return $mas === false ? $local : substr($local, 0, $mas);
    }

    public function esDeLaCasa(string $direccion): bool
    {
        return $this->local($direccion) !== null;
    }
}
