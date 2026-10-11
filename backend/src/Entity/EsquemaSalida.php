<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Esquema de salidas guardado con un nombre desde el programador (ADR-024):
 * una ruta, las horas del día con su bus y cada cuántos días se repite.
 * Es una plantilla: aplicarlo crea salidas independientes; cambiarlo o
 * borrarlo no toca las salidas ya creadas. Aislado por empresa (TenantFilter);
 * `empresa` nula = esquema de un usuario sin empresa (ve todo).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_esquema_salida_empresa_nombre", columns: ["empresa_id", "nombre"])]
class EsquemaSalida
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $nombre;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Empresa $empresa;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Ruta $ruta;

    #[ORM\Column(type: "smallint", options: ["default" => 1])]
    private int $intervaloDias = 1;

    /** @var Collection<int, EsquemaSalidaMomento> */
    #[ORM\OneToMany(targetEntity: EsquemaSalidaMomento::class, mappedBy: "esquema", cascade: ["persist"], orphanRemoval: true)]
    #[ORM\OrderBy(["hora" => "ASC"])]
    private Collection $momentos;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $actualizadoPor = null;

    #[ORM\Column]
    private \DateTimeImmutable $actualizadoEn;

    public function __construct(string $nombre, ?Empresa $empresa, Ruta $ruta)
    {
        $this->nombre = $nombre;
        $this->empresa = $empresa;
        $this->ruta = $ruta;
        $this->momentos = new ArrayCollection();
        $this->actualizadoEn = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function getRuta(): Ruta
    {
        return $this->ruta;
    }

    public function getIntervaloDias(): int
    {
        return $this->intervaloDias;
    }

    /** @return Collection<int, EsquemaSalidaMomento> */
    public function getMomentos(): Collection
    {
        return $this->momentos;
    }

    public function getActualizadoPor(): ?Usuario
    {
        return $this->actualizadoPor;
    }

    public function getActualizadoEn(): \DateTimeImmutable
    {
        return $this->actualizadoEn;
    }

    /**
     * Reemplaza todo el contenido del esquema (nombre, ruta, intervalo y momentos).
     *
     * @param list<array{hora: string, bus: Bus}> $momentos
     */
    public function reemplazar(string $nombre, Ruta $ruta, int $intervaloDias, array $momentos, ?Usuario $por): void
    {
        $this->nombre = $nombre;
        $this->ruta = $ruta;
        $this->intervaloDias = $intervaloDias;
        $this->momentos->clear();
        foreach ($momentos as $m) {
            $this->momentos->add(new EsquemaSalidaMomento($this, $m["hora"], $m["bus"]));
        }
        $this->actualizadoPor = $por;
        $this->actualizadoEn = new \DateTimeImmutable();
    }
}
