<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'boleto_estado')]
class EstadoBoleto
{
    public const EMITIDO = 1;
    public const CHEQUEADO = 2;
    public const TRANSITO = 3;
    public const ANULADO = 4;
    public const REASIGNADO = 5;
    public const CANCELADO = 6;

    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 40, unique: true)]
    private string $nombre;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'boolean')]
    private bool $activo;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }
}
