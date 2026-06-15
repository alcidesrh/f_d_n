<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\PersonaBase;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourcePaginationPage()]
class Piloto extends PersonaBase {

    #[ORM\Column(name: 'fechaNacimiento', type: 'date', nullable: true)]
    private ?\DateTime $fechaNacimiento = null;

    #[ORM\Column(name: 'numeroLicencia', type: 'string', length: 40, nullable: true)]
    private ?string $numeroLicencia = null;

    #[ORM\Column(name: 'fechaVencimientoLicencia', type: 'date', nullable: true)]
    private ?\DateTime $fechaVencimientoLicencia = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $dpi = null;

    #[ORM\Column(name: 'seguroSocial', type: 'string', length: 50, nullable: true)]
    private ?string $seguroSocial = null;

    #[ORM\Column(type: 'string', length: 10, unique: true)]
    private string $codigo;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')]
    private ?Empresa $empresa = null;

    public function getCodigo(): string {
        return $this->codigo;
    }

    public function getNumeroLicencia(): string {
        return $this->numeroLicencia;
    }

    public function getFechaNacimiento(): ?\DateTime {
        return $this->fechaNacimiento;
    }

    public function getFechaVencimientoLicencia(): ?\DateTime {
        return $this->fechaVencimientoLicencia;
    }

    public function getDpi(): string {
        return $this->dpi;
    }

    public function getEmpresa(): ?Empresa {
        return $this->empresa;
    }


    public function getTelefono(): ?string {
        return $this->telefono;
    }

    public function getSeguroSocial(): ?string {
        return $this->seguroSocial;
    }
}
