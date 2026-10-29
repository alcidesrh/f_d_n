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
 *
 * Automático (ADR-044): el programador de tareas lo mantiene desplegado
 * `horizonteDias` hacia adelante. `ancla` fija la cadencia (un día es de
 * salidas si dista de ella un múltiplo de `intervaloDias`) y
 * `programadoHasta` es la frontera: el último día ya desplegado. Lo que
 * queda detrás de la frontera no se vuelve a desplegar, así que una salida
 * anulada o eliminada a mano no reaparece. `pausadoMotivo`: el programador
 * se detuvo (ruta inactiva, un bus de otra empresa) hasta que se corrija y
 * se guarde de nuevo.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_esquema_salida_empresa_nombre", columns: ["empresa_id", "nombre"])]
class EsquemaSalida
{
    public const HORIZONTE_DEFECTO = 90;
    public const HORIZONTE_MIN = 7;
    public const HORIZONTE_MAX = 180;

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

    #[ORM\Column(options: ["default" => false])]
    private bool $automatico = false;

    #[ORM\Column(type: "smallint", options: ["default" => 90])]
    private int $horizonteDias = self::HORIZONTE_DEFECTO;

    #[ORM\Column(type: "date_immutable", nullable: true)]
    private ?\DateTimeImmutable $ancla = null;

    #[ORM\Column(type: "date_immutable", nullable: true)]
    private ?\DateTimeImmutable $programadoHasta = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pausadoMotivo = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pausadoEn = null;

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

    public function isAutomatico(): bool
    {
        return $this->automatico;
    }

    public function getHorizonteDias(): int
    {
        return $this->horizonteDias;
    }

    public function getAncla(): ?\DateTimeImmutable
    {
        return $this->ancla;
    }

    public function getProgramadoHasta(): ?\DateTimeImmutable
    {
        return $this->programadoHasta;
    }

    public function getPausadoMotivo(): ?string
    {
        return $this->pausadoMotivo;
    }

    public function getPausadoEn(): ?\DateTimeImmutable
    {
        return $this->pausadoEn;
    }

    /**
     * Enciende o ajusta el modo automático. Cambiar la ruta reinicia la
     * frontera (lo de la ruta anterior queda como está); apagarlo la
     * conserva, para que al volver a encenderlo no repita lo ya desplegado.
     * Guardar siempre quita la pausa: se vuelve a intentar con lo nuevo.
     */
    public function automatizar(bool $automatico, int $horizonteDias, ?\DateTimeImmutable $ancla, bool $cambioRuta): void
    {
        if ($cambioRuta) {
            $this->programadoHasta = null;
        }
        $this->automatico = $automatico;
        $this->horizonteDias = $horizonteDias;
        if ($automatico) {
            $this->ancla = ($ancla ?? $this->ancla)?->setTime(0, 0);
        }
        $this->pausadoMotivo = null;
        $this->pausadoEn = null;
    }

    /** La frontera avanza hasta `$dia` (nunca retrocede). */
    public function desplegadoHasta(\DateTimeImmutable $dia): void
    {
        $dia = $dia->setTime(0, 0);
        if ($this->programadoHasta === null || $dia > $this->programadoHasta) {
            $this->programadoHasta = $dia;
        }
    }

    public function pausar(string $motivo, \DateTimeImmutable $en): void
    {
        $this->pausadoMotivo = mb_substr($motivo, 0, 255);
        $this->pausadoEn = $en;
    }
}
