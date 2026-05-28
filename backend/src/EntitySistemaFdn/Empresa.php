<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'empresa')]
class Empresa
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 15, unique: true, nullable: true)]
    private ?string $alias = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $nombre;

    #[ORM\Column(name: 'nombreComercial', type: 'string', length: 255, unique: true)]
    private string $nombreComercial;

    #[ORM\Column(name: 'denominacionSocial', type: 'string', length: 255)]
    private string $denominacionSocial;

    #[ORM\Column(type: 'text')]
    private string $direccion;

    #[ORM\Column(type: 'string', length: 20, unique: true)]
    private string $nit;

    #[ORM\Column(name: 'formaPagoISR', type: 'string', length: 255)]
    private string $formaPagoISR;

    #[ORM\Column(name: 'representanteLegal', type: 'string', length: 255)]
    private string $representanteLegal;

    #[ORM\Column(name: 'id_externo', type: 'bigint', nullable: true)]
    private ?int $idExterno = null;

    #[ORM\Column(name: 'reportar_boleto_facturado', type: 'boolean')]
    private bool $reportarBoletoFacturado = false;

    #[ORM\Column(name: 'id_producto_boleto_externo', type: 'bigint', nullable: true)]
    private ?int $idProductoBoletoExterno = null;

    #[ORM\Column(name: 'reportar_encomienda_facturado', type: 'boolean')]
    private bool $reportarEncomiendaFacturado = false;

    #[ORM\Column(name: 'id_producto_encomienda_externo', type: 'bigint', nullable: true)]
    private ?int $idProductoEncomiendaExterno = null;

    #[ORM\Column(name: 'obligatorio_control_tarjetas', type: 'boolean')]
    private bool $obligatorioControlTarjetas = false;

    #[ORM\Column(name: 'no_bono', type: 'string', length: 50, nullable: true)]
    private ?string $noBono = null;

    #[ORM\Column(type: 'string', length: 6, unique: true)]
    private string $color;

    #[ORM\Column(name: 'telefonos', type: 'text')]
    private string $telefonos;

    #[ORM\Column(name: 'logo', type: 'text', nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(name: 'id_usuario_externo', type: 'bigint', nullable: true)]
    private ?int $idUsuarioExterno = null;

    #[ORM\Column(name: 'id_cliente_externo', type: 'bigint', nullable: true)]
    private ?int $idClienteExterno = null;

    #[ORM\Column(name: 'url_externo', type: 'string', length: 255, nullable: true)]
    private ?string $urlExterno = null;

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

    public function getNombreComercial(): string
    {
        return $this->nombreComercial;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function getActivo(): bool
    {
        return $this->activo;
    }
}
