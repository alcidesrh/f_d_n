<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'itinerario_ciclico')]
class ItinerarioCiclico extends Itinerario
{
    #[ORM\ManyToOne(targetEntity: DiaSemana::class)]
    #[ORM\JoinColumn(name: 'dia_semana_id', referencedColumnName: 'id')]
    private ?DiaSemana $diaSemana = null;

    #[ORM\ManyToOne(targetEntity: HorarioCiclico::class)]
    #[ORM\JoinColumn(name: 'horario_ciclico_id', referencedColumnName: 'id')]
    private ?HorarioCiclico $horarioCiclico = null;

    public function getHorarioCiclico(): ?HorarioCiclico
    {
        return $this->horarioCiclico;
    }
}
