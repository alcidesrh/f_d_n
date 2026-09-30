<?php

namespace App\Entity;

use App\Repository\SalidaReservacionRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use IntlDateFormatter;

#[ORM\Entity(repositoryClass: SalidaReservacionRepository::class)]
class SalidaReservacion {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bus_clase = null;

    #[ORM\Column(nullable: true)]
    private ?int $minutos = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $salida_id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE,)]
    private \DateTimeInterface $salida_fecha;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $hora = null;

    #[ORM\OneToMany(mappedBy: 'salidaReservacion', targetEntity: Asiento::class, orphanRemoval: true)]
    private Collection $asientos;

    #[ORM\ManyToOne]
    private ?Empresa $empresa = null;

    #[ORM\OneToOne(mappedBy: 'salida', cascade: ['persist'])]
    private ?Reservacion $salida_reservacion = null;

    #[ORM\OneToOne(mappedBy: 'regreso', cascade: ['persist'])]
    private ?Reservacion $regreso_reservacion = null;

    #[ORM\Column(nullable: true)]
    private ?bool $regreso = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $clienteNota = null;

    public function reset() {
        $this->hora = $this->salida_id = $this->bus_clase = $this->minutos = null;
    }

    public function __construct() {
        $this->regreso = false;
        $this->asientos = new ArrayCollection();
        $this->salida_fecha = new \DateTime();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function setId(int $id): self {
        $this->id = $id;

        return $this;
    }

    public function getBusClase(): ?string {
        return $this->bus_clase;
    }

    public function setBusClase(?string $bus_clase): self {
        $this->bus_clase = $bus_clase;

        return $this;
    }

    public function getMinutos(): ?int {
        return $this->minutos;
    }

    public function setMinutos(?int $minutos): self {
        $this->minutos = $minutos;

        return $this;
    }

    public function getSalidaId(): ?string {
        return $this->salida_id;
    }

    public function setSalidaId(?string $salida_id): self {
        $this->salida_id = $salida_id;

        return $this;
    }

    public function getSalidaFecha(): ?\DateTime {
        return $this->salida_fecha;
    }

    public function getSalidaFechaFactura($locale = 'es', $format = false): ?string {

        $aux = $locale == 'es' ? ' de ' : ' of ';

        if ($format) {

            if ($format == 'hora') {

                return (new DateTime($this->hora))->format('h:i a');
            } else if ($format == 'fecha') {

                $fecha =  ucfirst(IntlDateFormatter::formatObject($this->getSalidaFechaConHora(), "cccc, dd '$aux'", $locale));
            }
        }
        if (!isset($fecha)) {
            $fecha = (new DateTime($this->hora))->format('h:i a') . ' ' .
                ucfirst(IntlDateFormatter::formatObject($this->getSalidaFechaConHora(), "cccc dd '$aux'", $locale));
        }
        return $fecha .
            ucfirst(IntlDateFormatter::formatObject($this->getSalidaFechaConHora(), "MMMM'.'", $locale));
    }


    public function getSalidaFechaConHora(): ?\DateTime {

        $hora = new DateTime($this->hora);

        $this->salida_fecha->setTime($hora->format('H'), $hora->format('i'));

        return $this->salida_fecha;
    }

    public function setSalidaFecha(\DateTimeInterface $salida_fecha): self {
        $this->salida_fecha = $salida_fecha;

        return $this;
    }

    public function getHora(): ?string {
        return $this->hora;
    }

    public function setHora($hora): self {
        $this->hora = $hora;

        return $this;
    }

    /**
     * @return Collection<int, Asiento>
     */
    public function getAsientos(): Collection {
        return $this->asientos;
    }

    public function addAsiento(Asiento $asiento): self {

        if (!$this->asientos->contains($asiento)) {

            $this->asientos->add($asiento);

            $asiento->setSalidaReservacion($this);
        }

        return $this;
    }

    public function setAsientos($asientos = null) {

        $this->asientos = $asientos ?? new ArrayCollection();

        return $this;
    }

    public function removeAsiento(Asiento $asiento): self {

        if ($this->asientos->removeElement($asiento)) {

            if ($asiento->getSalidaReservacion() === $this) {

                $asiento->setSalidaReservacion(null);
            }
        }

        return $this;
    }

    public function getEmpresa(): ?Empresa {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): self {
        $this->empresa = $empresa;

        return $this;
    }

    /**
     * Get the value of salida_reservacion
     */
    public function getSalida_reservacion() {
        return $this->salida_reservacion;
    }

    /**
     * Set the value of salida_reservacion
     *
     * @return  self
     */
    public function setSalida_reservacion($salida_reservacion) {
        $this->salida_reservacion = $salida_reservacion;

        return $this;
    }

    /**
     * Get the value of regreso_reservacion
     */
    public function getRegreso_reservacion() {
        return $this->regreso_reservacion;
    }

    /**
     * Set the value of regreso_reservacion
     *
     * @return  self
     */
    public function setRegreso_reservacion($regreso_reservacion) {
        $this->regreso_reservacion = $regreso_reservacion;

        return $this;
    }

    public function getReservacion(): ?Reservacion {
        return $this->salida_reservacion ?? $this->regreso_reservacion;
    }

    /**
     * Get the value of regreso
     */
    public function getRegreso() {
        return (int) $this->regreso;
    }

    /**
     * Set the value of regreso
     *
     * @return  self
     */
    public function setRegreso($regreso) {
        $this->regreso = $regreso;

        return $this;
    }

    public function getAsientosIds() {

        return $this->asientos->map(fn (Asiento $asiento) => \intval($asiento->getAsientoId()))->toArray();
    }

    public function getAsientosCantidad(): int {

        return $this->asientos?->count() ?? 0;
    }

    public function __toString() {
        return $this->salida_fecha->format('dd/MM/yyyy') . ' ' . $this->hora;
    }

    public function getClienteNota(): ?string
    {
        return $this->clienteNota;
    }

    public function setClienteNota(?string $clienteNota): static
    {
        $this->clienteNota = $clienteNota;

        return $this;
    }
}
