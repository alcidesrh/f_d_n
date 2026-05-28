<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'bus_senal')]
class SenalBus
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'boolean')] private bool $nivel2;
    #[ORM\Column(name: 'coordenada_x', type: 'integer')] private int $coordenadaX;
    #[ORM\Column(name: 'coordenada_y', type: 'integer')] private int $coordenadaY;
    #[ORM\ManyToOne(targetEntity: TipoBus::class)] #[ORM\JoinColumn(name: 'tipoBus_id', referencedColumnName: 'id')] private ?TipoBus $tipoBus = null;
    #[ORM\ManyToOne(targetEntity: TipoSenal::class)] #[ORM\JoinColumn(name: 'tipo_id', referencedColumnName: 'id')] private ?TipoSenal $tipo = null;
    public function getId(): ?int { return $this->id; }
}
