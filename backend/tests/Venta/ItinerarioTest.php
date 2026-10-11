<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\Enum\PrecisionHora;
use App\Venta\Itinerario;
use App\Venta\Tramo;
use PHPUnit\Framework\TestCase;

final class ItinerarioTest extends TestCase
{
    /** Salida A(1) → B(2) → C(3) → D(4): trayecto 1 = A→D, 10+ los pares que tienen trayecto. */
    private static function abcd(): Itinerario
    {
        return Itinerario::deParadas([1, 2, 3, 4], [
            1 => ["origen" => 1, "destino" => 4],
            10 => ["origen" => 1, "destino" => 2],
            11 => ["origen" => 2, "destino" => 3],
            12 => ["origen" => 3, "destino" => 4],
            99 => ["origen" => 4, "destino" => 1],
            98 => ["origen" => 2, "destino" => 7],
        ], [
            1 => ["hora" => new \DateTimeImmutable("2026-10-10 06:00"), "precision" => PrecisionHora::EXACTA],
            3 => ["hora" => new \DateTimeImmutable("2026-10-10 08:00"), "precision" => PrecisionHora::ESTIMADA],
        ]);
    }

    public function testSoloSonVendiblesLosParesHaciaAdelanteEntreSusParadas(): void
    {
        $it = self::abcd();

        $this->assertSame([1, 10, 11, 12], array_keys($it->trayectos()));
        $this->assertSame(1, $it->trayectoId, "el completo une la primera y la última parada");
        $this->assertFalse($it->contieneTrayecto(99), "hacia atrás");
        $this->assertFalse($it->contieneTrayecto(98), "una punta fuera de la salida");
    }

    public function testTramoPorPosicionDeLasParadas(): void
    {
        $it = self::abcd();

        $this->assertEquals(new Tramo(1, 2), $it->tramo(11));
        $this->assertEquals(new Tramo(0, 3), $it->tramo(1));
        $this->assertEquals(new Tramo(0, 3), $it->completo());
        $this->assertEquals(new Tramo(1, 3), $it->tramoEntre(2, 4), "un par sin trayecto también tiene tramo");
        $this->assertNull($it->tramoEntre(3, 2), "hacia atrás no hay tramo");
        $this->assertSame(11, $it->trayectoEntre(2, 3));
        $this->assertSame(2, $it->posicion(3));
    }

    public function testHoraEnUnaParada(): void
    {
        $it = self::abcd();

        $this->assertSame("08:00", $it->hora(3)["hora"]->format("H:i"));
        $this->assertSame(PrecisionHora::ESTIMADA, $it->hora(3)["precision"]);
        $this->assertNull($it->hora(42));
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
