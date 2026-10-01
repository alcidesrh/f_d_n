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

    public function actualizar(string $recargoPorciento, bool $ventaEnLinea, int $cierreMinutos, ?Usuario $por, \DateTimeImmutable $en): void
    {
        $this->recargoPorciento = $recargoPorciento;
        $this->ventaEnLinea = $ventaEnLinea;
        $this->cierreMinutos = $cierreMinutos;
        $this->actualizadaPor = $por;
        $this->actualizadaEn = $en;
    }
}
