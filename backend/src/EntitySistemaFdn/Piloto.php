<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'piloto')]
class Piloto
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 10, unique: true)]
    private string $codigo;

    #[ORM\Column(name: 'nombre', type: 'string', length: 20)]
    private string $nombre1;

    #[ORM\Column(name: 'nombre2', type: 'string', length: 20, nullable: true)]
    private ?string $nombre2 = null;

    #[ORM\Column(name: 'apellidos', type: 'string', length: 40, nullable: true)]
    private ?string $apellido1 = null;

    #[ORM\Column(name: 'apellido2', type: 'string', length: 40, nullable: true)]
    private ?string $apellido2 = null;

    #[ORM\Column(name: 'fechaNacimiento', type: 'date', nullable: true)]
    private ?\DateTime $fechaNacimiento = null;

    #[ORM\Column(name: 'numeroLicencia', type: 'string', length: 40)]
    private string $numeroLicencia;

    #[ORM\Column(name: 'fechaVencimientoLicencia', type: 'date', nullable: true)]
    private ?\DateTime $fechaVencimientoLicencia = null;

    #[ORM\Column(type: 'string', length: 40)]
    private string $dpi;

    #[ORM\Column(name: 'seguroSocial', type: 'string', length: 50, nullable: true)]
    private ?string $seguroSocial = null;

    #[ORM\Column(type: 'string', length: 15, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(type: 'boolean')]
    private bool $activo;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')]
    private ?Empresa $empresa = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getNombre1(): string
    {
        return $this->nombre1;
    }

    public function getNombre2(): ?string
    {
        return $this->nombre2;
    }

    public function getApellido1(): ?string
    {
        return $this->apellido1;
    }

    public function getApellido2(): ?string
    {
        return $this->apellido2;
    }

    public function getNumeroLicencia(): string
    {
        return $this->numeroLicencia;
    }

    public function getFechaNacimiento(): ?\DateTime
    {
        return $this->fechaNacimiento;
    }

    public function getFechaVencimientoLicencia(): ?\DateTime
    {
        return $this->fechaVencimientoLicencia;
    }

    public function getDpi(): string
    {
        return $this->dpi;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function getActivo(): bool
    {
        return $this->activo;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function getSeguroSocial(): ?string
    {
        return $this->seguroSocial;
    }
}
