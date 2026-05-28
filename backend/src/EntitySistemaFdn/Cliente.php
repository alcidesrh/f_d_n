<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'cliente')]
class Cliente
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $nit = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $dpi = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $nombre;

    #[ORM\Column(name: 'nombre1', type: 'string', length: 50, nullable: true)]
    private ?string $nombre1 = null;

    #[ORM\Column(name: 'nombre2', type: 'string', length: 50, nullable: true)]
    private ?string $nombre2 = null;

    #[ORM\Column(name: 'apellido1', type: 'string', length: 50, nullable: true)]
    private ?string $apellido1 = null;

    #[ORM\Column(name: 'apellido2', type: 'string', length: 50, nullable: true)]
    private ?string $apellido2 = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $direccion = null;

    #[ORM\Column(type: 'string', length: 21, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $correo = null;

    #[ORM\Column(name: 'fecha_vencimiento_documento', type: 'date', nullable: true)]
    private ?\DateTime $fechaVencimientoDocumento = null;

    #[ORM\Column(name: 'empleado', type: 'boolean')]
    private bool $empleado = false;

    #[ORM\Column(name: 'fecha_nacimiento', type: 'date', nullable: true)]
    private ?\DateTime $fechaNacimiento = null;

    #[ORM\Column(name: 'nitCreacionCopia', type: 'string', length: 20, nullable: true)]
    private ?string $nitCreacionCopia = null;

    #[ORM\Column(name: 'nombreCreacionCopia', type: 'string', length: 255, nullable: true)]
    private ?string $nombreCreacionCopia = null;

    #[ORM\Column(name: 'detallado', type: 'boolean')]
    private bool $detallado = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $fechaCreacion = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNit(): ?string
    {
        return $this->nit;
    }

    public function getDpi(): ?string
    {
        return $this->dpi;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getNombre1(): ?string
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

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function getCorreo(): ?string
    {
        return $this->correo;
    }

    public function getFechaCreacion(): ?\DateTime
    {
        return $this->fechaCreacion;
    }
}
