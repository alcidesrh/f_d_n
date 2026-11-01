<?php

declare(strict_types=1);

namespace App\Venta\Pago\Cybersource;

use App\Entity\ConfiguracionCybersource;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lee `ConfiguracionCybersource` en cada uso (un cambio vale sin reiniciar).
 * Sin fila, valen los valores por defecto: pasarela simulada, API de pruebas
 * y sin huella del dispositivo.
 */
final class AjustesCybersource
{
    public function __construct(
        private readonly ?EntityManagerInterface $em = null,
        private readonly ?ConfiguracionCybersource $fija = null,
    ) {}

    /** Ajustes que no vienen de la base (pruebas). */
    public static function fijos(ConfiguracionCybersource $configuracion): self
    {
        return new self(null, $configuracion);
    }

    public function actual(): ConfiguracionCybersource
    {
        return $this->fija
            ?? $this->em?->find(ConfiguracionCybersource::class, ConfiguracionCybersource::ID)
            ?? new ConfiguracionCybersource();
    }
}
