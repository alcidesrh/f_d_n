<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'bus_asiento')]
class AsientoBus
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'boolean')]
    private bool $nivel2;

    #[ORM\Column(type: 'integer')]
    private int $numero;

    #[ORM\Column(name: 'coordenadaX', type: 'integer')]
    private int $coordenadaX;

    #[ORM\Column(name: 'coordenadaY', type: 'integer')]
    private int $coordenadaY;

    #[ORM\ManyToOne(targetEntity: TipoBus::class, inversedBy: 'listaAsiento')]
    #[ORM\JoinColumn(name: 'tipoBus_id', referencedColumnName: 'id')]
    private ?TipoBus $tipoBus = null;

    #[ORM\ManyToOne(targetEntity: ClaseAsiento::class)]
    #[ORM\JoinColumn(name: 'clase_id', referencedColumnName: 'id')]
    private ?ClaseAsiento $clase = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): int
    {
        return $this->numero;
    }

    public function getNivel2(): bool
    {
        return $this->nivel2;
    }

    public function getClase(): ?ClaseAsiento
    {
        return $this->clase;
    }

    public function getTipoBus(): ?TipoBus
    {
        return $this->tipoBus;
    }
}
