<?php

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\PersonaBase;
use Doctrine\ORM\Mapping as ORM;

/**
 * La búsqueda de la pantalla de venta (`App\Venta\Clientes::buscar`) se apoya
 * además en dos índices que Doctrine no sabe expresar y viven solo en
 * migraciones (`nat_cliente_nit_prefijo`, `nat_cliente_busqueda`; ADR-056).
 */
#[ORM\Entity]
#[ORM\Index(columns: ["nit"], name: "idx_cliente_nit")]
#[ORM\Index(columns: ["numero_documento"], name: "idx_cliente_documento")]
#[ORM\Index(columns: ["comodin"], name: "idx_cliente_comodin", options: ["where" => "comodin"])]
#[ApiResourcePaginationPage()]
#[Recurso(nombre: "Clientes", area: "Venta")]
class Cliente extends PersonaBase {

    public const NOMBRE_COMODIN = "CONSUMIDOR FINAL";

    /** Documento de identificación (opcional): tipo + número. */
    #[ORM\ManyToOne]
    private ?TipoDocumento $tipoDocumento = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $numeroDocumento = null;

    #[ORM\ManyToOne]
    private ?Nacion $nacionalidad = null;

    /** El cliente de quien no da sus datos: uno solo, lo crea `Clientes::comodin`. */
    #[ORM\Column(options: ["default" => false])]
    private bool $comodin = false;

    public static function comodin(): self
    {
        $cliente = new self();
        $cliente->comodin = true;

        return $cliente->setNombre(self::NOMBRE_COMODIN)->setNit("CF");
    }

    public function isComodin(): bool
    {
        return $this->comodin;
    }

    public function getTipoDocumento(): ?TipoDocumento
    {
        return $this->tipoDocumento;
    }

    public function setTipoDocumento(?TipoDocumento $tipoDocumento): static
    {
        $this->tipoDocumento = $tipoDocumento;

        return $this;
    }

    public function getNumeroDocumento(): ?string
    {
        return $this->numeroDocumento;
    }

    public function setNumeroDocumento(?string $numeroDocumento): static
    {
        $this->numeroDocumento = $numeroDocumento;

        return $this;
    }

    public function getNacionalidad(): ?Nacion
    {
        return $this->nacionalidad;
    }

    public function setNacionalidad(?Nacion $nacionalidad): static
    {
        $this->nacionalidad = $nacionalidad;

        return $this;
    }

    /** Nombre completo como se imprime en boletos y facturas. */
    public function getNombreCompleto(): string
    {
        return self::nombreCompletoDe($this->nombre, $this->apellido);
    }
}
