<?php

declare(strict_types=1);

namespace App\Correo;

use App\Correo\Configuracion\AjustesCorreo;
use App\Entity\ConfiguracionCorreo;

/**
 * Los dominios del correo de la empresa (`ConfiguracionCorreo`, leídos en
 * cada uso):
 *
 * - el principal, el de las direcciones (`{usuario}@sistemafdn.com`), el
 *   que va en el remitente;
 * - el de entrada (opcional), donde se reciben si es otro. MailPace solo
 *   recibe en un subdominio (`correo.sistemafdn.com`); entonces el remitente
 *   sigue siendo `@sistemafdn.com` y las respuestas vuelven por `Reply-To`
 *   al subdominio.
 *
 * Una dirección es "de la casa" si su dominio es cualquiera de los dos.
 */
final class Dominios
{
    public function __construct(private readonly AjustesCorreo $ajustes) {}

    /** Con dominios fijos (pruebas). */
    public static function fijos(string $principal, ?string $entrada = null): self
    {
        return new self(AjustesCorreo::fijos((new ConfiguracionCorreo())->setDominios(
            strtolower(trim($principal)),
            $entrada !== null && trim($entrada) !== "" ? strtolower(trim($entrada)) : null,
        )));
    }

    public function principal(): string
    {
        return $this->ajustes->actual()->getDominio();
    }

    public function entrada(): string
    {
        $c = $this->ajustes->actual();

        return $c->getDominioEntrada() ?? $c->getDominio();
    }

    public function direccion(string $local): string
    {
        return $local . "@" . $this->principal();
    }

    /** Dirección a la que se responde (la de entrada), o null si es la misma del remitente. */
    public function responderA(string $local): ?string
    {
        $principal = $this->principal();
        $entrada = $this->entrada();

        return $entrada !== $principal ? $local . "@" . $entrada : null;
    }

    /** La parte local si la dirección es de la casa; si no, null. */
    public function local(string $direccion): ?string
    {
        [$local, $dominio] = Direcciones::partes($direccion);
        if ($local === "" || ($dominio !== $this->principal() && $dominio !== $this->entrada())) {
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
