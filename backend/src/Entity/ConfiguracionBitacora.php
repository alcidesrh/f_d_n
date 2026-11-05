<?php

declare(strict_types=1);

namespace App\Entity;

use App\Actividad\NivelActividad;
use App\Actividad\Seleccion;
use App\Actividad\TipoActividad;
use Doctrine\ORM\Mapping as ORM;

/**
 * Configuración de la bitácora de actividad de los usuarios: una sola fila.
 * Sin fila valen los valores por defecto. Se lee con
 * `App\Actividad\AjustesBitacora` y se edita desde la administración
 * (`App\Actividad\GestionBitacora`, capacidad `bitacora.configurar`); no es un
 * recurso de la API.
 */
#[ORM\Entity]
class ConfiguracionBitacora
{
    public const ID = 1;

    public const ALCANCE_TODOS = "todos";
    public const ALCANCE_SELECCIONADOS = "seleccionados";

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    /** Apagada, solo queda la bitácora de negocio (`bitacora`). */
    #[ORM\Column(options: ["default" => true])]
    private bool $activa = true;

    /** `NivelActividad`. */
    #[ORM\Column(length: 10, options: ["default" => "medio"])]
    private string $nivel = "medio";

    /**
     * Familias que no se registran aunque el nivel las incluya (`TipoActividad`).
     *
     * @var list<string>
     */
    #[ORM\Column(type: "json", options: ["default" => "[]"])]
    private array $familiasApagadas = [];

    /** `todos` o `seleccionados`. */
    #[ORM\Column(length: 15, options: ["default" => "todos"])]
    private string $alcance = self::ALCANCE_TODOS;

    /**
     * Entradas de `App\Actividad\Seleccion`.
     *
     * @var list<array{tipo: string, id: int, desde: ?string, hasta: ?string}>
     */
    #[ORM\Column(type: "json", options: ["default" => "[]"])]
    private array $seleccion = [];

    /** Meses que se conserva el detalle de `actividad` (1 a 60). */
    #[ORM\Column(options: ["default" => 2])]
    private int $retencionMeses = 2;

    /** Antes de borrar un mes, se exporta a CSV comprimido. */
    #[ORM\Column(options: ["default" => true])]
    private bool $exportarAntesDeBorrar = true;

    /** Meses que se conserva el resumen diario; 0 = sin límite. */
    #[ORM\Column(options: ["default" => 0])]
    private int $retencionResumenMeses = 0;

    /** Hora (`HH:MM`, America/Guatemala) desde la que se resume el día anterior. */
    #[ORM\Column(length: 5, options: ["default" => "00:15"])]
    private string $horaResumen = "00:15";

    /** Cada cuántos segundos la SPA manda su lote de acciones de pantalla. */
    #[ORM\Column(options: ["default" => 30])]
    private int $intervaloPantallaSegundos = 30;

    /** Tamaño máximo del detalle de una fila, en KB. */
    #[ORM\Column(options: ["default" => 4])]
    private int $maxDetalleKb = 4;

    /**
     * Campos del cuerpo de las peticiones que se suman a la lista blanca.
     *
     * @var list<string>
     */
    #[ORM\Column(type: "json", options: ["default" => "[]"])]
    private array $camposExtra = [];

    /** Cambios campo a campo de todas las entidades en la bitácora de negocio. */
    #[ORM\Column(options: ["default" => true])]
    private bool $cambiosEntidades = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $actualizadaEn = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $actualizadaPor = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function isActiva(): bool
    {
        return $this->activa;
    }

    public function setActiva(bool $activa): static
    {
        $this->activa = $activa;

        return $this;
    }

    public function getNivel(): NivelActividad
    {
        return NivelActividad::tryFrom($this->nivel) ?? NivelActividad::MEDIO;
    }

    public function setNivel(NivelActividad $nivel): static
    {
        $this->nivel = $nivel->value;

        return $this;
    }

    /** @return list<TipoActividad> */
    public function getFamiliasApagadas(): array
    {
        return array_values(array_filter(array_map(static fn(string $f) => TipoActividad::tryFrom($f), $this->familiasApagadas)));
    }

    /** @param list<TipoActividad> $familias */
    public function setFamiliasApagadas(array $familias): static
    {
        $this->familiasApagadas = array_values(array_unique(array_map(static fn(TipoActividad $f) => $f->value, $familias)));

        return $this;
    }

    public function getAlcance(): string
    {
        return $this->alcance === self::ALCANCE_SELECCIONADOS ? self::ALCANCE_SELECCIONADOS : self::ALCANCE_TODOS;
    }

    public function setAlcance(string $alcance): static
    {
        $this->alcance = $alcance === self::ALCANCE_SELECCIONADOS ? self::ALCANCE_SELECCIONADOS : self::ALCANCE_TODOS;

        return $this;
    }

    public function getSeleccion(): Seleccion
    {
        return new Seleccion($this->seleccion);
    }

    public function setSeleccion(Seleccion $seleccion): static
    {
        $this->seleccion = $seleccion->entradas();

        return $this;
    }

    public function getRetencionMeses(): int
    {
        return $this->retencionMeses;
    }

    public function setRetencionMeses(int $meses): static
    {
        $this->retencionMeses = $meses;

        return $this;
    }

    public function isExportarAntesDeBorrar(): bool
    {
        return $this->exportarAntesDeBorrar;
    }

    public function setExportarAntesDeBorrar(bool $exportar): static
    {
        $this->exportarAntesDeBorrar = $exportar;

        return $this;
    }

    public function getRetencionResumenMeses(): int
    {
        return $this->retencionResumenMeses;
    }

    public function setRetencionResumenMeses(int $meses): static
    {
        $this->retencionResumenMeses = $meses;

        return $this;
    }

    public function getHoraResumen(): string
    {
        return $this->horaResumen;
    }

    public function setHoraResumen(string $hora): static
    {
        $this->horaResumen = $hora;

        return $this;
    }

    public function getIntervaloPantallaSegundos(): int
    {
        return $this->intervaloPantallaSegundos;
    }

    public function setIntervaloPantallaSegundos(int $segundos): static
    {
        $this->intervaloPantallaSegundos = $segundos;

        return $this;
    }

    public function getMaxDetalleKb(): int
    {
        return $this->maxDetalleKb;
    }

    public function setMaxDetalleKb(int $kb): static
    {
        $this->maxDetalleKb = $kb;

        return $this;
    }

    /** @return list<string> */
    public function getCamposExtra(): array
    {
        return $this->camposExtra;
    }

    /** @param list<string> $campos */
    public function setCamposExtra(array $campos): static
    {
        $this->camposExtra = array_values(array_unique($campos));

        return $this;
    }

    public function isCambiosEntidades(): bool
    {
        return $this->cambiosEntidades;
    }

    public function setCambiosEntidades(bool $cambios): static
    {
        $this->cambiosEntidades = $cambios;

        return $this;
    }

    public function getActualizadaEn(): ?\DateTimeImmutable
    {
        return $this->actualizadaEn;
    }

    public function getActualizadaPor(): ?Usuario
    {
        return $this->actualizadaPor;
    }

    public function actualizada(?Usuario $por, \DateTimeImmutable $en): static
    {
        $this->actualizadaPor = $por;
        $this->actualizadaEn = $en;

        return $this;
    }
}
