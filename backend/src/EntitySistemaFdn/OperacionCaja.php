<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'caja_operacion')]
class OperacionCaja
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $importe;
    #[ORM\Column(type: 'datetime')] private \DateTime $fecha;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $descripcion = null;
    #[ORM\ManyToOne(targetEntity: Caja::class)] #[ORM\JoinColumn(name: 'caja_id', referencedColumnName: 'id')] private ?Caja $caja = null;
    #[ORM\ManyToOne(targetEntity: TipoOperacionCaja::class)] #[ORM\JoinColumn(name: 'tipo_operacion_id', referencedColumnName: 'id')] private ?TipoOperacionCaja $tipoOperacion = null;
    #[ORM\ManyToOne(targetEntity: Empresa::class)] #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')] private ?Empresa $empresa = null;
    public function getId(): ?int { return $this->id; }
}
