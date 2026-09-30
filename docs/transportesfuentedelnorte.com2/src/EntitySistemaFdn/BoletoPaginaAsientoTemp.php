<?php

namespace App\EntitySistemaFdn;

use App\Repository\BoletoPaginaAsientoTempRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoletoPaginaAsientoTempRepository::class)]
class BoletoPaginaAsientoTemp {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: "reservacion_id", type: Types::BIGINT)]
    private ?string $reservacion = null;

    #[ORM\Column(name: "asiento_id", type: Types::BIGINT)]
    private ?string $asiento = null;

    #[ORM\Column(name: "boleto_pagina_temp_id", type: Types::BIGINT)]
    private ?string $boleto_pagina_temp = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fecha_creacion = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getAsiento(): ?string {
        return $this->asiento;
    }

    public function setAsiento(string $asiento): self {
        $this->asiento = $asiento;

        return $this;
    }

    /**
     * Get the value of reservacion
     */
    public function getReservacion() {
        return $this->reservacion;
    }

    /**
     * Set the value of reservacion
     *
     * @return  self
     */
    public function setReservacion($reservacion) {
        $this->reservacion = $reservacion;

        return $this;
    }

    /**
     * Get the value of boleto_pagina_temp
     */
    public function getBoletoPaginaTemp() {
        return $this->boleto_pagina_temp;
    }

    /**
     * Set the value of boleto_pagina_temp
     *
     * @return  self
     */
    public function setBoletoPaginaTemp($boleto_pagina_temp) {
        $this->boleto_pagina_temp = $boleto_pagina_temp;

        return $this;
    }

    /**
     * Get the value of fecha_creacion
     */
    public function getFecha_creacion() {
        return $this->fecha_creacion;
    }

    /**
     * Set the value of fecha_creacion
     *
     * @return  self
     */
    public function setFecha_creacion($fecha_creacion) {
        $this->fecha_creacion = $fecha_creacion;

        return $this;
    }
}
