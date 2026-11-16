<?php

declare(strict_types=1);

namespace App\Tests\Seguimiento;

use App\Seguimiento\HoraEstimada;
use PHPUnit\Framework\TestCase;

/** Hora estimada de llegada por parada (ADR-064). */
final class HoraEstimadaTest extends TestCase
{
    private const SALIDA = 1_800_000_000;

    /**
     * Tres paradas sobre el mismo meridiano: origen, intermedia a una hora
     * (medio grado al norte) y destino a dos horas.
     *
     * @return list<array{enclave: int, nombre: string, lat: ?float, lng: ?float, hora: ?int}>
     */
    private static function horario(): array
    {
        return [
            ["enclave" => 1, "nombre" => "Origen", "lat" => 15.0, "lng" => -90.0, "hora" => self::SALIDA],
            ["enclave" => 2, "nombre" => "Intermedia", "lat" => 15.5, "lng" => -90.0, "hora" => self::SALIDA + 3600],
            ["enclave" => 3, "nombre" => "Destino", "lat" => 16.0, "lng" => -90.0, "hora" => self::SALIDA + 7200],
        ];
    }

    public function testSinLecturaValeElHorario(): void
    {
        $r = HoraEstimada::calcular(self::horario(), null, self::SALIDA + 600);

        $this->assertSame("horario", $r["fuente"]);
        $this->assertNull($r["atrasoMinutos"]);
        $this->assertSame([self::SALIDA, self::SALIDA + 3600, self::SALIDA + 7200], array_column($r["paradas"], "estimada"));
        $this->assertSame([false, false, false], array_column($r["paradas"], "paso"));
    }

    public function testElAtrasoSeSumaALasParadasQueFaltan(): void
    {
        // A mitad del primer tramo (el horario lo ponía ahí a los 30 min) cuando ya van 50 min: 20 de atraso.
        $ahora = self::SALIDA + 3000;
        $r = HoraEstimada::calcular(self::horario(), ["lat" => 15.25, "lng" => -90.0, "instante" => $ahora], $ahora);

        $this->assertSame("gps", $r["fuente"]);
        $this->assertSame(20, $r["atrasoMinutos"]);
        $this->assertSame([true, false, false], array_column($r["paradas"], "paso"));
        $this->assertSame([null, self::SALIDA + 3600 + 1200, self::SALIDA + 7200 + 1200], array_column($r["paradas"], "estimada"));
        $this->assertSame(self::SALIDA + 3600, $r["paradas"][1]["programada"]);
    }

    public function testAdelantadoNoLlegaAntesDeAhora(): void
    {
        // Ya en la intermedia a los 50 min (10 de adelanto): la pasó; el destino, 10 min antes.
        $ahora = self::SALIDA + 3000;
        $r = HoraEstimada::calcular(self::horario(), ["lat" => 15.5, "lng" => -90.0, "instante" => $ahora], $ahora);

        $this->assertSame(-10, $r["atrasoMinutos"]);
        $this->assertSame([true, true, false], array_column($r["paradas"], "paso"));
        $this->assertSame(self::SALIDA + 7200 - 600, $r["paradas"][2]["estimada"]);

        // Casi en el destino con una lectura de hace un rato: nunca antes de ahora.
        $r = HoraEstimada::calcular(self::horario(), ["lat" => 15.99, "lng" => -90.0, "instante" => self::SALIDA + 3000], self::SALIDA + 3200);
        $this->assertSame(self::SALIDA + 3200, $r["paradas"][2]["estimada"]);
    }

    public function testEnElOrigenNoHayAdelantoYElRetrasoCuenta(): void
    {
        // Parado en el origen 20 min antes de su hora: sin atraso, y el origen todavía no se pasó.
        $antes = self::SALIDA - 1200;
        $r = HoraEstimada::calcular(self::horario(), ["lat" => 15.001, "lng" => -90.0, "instante" => $antes], $antes);
        $this->assertSame(0, $r["atrasoMinutos"]);
        $this->assertSame([false, false, false], array_column($r["paradas"], "paso"));
        $this->assertSame(self::SALIDA + 3600, $r["paradas"][1]["estimada"]);

        // Sigue en el origen 15 min después de su hora: 15 de atraso.
        $despues = self::SALIDA + 900;
        $r = HoraEstimada::calcular(self::horario(), ["lat" => 15.001, "lng" => -90.0, "instante" => $despues], $despues);
        $this->assertSame(15, $r["atrasoMinutos"]);
        $this->assertSame(self::SALIDA + 7200 + 900, $r["paradas"][2]["estimada"]);
    }

    public function testUnaLecturaLejosDeLaRutaNoEstima(): void
    {
        $ahora = self::SALIDA + 3000;
        $r = HoraEstimada::calcular(self::horario(), ["lat" => 15.25, "lng" => -89.0, "instante" => $ahora], $ahora);

        $this->assertSame("horario", $r["fuente"]);
    }

    public function testParadasSinCoordenadaOSinHoraSeSaltan(): void
    {
        $horario = self::horario();
        $horario[1]["lat"] = null;
        $ahora = self::SALIDA + 3000;
        // La recta va del origen al destino: a un cuarto del camino el horario lo ponía a los 30 min.
        $r = HoraEstimada::calcular($horario, ["lat" => 15.25, "lng" => -90.0, "instante" => $ahora], $ahora);
        $this->assertSame(20, $r["atrasoMinutos"]);
        $this->assertSame(self::SALIDA + 3600 + 1200, $r["paradas"][1]["estimada"]);

        $horario[2]["hora"] = null;
        $this->assertNull(HoraEstimada::ubicar($horario, 15.25, -90.0), "no quedan dos paradas ubicadas");
        $this->assertSame("horario", HoraEstimada::calcular($horario, ["lat" => 15.25, "lng" => -90.0, "instante" => $ahora], $ahora)["fuente"]);
    }
}
