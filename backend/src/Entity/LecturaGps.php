<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una lectura del GPS de un bus: dónde estaba en un instante (ADR-050). La
 * guarda `App\Seguimiento\HistorialGps` al llegar a `/api/seguimiento/gps` y
 * con ellas se reproduce la trayectoria de una salida. Solo se agrega (por
 * DBAL) y se purga sola (`Tarea::PURGAR_LECTURAS_GPS`).
 *
 * La clave es el bus y el instante: Traccar reenvía lo que tenía en cola y la
 * misma lectura no se guarda dos veces. Esa misma clave sirve para leer la
 * trayectoria (un bus entre dos instantes) y para purgar.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: "lectura_gps")]
class LecturaGps
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Bus $bus;

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

    public function getBus(): Bus
    {
        return $this->bus;
    }

    public function getInstante(): \DateTimeImmutable
    {
        return $this->instante;
    }

    public function getLat(): float
    {
        return $this->lat;
    }

    public function getLng(): float
    {
        return $this->lng;
    }

    public function getRumbo(): int
    {
        return $this->rumbo;
    }

    public function getVelocidadKmh(): int
    {
        return $this->velocidadKmh;
    }
}
