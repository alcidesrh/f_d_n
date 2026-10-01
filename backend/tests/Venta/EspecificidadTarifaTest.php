<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\CandidatoTarifa;
use App\Venta\EspecificidadTarifa;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EspecificidadTarifaTest extends TestCase
{
    private static function t(int $id, int $trayecto = 1, ?int $empresa = null, ?string $hora = null, ?int $bus = null, ?string $clase = null): CandidatoTarifa
    {
        return new CandidatoTarifa($id, Money::GTQ($id * 100), $trayecto, $empresa, $hora, $bus, $clase);
    }

    /** Salida: trayecto 1, empresa 1, 12:00, bus 1. */
    private static function elegir(array $candidatos, string $clase = "A", int $trayecto = 1, ?string $hora = "12:00", ?int $empresa = 1, ?int $bus = 1): ?int
    {
        return EspecificidadTarifa::elegir($candidatos, $clase, $trayecto, $empresa, $hora, $bus)?->id;
    }

    /** Las tarifas del ejemplo de la especificación, por id. */
    private static function ejemplo(): array
    {
        return [
            1 => self::t(1, 1, 1, "12:00", 1, "A"),
            2 => self::t(2, 1, 2, "12:00", 1, "A"),
            3 => self::t(3, 1, 1, "15:00", 1, "A"),
            4 => self::t(4, 1),
            5 => self::t(5, 1, clase: "A"),
            6 => self::t(6, 1, 1, "12:00", 1),
            7 => self::t(7, 1, null, "12:00", 1, "A"),
            8 => self::t(8, 1, 1),
            9 => self::t(9, 1, 1, null, 1),
        ];
    }

    public function testNoAplicaSiFijaUnValorDistinto(): void
    {
        $e = self::ejemplo();
        $this->assertNull(self::elegir([$e[2]]), "otra empresa");
        $this->assertNull(self::elegir([$e[3]]), "otra hora");
        $this->assertNull(self::elegir([$e[1]], "B"), "otra clase");
        $this->assertNull(self::elegir([$e[4]], trayecto: 2), "otro trayecto");
    }

    /**
     * Orden de prioridad del ejemplo para un asiento A, de la más fuerte a la
     * más débil: cada una gana a todas las que le siguen.
     *
     * @return iterable<array{list<int>, string}>
     */
    public static function ordenes(): iterable
    {
        yield "asiento A" => [[1, 6, 9, 8, 7, 5, 4], "A"];
        yield "asiento B" => [[6, 9, 8, 4], "B"];
    }

    /** @param list<int> $orden */
    #[DataProvider("ordenes")]
    public function testPrioridadDelEjemplo(array $orden, string $clase): void
    {
        $e = self::ejemplo();
        while ($orden !== []) {
            // Con las que quedan (y las que nunca aplican), gana la primera.
            $candidatos = array_merge(array_map(static fn(int $id) => $e[$id], $orden), [$e[2], $e[3]]);
            shuffle($candidatos);
            $this->assertSame($orden[0], self::elegir($candidatos, $clase));
            array_shift($orden);
        }
    }

    public function testLaEmpresaPesaMasQueHoraBusYClaseJuntos(): void
    {
        $e = self::ejemplo();
        $this->assertSame(8, self::elegir([$e[7], $e[8]]));
    }

    public function testElBusPesaMasQueLaClase(): void
    {
        $this->assertSame(1, self::elegir([self::t(1, bus: 1), self::t(2, clase: "A")]));
    }

    public function testSinBusNiEmpresaSoloAplicanLasQueNoLosFijan(): void
    {
        $e = self::ejemplo();
        $this->assertSame(5, self::elegir(array_values($e), empresa: null, bus: null));
    }

    public function testEmpateGanaLaMasReciente(): void
    {
        $this->assertSame(9, self::elegir([self::t(4, empresa: 1), self::t(9, empresa: 1), self::t(6, empresa: 1)]));
    }

    /** Subtrayecto 5 de la salida: empresa y bus de la salida, hora en su parada de origen. */
    public function testSubtrayectoUsaSuTrayectoConEmpresaYBusDeLaSalidaYHoraEnLaParada(): void
    {
        $candidatos = [
            self::t(1, 1, 1),
            self::t(2, 5),
            self::t(3, 5, 1, "14:30", 1),
            self::t(4, 5, 1, "12:00", 1),
        ];

        $this->assertSame(3, self::elegir($candidatos, trayecto: 5, hora: "14:30"));
        $this->assertSame(2, self::elegir($candidatos, trayecto: 5, hora: "15:00"));
        $this->assertNull(self::elegir($candidatos, trayecto: 6), "sin tarifa del subtrayecto no se vende");
    }

    public function testSinHoraEnLaParadaSoloAplicanLasQueNoFijanHora(): void
    {
        $candidatos = [
            self::t(1, 5, 1, "14:30", 1),
            self::t(2, 5, 1, null, 1),
            self::t(3, 5, null, "14:30"),
        ];

        $this->assertSame(2, self::elegir($candidatos, trayecto: 5, hora: null));
        $this->assertSame(1, self::elegir($candidatos, trayecto: 5, hora: "14:30"));
    }
}
