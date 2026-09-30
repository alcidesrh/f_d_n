<?php

namespace App\Entity;

use App\Repository\ConfiguracionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfiguracionRepository::class)]
class Configuracion {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?float $compra_porciento = null;

    #[ORM\Column(nullable: true)]
    private ?float $dolar_cambio = null;

    #[ORM\Column(nullable: true)]
    private ?bool $desactivar_pagina = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getCompraPorciento(): ?float {
        return $this->compra_porciento;
    }

    public function setCompraPorciento(?float $compra_porciento): self {
        $this->compra_porciento = $compra_porciento;

        return $this;
    }

    public function getDolarCambio(): ?float {
        return $this->dolar_cambio;
    }

    public function setDolarCambio(?float $dolar_cambio): self {
        $this->dolar_cambio = $dolar_cambio;

        return $this;
    }

    public function isDesactivarPagina(): ?bool {
        return $this->desactivar_pagina;
    }

    public function setDesactivarPagina(?bool $activo): self {
        $this->desactivar_pagina = $activo;

        return $this;
    }
}
