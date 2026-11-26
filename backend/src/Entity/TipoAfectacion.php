<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use App\Afectacion\Validacion\CapacidadDelCatalogo;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Entity\Enum\PrioridadAfectacion;
use App\Entity\Enum\SujetoAfectacion;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un tipo de afectación de la logística (ADR-070): impresora averiada, papel
 * agotado, caída de internet, avería de un bus… Dice sobre qué cae
 * (`sujeto`), quién la reporta (`owner`, una capacidad), quién la atiende
 * (`target`), qué tan grave es y cómo la vigila el centinela.
 *
 * El centinela avisa a quien tiene `target` donde cae la afectación apenas
 * se crea. Si pasan `escalarTrasMinutos` desde que se creó sin resolverse,
 * vuelve a avisar a `target` y además a quien tiene alguna capacidad de
 * `escalamiento`, y repite ese aviso cada `intervaloMinutos` hasta que se
 * resuelve.
 *
 * `codigo` es la clave estable con la que un detector la crea sola
 * (`Afectaciones::detectar`). Un tipo inactivo no se puede reportar; las
 * suyas que siguen abiertas se siguen vigilando. Con afectaciones no se
 * borra (desactívelo).
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
#[Recurso(nombre: "Tipos de afectación", area: "Logística", lecturaLibre: true, editar: "afectacion.tipos", eliminar: "afectacion.tipos")]
class TipoAfectacion extends Base
{
    /** El centinela corre cada minuto: no tiene sentido insistir más seguido. */
    public const INTERVALO_MINIMO = 5;

    #[Assert\NotBlank(message: "El código es obligatorio.")]
    #[Assert\Regex(pattern: "/^[a-z][a-z0-9_]*$/", message: "El código va en minúsculas, sin espacios ni acentos (impresora_averiada).")]
    #[Assert\Length(max: 60)]
    #[ORM\Column(length: 60, unique: true)]
    private string $codigo = "";

    #[Assert\NotBlank(message: "El nombre es obligatorio.")]
    #[Assert\Length(max: 80)]
    #[ORM\Column(length: 80, unique: true)]
    private string $nombre = "";

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 12, enumType: SujetoAfectacion::class, options: ["default" => "estacion"])]
    private SujetoAfectacion $sujeto = SujetoAfectacion::ESTACION;

    /** Capacidad para reportarla (crearla), donde cae. */
    #[Assert\NotBlank(message: "Indique qué capacidad la reporta.")]
    #[CapacidadDelCatalogo]
    #[ORM\Column(length: 80)]
    private string $owner = "afectacion.reportar";

    /** Capacidad para atenderla (editarla y resolverla), donde cae; recibe el primer aviso. */
    #[Assert\NotBlank(message: "Indique qué capacidad la atiende.")]
    #[CapacidadDelCatalogo]
    #[ORM\Column(length: 80)]
    private string $target = "afectacion.informatica";

    #[ORM\Column(length: 12, enumType: PrioridadAfectacion::class, options: ["default" => "normal"])]
    private PrioridadAfectacion $prioridad = PrioridadAfectacion::NORMAL;

    /** Cada cuántos minutos repite el aviso una vez escalada. */
    #[Assert\GreaterThanOrEqual(value: self::INTERVALO_MINIMO, message: "El centinela insiste cada 5 minutos o más.")]
    #[ORM\Column(options: ["default" => 1440])]
    private int $intervaloMinutos = 1440;

    /** Minutos sin resolver, desde que se creó, tras los que empieza el escalamiento. */
    #[Assert\PositiveOrZero]
    #[ORM\Column(options: ["default" => 4320])]
    private int $escalarTrasMinutos = 4320;

    /**
     * Capacidades que se suman a `target` desde el escalamiento.
     *
     * @var list<string>
     */
    #[Assert\All([new CapacidadDelCatalogo()])]
    #[ORM\Column(type: "json", options: ["default" => "[]"])]
    private array $escalamiento = [];

    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = trim($codigo);

        return $this;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = trim($nombre);

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getSujeto(): SujetoAfectacion
    {
        return $this->sujeto;
    }

    public function setSujeto(SujetoAfectacion $sujeto): static
    {
        $this->sujeto = $sujeto;

        return $this;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): static
    {
        $this->owner = trim($owner);

        return $this;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setTarget(string $target): static
    {
        $this->target = trim($target);

        return $this;
    }

    public function getPrioridad(): PrioridadAfectacion
    {
        return $this->prioridad;
    }

    public function setPrioridad(PrioridadAfectacion $prioridad): static
    {
        $this->prioridad = $prioridad;

        return $this;
    }

    public function getIntervaloMinutos(): int
    {
        return $this->intervaloMinutos;
    }

    public function setIntervaloMinutos(int $intervaloMinutos): static
    {
        $this->intervaloMinutos = $intervaloMinutos;

        return $this;
    }

    public function getEscalarTrasMinutos(): int
    {
        return $this->escalarTrasMinutos;
    }

    public function setEscalarTrasMinutos(int $escalarTrasMinutos): static
    {
        $this->escalarTrasMinutos = $escalarTrasMinutos;

        return $this;
    }

    /** @return list<string> */
    public function getEscalamiento(): array
    {
        return $this->escalamiento;
    }

    /** @param array<mixed> $escalamiento */
    public function setEscalamiento(array $escalamiento): static
    {
        $codigos = [];
        foreach ($escalamiento as $c) {
            $c = trim((string) $c);
            if ($c !== "" && !in_array($c, $codigos, true)) {
                $codigos[] = $c;
            }
        }
        $this->escalamiento = $codigos;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }

    /**
     * Quiénes reciben los avisos desde el escalamiento: `target` y las de
     * `escalamiento`, sin repetir.
     *
     * @return list<string>
     */
    public function capacidadesEscaladas(): array
    {
        return array_values(array_unique([$this->target, ...$this->escalamiento]));
    }
}
