<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'calendario_factura_ruta')]
class CalendarioFacturaRuta
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'boolean')] private bool $constante;
    #[ORM\ManyToOne(targetEntity: Ruta::class)] #[ORM\JoinColumn(name: 'ruta_codigo', referencedColumnName: 'codigo')] private ?Ruta $ruta = null;
    #[ORM\ManyToOne(targetEntity: Empresa::class)] #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')] private ?Empresa $empresa = null;
    public function getId(): ?int { return $this->id; }
}
