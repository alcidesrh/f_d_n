<?php

declare(strict_types=1);

namespace App\Acceso\Suplantacion;

use App\Entity\ConfiguracionSuplantacion;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Lee `ConfiguracionSuplantacion` (o la de por defecto, sin fila). Se
 * consulta solo cuando hay suplantación de por medio: sin caché.
 */
class AjustesSuplantacion
{
    public function __construct(
        private readonly ?EntityManagerInterface $em = null,
        private readonly ?ConfiguracionSuplantacion $fija = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /** Ajustes que no vienen de la base (pruebas). */
    public static function fijos(ConfiguracionSuplantacion $configuracion): self
    {
        return new self(null, $configuracion);
    }

    public function actual(): ConfiguracionSuplantacion
    {
        if ($this->fija !== null) {
            return $this->fija;
        }
        try {
            return $this->em?->find(ConfiguracionSuplantacion::class, ConfiguracionSuplantacion::ID) ?? new ConfiguracionSuplantacion();
        } catch (\Throwable $e) {
            // Sin la tabla (antes de migrar): los valores por defecto.
            $this->logger->warning("No se pudo leer la configuración de la suplantación: {error}", ["error" => $e->getMessage()]);

            return new ConfiguracionSuplantacion();
        }
    }
}
