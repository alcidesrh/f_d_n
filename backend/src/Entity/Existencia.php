<?php

declare(strict_types=1);

namespace App\Entity;

use App\Inventario\Cantidad;
use App\Inventario\InventarioRechazado;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cuánto hay de un artículo en un almacén y a qué costo (ADR-071), con sus
 * niveles: debajo del `minimo` el detector crea la afectación del faltante;
 * en el `puntoReorden` o debajo, toca reponer; `maximo` es hasta dónde.
 *
 * `cantidad` es la suma de los movimientos del almacén con ese artículo:
 * solo la cambian `entrar()`, `sacar()` y `contar()`, que llama
 * `App\Inventario\Inventario` junto con el `Movimiento` que lo explica.
 * `costoPromedio` es el promedio ponderado de lo que ha entrado.
 *
 * Solo la API REST `/api/inventario` la expone.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uniq_existencia_almacen_articulo", columns: ["almacen_id", "articulo_id"])]
class Existencia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Sin `onDelete`, como el artículo: un almacén con existencias no se borra (se desactiva). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Almacen $almacen;

    /** Sin `onDelete`: un artículo con existencias no se borra (se desactiva). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Articulo $articulo;

    #[ORM\Column(type: "decimal", precision: 12, scale: 2, options: ["default" => "0.00"])]
    private string $cantidad = "0.00";

    /** Quetzales por unidad. */
    #[ORM\Column(type: "decimal", precision: 14, scale: 4, options: ["default" => "0.0000"])]
    private string $costoPromedio = "0.0000";

    #[ORM\Column(type: "decimal", precision: 12, scale: 2, options: ["default" => "0.00"])]
    private string $minimo = "0.00";

    #[ORM\Column(type: "decimal", precision: 12, scale: 2, nullable: true)]
    private ?string $puntoReorden = null;

    #[ORM\Column(type: "decimal", precision: 12, scale: 2, nullable: true)]
    private ?string $maximo = null;

    public function __construct(Almacen $almacen, Articulo $articulo)
    {
        $this->almacen = $almacen;
        $this->articulo = $articulo;
    }

    /** Suma `$q` centésimas a `$costo` diezmilésimas y rehace el promedio ponderado. */
    public function entrar(int $q, int $costo): void
    {
        if ($q <= 0) {
            throw new InventarioRechazado("La cantidad debe ser mayor que cero.", "cantidad_invalida");
        }
        $antes = $this->centesimas();
        $this->costoPromedio = Cantidad::costoTexto(Cantidad::promedio($antes, $this->costo(), $q, $costo));
        $this->cantidad = Cantidad::texto($antes + $q);
    }

    /** Resta `$q` centésimas; no deja la existencia en negativo. */
    public function sacar(int $q): void
    {
        if ($q <= 0) {
            throw new InventarioRechazado("La cantidad debe ser mayor que cero.", "cantidad_invalida");
        }
        $antes = $this->centesimas();
        if ($q > $antes) {
            throw new InventarioRechazado(
                sprintf("Solo hay %s %s de «%s» en %s.", Cantidad::texto($antes), $this->articulo->getUnidad(), $this->articulo->getNombre(), $this->almacen->getNombre()),
                "existencia_insuficiente",
                409,
            );
        }
        $this->cantidad = Cantidad::texto($antes - $q);
    }

    /** Un conteo: deja `$contado` centésimas y devuelve la diferencia con lo que decía el sistema. */
    public function contar(int $contado): int
    {
        if ($contado < 0) {
            throw new InventarioRechazado("Lo contado no puede ser negativo.", "cantidad_invalida");
        }
        $diferencia = $contado - $this->centesimas();
        $this->cantidad = Cantidad::texto($contado);

        return $diferencia;
    }

    /** Centésimas: null deja ese nivel sin poner. */
    public function niveles(int $minimo, ?int $puntoReorden, ?int $maximo): void
    {
        if ($minimo < 0 || ($puntoReorden !== null && $puntoReorden < 0) || ($maximo !== null && $maximo < 0)) {
            throw new InventarioRechazado("Los niveles no pueden ser negativos.", "nivel_invalido");
        }
        if ($puntoReorden !== null && $puntoReorden < $minimo) {
            throw new InventarioRechazado("El punto de reorden va en el mínimo o por encima.", "nivel_invalido");
        }
        if ($maximo !== null && $maximo < max($minimo, $puntoReorden ?? 0)) {
            throw new InventarioRechazado("El máximo va por encima del mínimo y del punto de reorden.", "nivel_invalido");
        }
        $this->minimo = Cantidad::texto($minimo);
        $this->puntoReorden = $puntoReorden === null ? null : Cantidad::texto($puntoReorden);
        $this->maximo = $maximo === null ? null : Cantidad::texto($maximo);
    }

    public function centesimas(): int
    {
        return (int) Cantidad::centesimas($this->cantidad);
    }

    /** Diezmilésimas de quetzal por unidad. */
    public function costo(): int
    {
        return (int) Cantidad::diezmilesimas($this->costoPromedio);
    }

    public function minimoCentesimas(): int
    {
        return (int) Cantidad::centesimas($this->minimo);
    }

    public function puntoReordenCentesimas(): ?int
    {
        return $this->puntoReorden === null ? null : Cantidad::centesimas($this->puntoReorden);
    }

    public function maximoCentesimas(): ?int
    {
        return $this->maximo === null ? null : Cantidad::centesimas($this->maximo);
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCostoPromedio(): string
    {
        return $this->costoPromedio;
    }

    public function getMinimo(): string
    {
        return $this->minimo;
    }

    public function getPuntoReorden(): ?string
    {
        return $this->puntoReorden;
    }

    public function getMaximo(): ?string
    {
        return $this->maximo;
    }
}
