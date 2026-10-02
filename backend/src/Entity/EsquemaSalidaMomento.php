<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Una hora del día (`HH:MM`) con su bus dentro de un esquema de salidas (ADR-024). */
#[ORM\Entity]
class EsquemaSalidaMomento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "momentos")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private EsquemaSalida $esquema;

    /** `HH:MM`, 24 h */
    #[ORM\Column(length: 5)]
    private string $hora;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Bus $bus;

    public function __construct(EsquemaSalida $esquema, string $hora, Bus $bus)
    {
        $this->esquema = $esquema;
        $this->hora = $hora;
        $this->bus = $bus;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEsquema(): EsquemaSalida
    {
        return $this->esquema;
    }

    public function getHora(): string
    {
        return $this->hora;
    }

    public function getBus(): Bus
    {
        return $this->bus;
    }
}
