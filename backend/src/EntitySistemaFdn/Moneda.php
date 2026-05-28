<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'moneda')]
class Moneda
{
    public const GTQ = 1;
    public const USD = 2;
    public const EUR = 3;
    public const HNL = 4;
    public const BZD = 5;
    public const MXN = 6;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 3, unique: true)]
    private string $sigla;

    #[ORM\Column(type: 'string', length: 40, unique: true)]
    private string $nombre;

    #[ORM\Column(type: 'boolean')]
    private bool $activo;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSigla(): string
    {
        return $this->sigla;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getActivo(): bool
    {
        return $this->activo;
    }
}
