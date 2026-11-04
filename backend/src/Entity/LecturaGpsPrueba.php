<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una lectura GPS de un teléfono de prueba (temporal, ADR-050): como
 * `LecturaGps`, pero del dispositivo con un nombre acordado en vez de un bus.
 * Solo la escribe y la lee `App\Seguimiento\TelefonosDePrueba` (por DBAL) y se
 * purga con las de los buses.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: "lectura_gps_prueba")]
class LecturaGpsPrueba
{
    /** El identificador del dispositivo en Traccar Client, en mayúsculas. */
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $dispositivo;

    #[ORM\Id]
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $instante;

    #[ORM\Column]
    private float $lat;

    #[ORM\Column]
    private float $lng;

    /** Grados, 0 = norte. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $rumbo;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $velocidadKmh;

    private function __construct() {}

    public function getDispositivo(): string
    {
        return $this->dispositivo;
    }

    public function getInstante(): \DateTimeImmutable
    {
        return $this->instante;
    }
}
