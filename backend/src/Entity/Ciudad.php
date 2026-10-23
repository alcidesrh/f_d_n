<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Ciudad o municipio de una `Region` (ADR-041). */
#[ORM\Entity]
#[ORM\Index(columns: ["region_id"], name: "idx_ciudad_region")]
class Ciudad
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Region $region;

    #[ORM\Column(length: 100)]
    private string $nombre;

    public function __construct(Region $region, string $nombre)
    {
        $this->region = $region;
        $this->nombre = $nombre;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRegion(): Region
    {
        return $this->region;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }
}
