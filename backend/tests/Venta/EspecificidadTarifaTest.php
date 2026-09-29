<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\CandidatoTarifa;
use App\Venta\EspecificidadTarifa;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class EspecificidadTarifaTest extends TestCase
{
    private static function t(int $id, int $q, string $clase = "A", ?int $empresa = null, ?int $trayecto = null, ?string $hora = null, ?int $bus = null): CandidatoTarifa
    {
        return new CandidatoTarifa($id, Money::GTQ($q * 100), $clase, $empresa, $trayecto, $hora, $bus);
    }

    private static function elegir(array $candidatos, string $clase = "A", string $hora = "08:00", ?int $bus = 7): ?int
    {
        return EspecificidadTarifa::elegir($candidatos, $clase, 3, 20, $hora, $bus)?->id;
    }

    public function testGanaLaQueCoincideEnMasAtributos(): void
    {
        $candidatos = [
            self::t(1, 100, trayecto: 20),
            self::t(2, 120, empresa: 3, trayecto: 20),
            self::t(3, 150, empresa: 3, trayecto: 20, hora: "08:00"),
        ];

        $this->assertSame(3, self::elegir($candidatos));
        $this->assertSame(2, self::elegir($candidatos, hora: "10:30"), "la de las 08:00 no aplica a otra hora");
    }

    public function testUnAtributoFijadoDistintoDescartaLaTarifa(): void
    {
        $candidatos = [
            self::t(1, 100, trayecto: 20),
            self::t(2, 90, trayecto: 20, bus: 8),
        ];

        $this->assertSame(1, self::elegir($candidatos));
        $this->assertSame(2, self::elegir($candidatos, bus: 8));
    }

    public function testLaClaseDebeCoincidir(): void
    {
        $candidatos = [self::t(1, 100, "B", trayecto: 20)];

        $this->assertNull(self::elegir($candidatos, "A"));
        $this->assertSame(1, self::elegir($candidatos, "B"));
    }

    public function testEmpateGanaLaMasReciente(): void
    {
        $this->assertSame(9, self::elegir([self::t(4, 100, trayecto: 20), self::t(9, 110, trayecto: 20)]));
    }

    public function testComodinTotal(): void
    {
        $this->assertSame(1, self::elegir([self::t(1, 50)]));
    }
}
