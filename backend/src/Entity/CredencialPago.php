<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Comercio de una empresa en la pasarela de pago (Cybersource): cada empresa
 * cobra a su propia cuenta. La llave secreta va cifrada
 * (`App\Venta\Pago\Cybersource\CredencialesCybersource`); no es un recurso de
 * la API. Se cargan con `app:pago:credencial`.
 */
#[ORM\Entity]
#[ORM\Table(name: "credencial_pago")]
class CredencialPago
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: "CASCADE")]
    private Empresa $empresa;

    /** Merchant ID. */
    #[ORM\Column(length: 100)]
    private string $comercio;

    /** Id de la llave compartida (key id). */
    #[ORM\Column(length: 100)]
    private string $llave;

    /** Llave secreta compartida (base64), cifrada. */
    #[ORM\Column(type: "text")]
    private string $secretoCifrado;

    public function __construct(Empresa $empresa, string $comercio, string $llave, string $secretoCifrado)
    {
        $this->empresa = $empresa;
        $this->cambiar($comercio, $llave, $secretoCifrado);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function getComercio(): string
    {
        return $this->comercio;
    }

    public function getLlave(): string
    {
        return $this->llave;
    }

    public function getSecretoCifrado(): string
    {
        return $this->secretoCifrado;
    }

    public function cambiar(string $comercio, string $llave, string $secretoCifrado): void
    {
        $this->comercio = $comercio;
        $this->llave = $llave;
        $this->secretoCifrado = $secretoCifrado;
    }
}
