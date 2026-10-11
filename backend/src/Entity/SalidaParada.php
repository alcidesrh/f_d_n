<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\PrecisionHora;
use Doctrine\ORM\Mapping as ORM;

/**
 * Horario de una salida en una parada de su ruta (ADR-030): a qué hora pasa.
 * Es una foto (`App\Ruta\Horarios`): se fija al crear la salida y al cambiar
 * su fecha, hora o bus; las salidas con boletos la conservan aunque cambie la
 * ruta. `enclave` se repite aquí para buscar por parada y hora con un índice.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_salida_parada_orden", columns: ["salida_id", "orden"])]
#[ORM\Index(columns: ["enclave_id", "hora"], name: "idx_salida_parada_enclave_hora")]
class SalidaParada
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "horario")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Salida $salida;

    #[ORM\Column(type: "smallint")]
    private int $orden;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Enclave $enclave;

    #[ORM\Column]
    private \DateTimeImmutable $hora;

    #[ORM\Column(length: 12, enumType: PrecisionHora::class)]
    private PrecisionHora $precision;

    #[ORM\Column(options: ["default" => true])]
    private bool $permiteSubir = true;

    #[ORM\Column(options: ["default" => true])]
    private bool $permiteBajar = true;

    public function __construct(Salida $salida, int $orden, Enclave $enclave, \DateTimeImmutable $hora, PrecisionHora $precision)
    {
        $this->salida = $salida;
        $this->orden = $orden;
        $this->enclave = $enclave;
        $this->hora = $hora;
        $this->precision = $precision;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSalida(): Salida
    {
        return $this->salida;
    }

    public function getOrden(): int
    {
        return $this->orden;
    }

    public function getEnclave(): Enclave
    {
        return $this->enclave;
    }

    public function getHora(): \DateTimeImmutable
    {
        return $this->hora;
    }

    public function getPrecision(): PrecisionHora
    {
        return $this->precision;
    }

    public function permiteSubir(): bool
    {
        return $this->permiteSubir;
    }

    public function permiteBajar(): bool
    {
        return $this->permiteBajar;
    }
}
