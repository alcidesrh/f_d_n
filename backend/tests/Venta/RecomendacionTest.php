<?php

declare(strict_types=1);

namespace App\Tests\Venta;

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

        $this->assertSame([1], Recomendacion::asientos([1, 2, 3, 4], 4, new Tramo(1, 2), $ocupantes));
    }

    public function testEmpatesYSinVecinosNoRecomienda(): void
    {
        $this->assertSame([], Recomendacion::asientos([1, 2], 4, new Tramo(1, 2), []), "todo libre: nada que recomendar");
        $empate = [new Ocupante(5, 0, 1, Ocupante::VENDIDO), new Ocupante(6, 0, 1, Ocupante::VENDIDO)];
        $this->assertSame([5, 6], Recomendacion::asientos([6, 5, 7], 4, new Tramo(1, 2), $empate));
    }

    public function testElViajeCompletoNoTieneHolguraQueAprovechar(): void
    {
        $this->assertSame([], Recomendacion::asientos([1, 2], 4, new Tramo(0, 4), [new Ocupante(2, 0, 4, Ocupante::VENDIDO)]));
    }
}
