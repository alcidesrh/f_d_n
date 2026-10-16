<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\Ocupacion;
use App\Venta\Ocupante;
use App\Venta\Recomendacion;
use App\Venta\Tramo;
use PHPUnit\Framework\TestCase;

final class RecomendacionTest extends TestCase
{
    // Paradas A(0) B(1) C(2) D(3) E(4); se pide B→C = [1, 2).

    public function testPrefiereElHuecoMasAjustado(): void
    {
        $ocupantes = [
            new Ocupante(1, 0, 1, Ocupante::VENDIDO), // A→B
            new Ocupante(1, 2, 4, Ocupante::VENDIDO), // C→E: el asiento 1 queda libre justo B→C
            new Ocupante(2, 0, 1, Ocupante::VENDIDO), // A→B: libre de B a E
            new Ocupante(3, 1, 3, Ocupante::RESERVADO, token: "x"), // se solapa: no cuenta
        ];

        $this->assertSame([1], array_keys(Recomendacion::asientos([1, 2, 3, 4], 4, new Tramo(1, 2), $ocupantes)));
    }

    public function testEmpatesYSinVecinosNoRecomienda(): void
    {
        $this->assertSame([], Recomendacion::asientos([1, 2], 4, new Tramo(1, 2), []), "todo libre: nada que recomendar");
        $empate = [new Ocupante(5, 0, 1, Ocupante::VENDIDO), new Ocupante(6, 0, 1, Ocupante::VENDIDO)];
        $this->assertSame([5, 6], array_keys(Recomendacion::asientos([6, 5, 7], 4, new Tramo(1, 2), $empate)));
    }

    public function testElViajeCompletoNoTieneHolguraQueAprovechar(): void
    {
        $this->assertSame([], Recomendacion::asientos([1, 2], 4, new Tramo(0, 4), [new Ocupante(2, 0, 4, Ocupante::VENDIDO)]));
    }

    public function testElVecinoDeUnTramoAnteriorGanaAlPosterior(): void
    {
        // Asiento 7: A→B (anterior a B→C) y D→E (posterior). Se elige el anterior.
        $ocupantes = [
            new Ocupante(7, 0, 1, Ocupante::RESERVADO, token: "x"),
            new Ocupante(7, 3, 4, Ocupante::VENDIDO),
        ];

        $recomendados = Recomendacion::asientos([7, 10], 4, new Tramo(1, 2), $ocupantes);

        $this->assertSame([7], array_keys($recomendados));
        $this->assertSame(Ocupante::RESERVADO, $recomendados[7]->tipo);
    }

    public function testEntreAnterioresGanaElMasCercano(): void
    {
        // Asiento 9 con B→C pedido en [2, 3): A→B y B→C son anteriores; gana B→C, que termina en 2.
        $ocupantes = [
            new Ocupante(9, 0, 1, Ocupante::VENDIDO),
            new Ocupante(9, 1, 2, Ocupante::RESERVADO, token: "x"),
        ];

        $recomendados = Recomendacion::asientos([9, 10], 4, new Tramo(2, 3), $ocupantes);

        $this->assertSame([9], array_keys($recomendados));
        $this->assertSame(Ocupante::RESERVADO, $recomendados[9]->tipo);
    }

    public function testEntrePosterioresGanaElMasCercano(): void
    {
        // Asiento 9 con A→B pedido en [0, 1): sin anteriores; gana C→D (empieza en 2), no D→E (empieza en 3).
        $ocupantes = [
            new Ocupante(9, 3, 4, Ocupante::VENDIDO),
            new Ocupante(9, 2, 3, Ocupante::VENDIDO, sinCobro: Ocupacion::CORTESIA),
        ];

        $recomendados = Recomendacion::asientos([9, 10], 4, new Tramo(0, 1), $ocupantes);

        $this->assertSame([9], array_keys($recomendados));
        $this->assertSame(Ocupacion::CORTESIA, $recomendados[9]->sinCobro);
    }
}
