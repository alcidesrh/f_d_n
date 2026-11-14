<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Ajustes de la venta en la página web (ADR-023): una sola fila. Se edita en
 * el dashboard de la página (`/api/pagina/configuracion`, permiso
 * `pagina.administrar`); el legado los tenía en su tabla `configuracion`.
 */
#[ORM\Entity]
class ConfiguracionPagina
{
    public const ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    /** Porciento que se suma a la tarifa en la web (el `compra_porciento` del legado). */
    #[ORM\Column(type: "decimal", precision: 5, scale: 2, options: ["default" => "0.00"])]
    private string $recargoPorciento = "0.00";

    /** Apagada, la página muestra los horarios pero no vende. */
    #[ORM\Column(options: ["default" => true])]
    private bool $ventaEnLinea = true;

    /** La web deja de vender este tiempo antes de la salida. */
    #[ORM\Column(options: ["default" => 60])]
    private int $cierreMinutos = 60;

    /**
     * Veces que el cliente puede cambiar un mismo asiento desde «Mis compras»
     * (hora, ruta, asiento o clase). 0 = el cliente no modifica sus compras.
     */
    #[ORM\Column(type: "smallint", options: ["default" => 2])]
    private int $modificacionesPorAsiento = 2;

    /**
     * El cliente modifica un viaje hasta este tiempo antes de su salida (la
     * hora en la parada donde sube). Ej.: 30 y sale a las 8:30 → hasta las 8:00.
     */
    #[ORM\Column(options: ["default" => 60])]
    private int $modificacionMinutos = 60;

    /** Penalización por modificar (en quetzales); 0 = sin penalización. */
    #[ORM\Column(type: "decimal", precision: 10, scale: 2, options: ["default" => "0.00"])]
    private string $penalizacion = "0.00";

    /** La penalización se cobra por cada asiento cambiado (si no, una vez por modificación). */
    #[ORM\Column(options: ["default" => false])]
    private bool $penalizacionPorAsiento = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $actualizadaEn = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $actualizadaPor = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getRecargoPorciento(): string
    {
        return $this->recargoPorciento;
    }

    public function getVentaEnLinea(): bool
    {
        return $this->ventaEnLinea;
    }

    public function getCierreMinutos(): int
    {
        return $this->cierreMinutos;
    }

    public function getActualizadaEn(): ?\DateTimeImmutable
    {
        return $this->actualizadaEn;
    }

    public function getActualizadaPor(): ?Usuario
    {
        return $this->actualizadaPor;
    }

    public function getModificacionesPorAsiento(): int
    {
        return $this->modificacionesPorAsiento;
    }

    public function getModificacionMinutos(): int
    {
        return $this->modificacionMinutos;
    }

    public function getPenalizacion(): string
    {
        return $this->penalizacion;
    }

    public function isPenalizacionPorAsiento(): bool
    {
        return $this->penalizacionPorAsiento;
    }

    /** Reglas de las modificaciones del cliente (sin validar: lo hace `AjustesPagina`). */
    public function actualizarModificaciones(int $porAsiento, int $minutos, string $penalizacion, bool $penalizacionPorAsiento): void
    {
        $this->modificacionesPorAsiento = $porAsiento;
        $this->modificacionMinutos = $minutos;
        $this->penalizacion = $penalizacion;
        $this->penalizacionPorAsiento = $penalizacionPorAsiento;
    }

    public function actualizar(string $recargoPorciento, bool $ventaEnLinea, int $cierreMinutos, ?Usuario $por, \DateTimeImmutable $en): void
    {
        $this->recargoPorciento = $recargoPorciento;
        $this->ventaEnLinea = $ventaEnLinea;
        $this->cierreMinutos = $cierreMinutos;
        $this->actualizadaPor = $por;
        $this->actualizadaEn = $en;
    }
}
