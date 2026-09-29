<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\Itinerario;
use App\Venta\Tramo;
use PHPUnit\Framework\TestCase;

final class ItinerarioTest extends TestCase
{
    /**
     * Ruta A(1) → B(2) → C(3) → D(4) con todos los pares hacia adelante, como
     * los genera el migrador (ids de trayecto 10+).
     */
    private static function todosLosPares(): Itinerario
    {
        $paradas = [1, 2, 3, 4];
        $subs = [];
        $id = 10;
        for ($i = 0; $i < 4; $i++) {
            for ($j = $i + 1; $j < 4; $j++) {
                if ($i === 0 && $j === 3) {
                    continue; // A→D es el propio trayecto (id 1)
                }
                $subs[] = ["trayecto" => $id++, "origen" => $paradas[$i], "destino" => $paradas[$j], "minutos" => ($j - $i) * 60];
            }
        }
        // El migrador también cuelga el trayecto inverso D→A como subtrayecto.
        $subs[] = ["trayecto" => 99, "origen" => 4, "destino" => 1];
        shuffle($subs);

        return Itinerario::construir(1, 1, 4, $subs, 180);
    }

    public function testOrdenaLasParadasDesdeLosSubtrayectos(): void
    {
        $this->assertSame([1, 2, 3, 4], self::todosLosPares()->paradas);
    }

    public function testDescartaElTrayectoInverso(): void
    {
        $it = self::todosLosPares();

        $this->assertFalse($it->contieneTrayecto(99));
        $this->assertTrue($it->contieneTrayecto(1));
        $this->assertNull($it->tramo(99));
    }

    public function testTramoDeUnSubtrayecto(): void
    {
        $it = self::todosLosPares();
        $bc = $it->trayectoEntre(2, 3);

        $this->assertNotNull($bc);
        $this->assertEquals(new Tramo(1, 2), $it->tramo($bc));
        $this->assertEquals(new Tramo(0, 3), $it->tramo(1));
        $this->assertNull($it->tramoEntre(3, 2), "hacia atrás no hay tramo");
    }

    public function testOrdenConSoloTramosConsecutivos(): void
    {
        $it = Itinerario::construir(1, 1, 4, [
            ["trayecto" => 12, "origen" => 3, "destino" => 4],
            ["trayecto" => 10, "origen" => 1, "destino" => 2],
            ["trayecto" => 11, "origen" => 2, "destino" => 3],
        ]);

        $this->assertSame([1, 2, 3, 4], $it->paradas);
    }

    public function testSinSubtrayectosSoloOrigenYDestino(): void
    {
        $it = Itinerario::construir(7, 5, 6, []);

        $this->assertSame([5, 6], $it->paradas);
        $this->assertSame([7], array_keys($it->trayectos()));
    }

    public function testMinutosHastaUnaParada(): void
    {
        $it = self::todosLosPares();

        $this->assertSame(0, $it->minutosHasta(1));
        $this->assertSame(120, $it->minutosHasta(3));
        $this->assertSame(180, $it->minutosHasta(4));
        $this->assertNull($it->minutosHasta(42));
    }

    public function testTramosSeSolapan(): void
    {
        $this->assertTrue((new Tramo(0, 2))->seSolapaCon(new Tramo(1, 3)));
        $this->assertFalse((new Tramo(0, 1))->seSolapaCon(new Tramo(1, 3)), "A→B y B→D comparten asiento");
        $this->assertTrue((new Tramo(0, 3))->seSolapaCon(new Tramo(1, 2)));
    }

    public function testTramoVacioEsInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Tramo(2, 2);
    }
}
