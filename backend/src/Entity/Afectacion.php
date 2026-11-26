<?php

declare(strict_types=1);

namespace App\Entity;

use App\Afectacion\AfectacionRechazada;
use App\Entity\Enum\EstadoAfectacion;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una afectación de la logística que surgió (ADR-070): la impresora de una
 * estación que se rompió, el papel que se acabó, la avería de un bus. La
 * reporta un usuario con el `owner` de su tipo, o la crea sola un detector
 * (`reportadaPor` null, con `clave` para no repetirla mientras siga
 * abierta). Dónde cae —empresa, estación, bus— decide a quién avisa el
 * centinela y quién la ve.
 *
 * Estado `creada → pendiente → resuelta`: el centinela la pasa a pendiente
 * al enviar el primer aviso; quien tiene el `target` del tipo la resuelve.
 * `enviadaEn`, `escaladaEn`, `ultimoAvisoEn` y `avisos` llevan el ritmo
 * del centinela (`App\Afectacion\ReglasCentinela`).
 *
 * Solo la API REST `/api/afectaciones` la expone; los cambios pasan por
 * `App\Afectacion\Afectaciones` y `Centinela`.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["estado", "creada_en"], name: "idx_afectacion_estado")]
#[ORM\Index(columns: ["clave", "estado"], name: "idx_afectacion_clave")]
class Afectacion
{
    public const MAX_DESCRIPCION = 2000;
    public const MAX_RESOLUCION = 1000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    /** Sin `onDelete`: un tipo con afectaciones no se borra (se desactiva). */
    #[ORM\JoinColumn(nullable: false)]
    private TipoAfectacion $tipo;

    #[ORM\Column(length: 12, enumType: EstadoAfectacion::class)]
    private EstadoAfectacion $estado = EstadoAfectacion::CREADA;

    /** Qué pasó, con las palabras de quien la reporta (o del detector). */
    #[ORM\Column(type: "text")]
    private string $descripcion;

    /** La empresa dueña de lo afectado; null = compartido (solo lo cubre «todas las empresas»). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Empresa $empresa;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Estacion $estacion;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Bus $bus;

    /** Null: la creó un detector. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $reportadaPor;

    /** Lo que identifica lo detectado (`stock:12:34`): mientras siga abierta, el detector no crea otra. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $clave;

    #[ORM\Column]
    private \DateTimeImmutable $creadaEn;

    /** Primer aviso: cuándo pasó a pendiente. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $enviadaEn = null;

    /** Cuándo empezó a avisarse también a las capacidades de escalamiento. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $escaladaEn = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoAvisoEn = null;

    /** Rondas de aviso enviadas. */
    #[ORM\Column(options: ["default" => 0])]
    private int $avisos = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resueltaEn = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $resueltaPor = null;

    /** Cómo se resolvió (reparada, repuesta, cambiada por otra, con qué proveedor). */
    #[ORM\Column(type: "text", nullable: true)]
    private ?string $resolucion = null;

    public function __construct(
        TipoAfectacion $tipo,
        string $descripcion,
        ?Empresa $empresa,
        ?Estacion $estacion,
        ?Bus $bus,
        ?Usuario $reportadaPor,
        \DateTimeImmutable $ahora,
        ?string $clave = null,
    ) {
        $this->tipo = $tipo;
        $this->descripcion = self::texto($descripcion, self::MAX_DESCRIPCION, "Describa qué pasó.");
        $this->empresa = $empresa;
        $this->estacion = $estacion;
        $this->bus = $bus;
        $this->reportadaPor = $reportadaPor;
        $this->creadaEn = $ahora;
        $this->clave = $clave === null || trim($clave) === "" ? null : mb_substr(trim($clave), 0, 120);
    }

    /** `creada → pendiente`: se envió el primer aviso. */
    public function enviada(\DateTimeImmutable $ahora, bool $escalada = false): void
    {
        if ($this->estado !== EstadoAfectacion::CREADA) {
            throw new AfectacionRechazada(sprintf("La afectación #%d ya se había enviado.", $this->id), "afectacion_ya_enviada", 409);
        }
        $this->estado = EstadoAfectacion::PENDIENTE;
        $this->enviadaEn = $ahora;
        $this->avisada($ahora, $escalada);
    }

    /**
     * Una ronda de avisos más. Con `$escalada`, la primera fija cuándo empezó
     * el escalamiento. Una ronda sin nadie a quien avisar también cuenta para
     * el ritmo (si no, se intentaría cada minuto), pero no la pasa a pendiente.
     */
    public function avisada(\DateTimeImmutable $ahora, bool $escalada, bool $entregada = true): void
    {
        if ($this->estado === EstadoAfectacion::RESUELTA) {
            return;
        }
        $this->ultimoAvisoEn = $ahora;
        if ($escalada) {
            $this->escaladaEn ??= $ahora;
        }
        if ($entregada) {
            $this->avisos++;
        }
    }

    public function editar(string $descripcion): void
    {
        if ($this->estado === EstadoAfectacion::RESUELTA) {
            throw new AfectacionRechazada(sprintf("La afectación #%d ya está resuelta.", $this->id), "afectacion_resuelta", 409);
        }
        $this->descripcion = self::texto($descripcion, self::MAX_DESCRIPCION, "Describa qué pasó.");
    }

    /** Desde `creada` o `pendiente`. */
    public function resolver(?Usuario $por, ?string $resolucion, \DateTimeImmutable $ahora): void
    {
        if ($this->estado === EstadoAfectacion::RESUELTA) {
            throw new AfectacionRechazada(sprintf("La afectación #%d ya está resuelta.", $this->id), "afectacion_resuelta", 409);
        }
        $resolucion = trim((string) $resolucion);
        $this->estado = EstadoAfectacion::RESUELTA;
        $this->resueltaEn = $ahora;
        $this->resueltaPor = $por;
        $this->resolucion = $resolucion === "" ? null : mb_substr($resolucion, 0, self::MAX_RESOLUCION);
    }

    private static function texto(string $texto, int $maximo, string $siVacio): string
    {
        $texto = trim($texto);
        if ($texto === "") {
            throw new AfectacionRechazada($siVacio, "falta_descripcion");
        }

        return mb_substr($texto, 0, $maximo);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): TipoAfectacion
    {
        return $this->tipo;
    }

    public function getEstado(): EstadoAfectacion
    {
        return $this->estado;
    }

    public function getDescripcion(): string
    {
        return $this->descripcion;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function getEstacion(): ?Estacion
    {
        return $this->estacion;
    }

    public function getBus(): ?Bus
    {
        return $this->bus;
    }

    public function getReportadaPor(): ?Usuario
    {
        return $this->reportadaPor;
    }

    public function getClave(): ?string
    {
        return $this->clave;
    }

    public function getCreadaEn(): \DateTimeImmutable
    {
        return $this->creadaEn;
    }

    public function getEnviadaEn(): ?\DateTimeImmutable
    {
        return $this->enviadaEn;
    }

    public function getEscaladaEn(): ?\DateTimeImmutable
    {
        return $this->escaladaEn;
    }

    public function getUltimoAvisoEn(): ?\DateTimeImmutable
    {
        return $this->ultimoAvisoEn;
    }

    public function getAvisos(): int
    {
        return $this->avisos;
    }

    public function getResueltaEn(): ?\DateTimeImmutable
    {
        return $this->resueltaEn;
    }

    public function getResueltaPor(): ?Usuario
    {
        return $this->resueltaPor;
    }

    public function getResolucion(): ?string
    {
        return $this->resolucion;
    }
}
