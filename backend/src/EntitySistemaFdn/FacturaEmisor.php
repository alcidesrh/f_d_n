<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'factura_emisor')]
class FacturaEmisor
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(name: 'afiliacion_iva', type: 'string', length: 255, nullable: true)] private ?string $afiliacionIva = null;
    #[ORM\Column(name: 'correo_emisor', type: 'string', length: 255, nullable: true)] private ?string $correoEmisor = null;
    #[ORM\Column(name: 'nit_emisor', type: 'string', length: 255, nullable: true)] private ?string $nitEmisor = null;
    #[ORM\Column(name: 'nombre_comercial', type: 'string', length: 255, nullable: true)] private ?string $nombreComercial = null;
    #[ORM\Column(name: 'nombre_emisor', type: 'string', length: 255, nullable: true)] private ?string $nombreEmisor = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)] private ?string $direccion = null;
    #[ORM\Column(name: 'codigo_postal', type: 'string', length: 255, nullable: true)] private ?string $codigoPostal = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)] private ?string $departamento = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)] private ?string $pais = null;
    #[ORM\Column(type: 'boolean', nullable: true)] private ?bool $activo = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)] private ?string $userForcon = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)] private ?string $passwordForcon = null;
    #[ORM\ManyToOne(targetEntity: Empresa::class)] #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')] private ?Empresa $empresa = null;
    public function getId(): ?int { return $this->id; }
}
