<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Credenciales del certificador FEL de una empresa: el certificador asocia
 * cada usuario a un NIT emisor, así que cada empresa usa las suyas. La
 * clave va cifrada (`App\Venta\Facturacion\CredencialesFel`); no es un
 * recurso de la API. Se cargan con `app:fel:credencial` o desde el legado
 * (`factura_emisor`).
 */
#[ORM\Entity]
#[ORM\Table(name: "credencial_fel")]
class CredencialFel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: "CASCADE")]
    private Empresa $empresa;

    #[ORM\Column(length: 100)]
    private string $usuario;

    /** Clave cifrada (base64 de nonce + secretbox). */
    #[ORM\Column(type: "text")]
    private string $claveCifrada;

    public function __construct(Empresa $empresa, string $usuario, string $claveCifrada)
    {
        $this->empresa = $empresa;
        $this->usuario = $usuario;
        $this->claveCifrada = $claveCifrada;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function getUsuario(): string
    {
        return $this->usuario;
    }

    public function getClaveCifrada(): string
    {
        return $this->claveCifrada;
    }

    public function cambiar(string $usuario, string $claveCifrada): void
    {
        $this->usuario = $usuario;
        $this->claveCifrada = $claveCifrada;
    }
}
