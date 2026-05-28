<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'banco_cuenta')]
class CuentaBanco
{
    #[ORM\Id] #[ORM\Column(type: 'integer')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(name: 'referencia_externa', type: 'string', length: 100, nullable: true)] private ?string $referenciaExterna = null;
    #[ORM\Column(type: 'string', length: 100, nullable: true)] private ?string $nombre = null;
    #[ORM\Column(type: 'boolean', nullable: true)] private ?bool $activo = null;
    #[ORM\ManyToOne(targetEntity: Empresa::class)] #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')] private ?Empresa $empresa = null;
    #[ORM\ManyToOne(targetEntity: Banco::class)] #[ORM\JoinColumn(name: 'banco_id', referencedColumnName: 'id')] private ?Banco $banco = null;
    #[ORM\ManyToOne(targetEntity: Moneda::class)] #[ORM\JoinColumn(name: 'moneda_id', referencedColumnName: 'id')] private ?Moneda $moneda = null;
    public function getId(): ?int { return $this->id; }
}
