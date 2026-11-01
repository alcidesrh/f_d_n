<?php

declare(strict_types=1);

namespace App\Venta\Facturacion\Forcon;

use App\Entity\ConfiguracionForcon;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lee `ConfiguracionForcon` en cada uso (un cambio vale sin reiniciar). Sin
 * fila, valen los valores por defecto: certificador simulado, API de pruebas,
 * sin credencial global ni emisores en línea.
 */
final class AjustesForcon
{
    public function __construct(
        private readonly ?EntityManagerInterface $em = null,
        private readonly ?ConfiguracionForcon $fija = null,
    ) {}

    /** Ajustes que no vienen de la base (pruebas). */
    public static function fijos(ConfiguracionForcon $configuracion): self
    {
        return new self(null, $configuracion);
    }

    public function actual(): ConfiguracionForcon
    {
        return $this->fija
            ?? $this->em?->find(ConfiguracionForcon::class, ConfiguracionForcon::ID)
            ?? new ConfiguracionForcon();
    }
}
