<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'horario_ciclico')]
class HorarioCiclico
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'time', unique: true)]
    private \DateTime $hora;

    #[ORM\Column(type: 'boolean')]
    private bool $activo;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHora(): \DateTime
    {
        return $this->hora;
    }
}
