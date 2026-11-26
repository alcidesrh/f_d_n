<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\TipoMovimiento;
use App\Inventario\InventarioRechazado;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una línea del libro de un almacén (ADR-071): qué artículo, cuánto
 * (`cantidad` con signo: + entra, − sale), a qué costo unitario, cuánto
 * quedó (`saldo`), quién, cuándo y por qué. No se edita: un error se corrige
 * con otro movimiento (un ajuste).
 *
 * Un traslado son dos líneas: el `envio` (−, con `destino`) y, al llegar,
 * la `recepcion` (+, en el destino, con `envio` apuntando a la primera).
 * Mientras el envío no tiene `recibidoEn`, va en camino.
 *
 * Solo la API REST `/api/inventario` la expone; la escribe
 * `App\Inventario\Inventario`.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["almacen_id", "creado_en"], name: "idx_movimiento_almacen")]
#[ORM\Index(columns: ["tipo", "recibido_en"], name: "idx_movimiento_en_camino")]
class Movimiento
{
    public const MAX_MOTIVO = 500;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 12, enumType: TipoMovimiento::class)]
    private TipoMovimiento $tipo;

    /** Sin `onDelete`: el libro no se borra con el almacén (se desactiva). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Almacen $almacen;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Articulo $articulo;

    /** Con signo, en la unidad del artículo. */
    #[ORM\Column(type: "decimal", precision: 12, scale: 2)]
    private string $cantidad;

    /** Quetzales por unidad: el de la factura en una entrada; el promedio del almacén en lo demás. */
    #[ORM\Column(type: "decimal", precision: 14, scale: 4)]
    private string $costoUnitario;

    /** La existencia del artículo en el almacén después de este movimiento. */
    #[ORM\Column(type: "decimal", precision: 12, scale: 2)]
    private string $saldo;

    /** Factura, guía o constancia. */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $documento;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $motivo;

    /** El envío: a dónde va. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Almacen $destino = null;

    /** La recepción: el envío que recibe. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Movimiento $envio = null;

    /** El envío: cuándo llegó (null = en camino). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $recibidoEn = null;

    /** La afectación que lo motivó (el repuesto de una avería, el papel que se acabó). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Afectacion $afectacion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $usuario;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    public function __construct(
        TipoMovimiento $tipo,
        Existencia $existencia,
        string $cantidad,
        string $costoUnitario,
        ?Usuario $usuario,
        \DateTimeImmutable $ahora,
        ?string $documento = null,
        ?string $motivo = null,
    ) {
        $motivo = $motivo === null ? null : trim($motivo);
        if ($motivo !== null && mb_strlen($motivo) > self::MAX_MOTIVO) {
            throw new InventarioRechazado(sprintf("El motivo admite hasta %d caracteres.", self::MAX_MOTIVO), "motivo_largo");
        }
        $documento = $documento === null ? null : trim($documento);
        if ($documento !== null && mb_strlen($documento) > 80) {
            throw new InventarioRechazado("El documento admite hasta 80 caracteres.", "documento_largo");
        }
        $this->tipo = $tipo;
        $this->almacen = $existencia->getAlmacen();
        $this->articulo = $existencia->getArticulo();
        $this->cantidad = $cantidad;
        $this->costoUnitario = $costoUnitario;
        $this->saldo = $existencia->getCantidad();
        $this->usuario = $usuario;
        $this->creadoEn = $ahora;
        $this->documento = $documento === "" ? null : $documento;
        $this->motivo = $motivo === "" ? null : $motivo;
    }

    public function hacia(Almacen $destino): static
    {
        $this->destino = $destino;

        return $this;
    }

    public function deEnvio(Movimiento $envio): static
    {
        $this->envio = $envio;

        return $this;
    }

    public function por(?Afectacion $afectacion): static
    {
        $this->afectacion = $afectacion;

        return $this;
    }

    /** El envío llegó. Una sola vez. */
    public function recibido(\DateTimeImmutable $ahora): void
    {
        if ($this->tipo !== TipoMovimiento::ENVIO) {
            throw new InventarioRechazado("Solo se recibe un envío.", "no_es_envio", 409);
        }
        if ($this->recibidoEn !== null) {
            throw new InventarioRechazado("Ese envío ya se recibió.", "envio_recibido", 409);
        }
        $this->recibidoEn = $ahora;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): TipoMovimiento
    {
        return $this->tipo;
    }

    public function getAlmacen(): Almacen
    {
        return $this->almacen;
    }

    public function getArticulo(): Articulo
    {
        return $this->articulo;
    }

    public function getCantidad(): string
    {
        return $this->cantidad;
    }

    public function getCostoUnitario(): string
    {
        return $this->costoUnitario;
    }

    public function getSaldo(): string
    {
        return $this->saldo;
    }

    public function getDocumento(): ?string
    {
        return $this->documento;
    }

    public function getMotivo(): ?string
    {
        return $this->motivo;
    }

    public function getDestino(): ?Almacen
    {
        return $this->destino;
    }

    public function getEnvio(): ?Movimiento
    {
        return $this->envio;
    }

    public function getRecibidoEn(): ?\DateTimeImmutable
    {
        return $this->recibidoEn;
    }

    public function getAfectacion(): ?Afectacion
    {
        return $this->afectacion;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }
}
