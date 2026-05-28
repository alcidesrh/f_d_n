<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'impresora_auxiliares')]
class ImpresoraOperaciones
{
    #[ORM\Id] #[ORM\Column(type: 'integer')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    #[ORM\ManyToOne(targetEntity: Impresora::class)] #[ORM\JoinColumn(name: 'impresora_boleto_id', referencedColumnName: 'id')] private ?Impresora $impresoraBoleto = null;
    #[ORM\ManyToOne(targetEntity: Impresora::class)] #[ORM\JoinColumn(name: 'impresora_encomienda_id', referencedColumnName: 'id')] private ?Impresora $impresoraEncomienda = null;
    public function getId(): ?int { return $this->id; }
}
