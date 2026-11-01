<?php

declare(strict_types=1);

namespace App\Correo\Configuracion;

use App\Entity\ConfiguracionCorreo;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lee `ConfiguracionCorreo` en cada uso (un cambio vale sin reiniciar los
 * workers). Sin fila, valen los valores por defecto: MailPace apagado,
 * `sistemafdn.com` y sin correo entrante.
 */
final class AjustesCorreo
{
    public function __construct(
        private readonly ?EntityManagerInterface $em = null,
        private readonly ?ConfiguracionCorreo $fija = null,
    ) {}

    /** Ajustes que no vienen de la base (pruebas). */
    public static function fijos(ConfiguracionCorreo $configuracion): self
    {
        return new self(null, $configuracion);
    }

    public function actual(): ConfiguracionCorreo
    {
        return $this->fija
            ?? $this->em?->find(ConfiguracionCorreo::class, ConfiguracionCorreo::ID)
            ?? new ConfiguracionCorreo();
    }
}
