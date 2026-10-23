<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Departamento, estado o provincia de un país: la dirección de facturación
 * de la tarjeta en la página web se elige de una lista (ADR-041). Catálogo
 * importado del dataset de la página anterior (`app:geografia:importar`);
 * nada lo referencia por clave foránea, así que se reimporta entero.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["pais"], name: "idx_region_pais")]
class Region
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** ISO 3166-1 alfa-2. */
    #[ORM\Column(length: 2, options: ["fixed" => true])]
    private string $pais;

    /** Código ISO 3166-2 sin el país (`PE`, `CA`, `TX`); el que piden los bancos en EE. UU. y Canadá. */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codigo;

    #[ORM\Column(length: 100)]
    private string $nombre;

    public function __construct(string $pais, ?string $codigo, string $nombre)
    {
        $this->pais = $pais;
        $this->codigo = $codigo;
        $this->nombre = $nombre;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPais(): string
    {
        return $this->pais;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }
}
