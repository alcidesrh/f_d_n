<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Control de la migración del legado (ADR-027): la salida recibió un bus
 * inferido porque en el legado no tenía (se vendía con el tipo de bus). Si
 * después el legado le asigna el bus real, la siguiente corrida lo cambia.
 * Mientras exista la fila, el bus de la salida lo puso la migración. No es
 * parte del modelo de negocio ni se publica en la API.
 */
#[ORM\Entity]
#[ORM\Table(name: "salida_bus_inferido")]
class SalidaBusInferido
{
    #[ORM\Id]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Salida $salida;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Bus $bus;

    /** Cómo se eligió: `hora`, `franja` o `flota` (ver `App\Migration\Salida\InferenciaBus`). */
    #[ORM\Column(length: 10)]
    private string $criterio;

    /** Salida del legado de la que se tomó el bus (null con el criterio `flota`). */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $referenciaLegado = null;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    private function __construct() {}

    public function getSalida(): Salida
    {
        return $this->salida;
    }

    public function getBus(): Bus
    {
        return $this->bus;
    }

    public function getCriterio(): string
    {
        return $this->criterio;
    }

    public function getReferenciaLegado(): ?string
    {
        return $this->referenciaLegado;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }
}
