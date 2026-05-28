<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'calendario_factura_fecha')]
class CalendarioFacturaFecha
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'date')] private \DateTime $fecha;
    #[ORM\ManyToOne(targetEntity: CalendarioFacturaRuta::class)] #[ORM\JoinColumn(name: 'calendario_factura_ruta_id', referencedColumnName: 'id')] private ?CalendarioFacturaRuta $calendarioFacturaRuta = null;
    #[ORM\ManyToOne(targetEntity: Empresa::class)] #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')] private ?Empresa $empresa = null;
    public function getId(): ?int { return $this->id; }
}
