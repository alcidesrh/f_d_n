<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'ruta_estacion_item')]
class RutaEstacionItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $posicion;

    #[ORM\ManyToOne(targetEntity: Ruta::class, inversedBy: 'listaEstacionesIntermediaOrdenadas')]
    #[ORM\JoinColumn(name: 'ruta_codigo', referencedColumnName: 'codigo')]
    private ?Ruta $ruta = null;

    #[ORM\ManyToOne(targetEntity: Estacion::class)]
    #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')]
    private ?Estacion $estacion = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosicion(): int
    {
        return $this->posicion;
    }

    public function getRuta(): ?Ruta
    {
        return $this->ruta;
    }

    public function getEstacion(): ?Estacion
    {
        return $this->estacion;
    }
}
