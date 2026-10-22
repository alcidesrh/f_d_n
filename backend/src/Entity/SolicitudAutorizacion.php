<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Ubicacion;
use App\Autorizacion\AutorizacionRechazada;
use App\Entity\Enum\Dispensa;
use App\Entity\Enum\EstadoAutorizacion;
use Doctrine\ORM\Mapping as ORM;

/**
 * Autorización (ADR-040, la «solicitud de autorización» del legado): un pase
 * de un solo uso para que `solicitante` haga una operación que una regla le
 * impide —sin el permiso, pasado su límite diario o fuera de hora
 * (`Dispensa`)— sobre registros concretos (`sujeto` + `sujetoIds`). La pide
 * quien quedó bloqueado, la aprueba o rechaza quien tiene
 * `autorizacion.aprobar` donde cae la operación (`ubicacion`), y se usa sola
 * al repetir la operación, dentro de su transacción. Vence a las
 * `VIGENCIA_HORAS` de pedirse (pendiente) o de aprobarse.
 *
 * Solo la API REST `/api/autorizaciones` la expone; los cambios de estado
 * pasan por `App\Autorizacion\Autorizaciones` y `Pases`.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["solicitante_id", "estado"], name: "idx_solicitud_autorizacion_solicitante")]
#[ORM\Index(columns: ["estado", "creada_en"], name: "idx_solicitud_autorizacion_estado")]
class SolicitudAutorizacion
{
    /** Horas que dura una solicitud pendiente, y un pase aprobado sin usar. */
    public const VIGENCIA_HORAS = 24;
    public const MAX_MOTIVO = 500;
    public const MAX_RESPUESTA = 500;

    /** Sobre qué registros: boletos (anular, reasignar) o una salida (cortesía). */
    public const SUJETO_BOLETOS = "boletos";
    public const SUJETO_SALIDA = "salida";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Código de `App\Acceso\Capacidad` de la operación (`boleto.anular`…). */
    #[ORM\Column(length: 60)]
    private string $capacidad;

    #[ORM\Column(length: 20, enumType: Dispensa::class)]
    private Dispensa $dispensa;

    #[ORM\Column(length: 20)]
    private string $sujeto;

    /** @var list<int> */
    #[ORM\Column(type: "json")]
    private array $sujetoIds;

    /** Unidades que cubre: boletos, o asientos de cortesía. */
    #[ORM\Column]
    private int $cantidad;

    /** Dónde cae la operación (`Ubicacion::toArray`): decide quién la aprueba. */
    #[ORM\Column(type: "json")]
    private array $ubicacion;

    /** Empresa y estación de la operación, para filtrar los listados por alcance. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Empresa $empresa;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Estacion $estacion;

    /**
     * Lo que se quiere hacer, para mostrarlo sin volver a consultar: los
     * boletos (asiento, salida, ruta, fecha), la salida y el mensaje con el
     * que se bloqueó la operación.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: "json")]
    private array $detalle;

    #[ORM\Column(length: self::MAX_MOTIVO)]
    private string $motivo;

    #[ORM\Column(length: 20, enumType: EstadoAutorizacion::class)]
    private EstadoAutorizacion $estado = EstadoAutorizacion::PENDIENTE;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $solicitante;

    #[ORM\Column]
    private \DateTimeImmutable $creadaEn;

    #[ORM\Column]
    private \DateTimeImmutable $venceEn;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $resueltaPor = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resueltaEn = null;

    /** La nota de quien aprueba, o el motivo del rechazo. */
    #[ORM\Column(length: self::MAX_RESPUESTA, nullable: true)]
    private ?string $respuesta = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usadaEn = null;

    /**
     * @param list<int>            $sujetoIds
     * @param array<string, mixed> $detalle
     */
    public function __construct(
        Usuario $solicitante,
        string $capacidad,
        Dispensa $dispensa,
        string $sujeto,
        array $sujetoIds,
        int $cantidad,
        Ubicacion $ubicacion,
        ?Empresa $empresa,
        ?Estacion $estacion,
        array $detalle,
        string $motivo,
        \DateTimeImmutable $ahora,
    ) {
        $motivo = trim($motivo);
        if ($motivo === "") {
            throw new AutorizacionRechazada("Explique por qué necesita la autorización.", "falta_motivo");
        }
        $this->solicitante = $solicitante;
        $this->capacidad = $capacidad;
        $this->dispensa = $dispensa;
        $this->sujeto = $sujeto;
        $this->sujetoIds = array_values(array_unique(array_map("intval", $sujetoIds)));
        sort($this->sujetoIds);
        $this->cantidad = max(1, $cantidad);
        $this->ubicacion = $ubicacion->toArray();
        $this->empresa = $empresa;
        $this->estacion = $estacion;
        $this->detalle = $detalle;
        $this->motivo = mb_substr($motivo, 0, self::MAX_MOTIVO);
        $this->creadaEn = $ahora;
        $this->venceEn = $ahora->modify(sprintf("+%d hours", self::VIGENCIA_HORAS));
    }

    /** El estado a esa hora: una abierta que pasó de su vencimiento está vencida. */
    public function estadoEn(\DateTimeImmutable $ahora): EstadoAutorizacion
    {
        return $this->estado->abierta() && $this->venceEn <= $ahora ? EstadoAutorizacion::VENCIDA : $this->estado;
    }

    public function aprobar(Usuario $quien, ?string $nota, \DateTimeImmutable $ahora): void
    {
        $this->exigirPendiente("aprobar", $ahora);
        $this->estado = EstadoAutorizacion::APROBADA;
        $this->resolver($quien, $nota, $ahora);
        // Desde la aprobación corre la vigencia del pase.
        $this->venceEn = $ahora->modify(sprintf("+%d hours", self::VIGENCIA_HORAS));
    }

    public function rechazar(Usuario $quien, string $motivo, \DateTimeImmutable $ahora): void
    {
        $this->exigirPendiente("rechazar", $ahora);
        if (trim($motivo) === "") {
            throw new AutorizacionRechazada("Indique por qué la rechaza.", "falta_motivo");
        }
        $this->estado = EstadoAutorizacion::RECHAZADA;
        $this->resolver($quien, $motivo, $ahora);
    }

    /** Quien la pidió ya no la necesita (pendiente, o aprobada sin usar). */
    public function retirar(\DateTimeImmutable $ahora): void
    {
        if (!$this->estadoEn($ahora)->abierta()) {
            throw new AutorizacionRechazada(sprintf("La autorización está %s: ya no se retira.", mb_strtolower($this->estadoEn($ahora)->etiqueta())), "autorizacion_cerrada", 409);
        }
        $this->estado = EstadoAutorizacion::RETIRADA;
    }

    /** Solo `Pases::usar`, dentro de la transacción de la operación autorizada. */
    public function usar(\DateTimeImmutable $ahora): void
    {
        if ($this->estadoEn($ahora) !== EstadoAutorizacion::APROBADA) {
            throw new AutorizacionRechazada("La autorización ya no está vigente.", "autorizacion_cerrada", 409);
        }
        $this->estado = EstadoAutorizacion::USADA;
        $this->usadaEn = $ahora;
    }

    private function exigirPendiente(string $accion, \DateTimeImmutable $ahora): void
    {
        $estado = $this->estadoEn($ahora);
        if ($estado !== EstadoAutorizacion::PENDIENTE) {
            throw new AutorizacionRechazada(sprintf("Solo se puede %s una solicitud pendiente; esta está %s.", $accion, mb_strtolower($estado->etiqueta())), "autorizacion_cerrada", 409);
        }
    }

    private function resolver(Usuario $quien, ?string $respuesta, \DateTimeImmutable $ahora): void
    {
        $respuesta = $respuesta === null ? null : trim($respuesta);
        $this->resueltaPor = $quien;
        $this->resueltaEn = $ahora;
        $this->respuesta = $respuesta === null || $respuesta === "" ? null : mb_substr($respuesta, 0, self::MAX_RESPUESTA);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCapacidad(): string
    {
        return $this->capacidad;
    }

    public function getDispensa(): Dispensa
    {
        return $this->dispensa;
    }

    public function getSujeto(): string
    {
        return $this->sujeto;
    }

    /** @return list<int> */
    public function getSujetoIds(): array
    {
        return $this->sujetoIds;
    }

    public function getCantidad(): int
    {
        return $this->cantidad;
    }

    public function getUbicacion(): Ubicacion
    {
        return Ubicacion::desdeArray($this->ubicacion);
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function getEstacion(): ?Estacion
    {
        return $this->estacion;
    }

    /** @return array<string, mixed> */
    public function getDetalle(): array
    {
        return $this->detalle;
    }

    public function getMotivo(): string
    {
        return $this->motivo;
    }

    /** El guardado; para mostrar, `estadoEn()` (que sabe de vencidas). */
    public function getEstado(): EstadoAutorizacion
    {
        return $this->estado;
    }

    public function getSolicitante(): Usuario
    {
        return $this->solicitante;
    }

    public function getCreadaEn(): \DateTimeImmutable
    {
        return $this->creadaEn;
    }

    public function getVenceEn(): \DateTimeImmutable
    {
        return $this->venceEn;
    }

    public function getResueltaPor(): ?Usuario
    {
        return $this->resueltaPor;
    }

    public function getResueltaEn(): ?\DateTimeImmutable
    {
        return $this->resueltaEn;
    }

    public function getRespuesta(): ?string
    {
        return $this->respuesta;
    }

    public function getUsadaEn(): ?\DateTimeImmutable
    {
        return $this->usadaEn;
    }
}
