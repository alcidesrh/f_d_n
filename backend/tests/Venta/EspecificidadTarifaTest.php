<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\CandidatoTarifa;
use App\Venta\EspecificidadTarifa;
use Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * Salida de referencia: empresa 3, trayecto 20, 08:00, clase de bus 2, bus 7.
 */
final class EspecificidadTarifaTest extends TestCase
{
    private const AHORA = "2026-10-02 12:00";

    private static function t(
        int $id,
        string $vigente = "2026-01-01",
        string $clase = "A",
        ?int $empresa = null,
        ?int $trayecto = 20,
        ?string $desde = null,
        ?string $hasta = null,
        ?int $busClase = null,
        ?int $bus = null,
    ): CandidatoTarifa {
        return new CandidatoTarifa($id, Money::GTQ(100), $clase, new \DateTimeImmutable($vigente), $empresa, $trayecto, $desde, $hasta, $busClase, $bus);
    }

    private static function elegir(array $candidatos, string $clase = "A", string $hora = "08:00", ?int $busClase = 2, ?int $bus = 7, string $ahora = self::AHORA): ?int
    {
        return EspecificidadTarifa::elegir($candidatos, $clase, 3, 20, $hora, $busClase, $bus, new \DateTimeImmutable($ahora))?->id;
    }

    public function testGanaLaMasRecienteDeLasQueCoincidenOEstanVacias(): void
    {
        $candidatos = [
            self::t(1, "2024-01-01", empresa: 3, busClase: 2, bus: 7, desde: "06:00", hasta: "09:00"),
            self::t(2, "2025-06-01"),
            self::t(3, "2025-03-01", busClase: 2),
        ];

        $this->assertSame(2, self::elegir($candidatos));
    }

    public function testAIgualVigenciaGanaLaDeIdMayor(): void
    {
        $this->assertSame(9, self::elegir([self::t(4), self::t(9)]));
    }

    public function testUnBusDeClaseSinTarifaTomaLaMasRecienteDelTrayecto(): void
    {
        $candidatos = [
            self::t(1, "2014-05-17", busClase: 1),
            self::t(2, "2024-05-09", busClase: 4),
            self::t(3, "2026-01-14", busClase: 20),
        ];

        $this->assertSame(3, self::elegir($candidatos, busClase: 5));
        $this->assertSame(2, self::elegir($candidatos, busClase: 4), "si hay de su clase, esa");
    }

    public function testSeRelajaPrimeroElBus(): void
    {
        $candidatos = [
            self::t(1, "2024-01-01", busClase: 2, bus: 8),
            self::t(2, "2025-01-01", busClase: 9),
        ];

        $this->assertSame(1, self::elegir($candidatos), "dejar de exigir el bus basta; la clase de bus se sigue exigiendo");
    }

    public function testElHorarioSeRelajaAntesQueLaClaseDeBus(): void
    {
        $candidatos = [
            self::t(1, "2024-01-01", busClase: 2, desde: "20:00", hasta: "23:00"),
            self::t(2, "2025-01-01", busClase: 9),
        ];

        $this->assertSame(1, self::elegir($candidatos));
    }

    public function testLaEmpresaEsLoUltimoQueSeRelaja(): void
    {
        $candidatos = [
            self::t(1, "2024-01-01", empresa: 3, busClase: 9),
            self::t(2, "2025-01-01", empresa: 4, busClase: 2),
        ];

        $this->assertSame(1, self::elegir($candidatos));
        $this->assertSame(2, self::elegir([self::t(2, empresa: 4)]), "como último recurso, la de otra empresa");
    }

    public function testHorarioConExtremosIncluidosYQueCruzaLaMedianoche(): void
    {
        $candidatos = [
            self::t(1, "2024-01-01"),
            self::t(2, "2025-01-01", desde: "22:15", hasta: "04:00"),
            self::t(3, "2025-01-01", desde: "08:00", hasta: "08:00"),
        ];

        $this->assertSame(3, self::elegir($candidatos));
        $this->assertSame(2, self::elegir($candidatos, hora: "23:30"));
        $this->assertSame(2, self::elegir($candidatos, hora: "03:00"));
        $this->assertSame(1, self::elegir($candidatos, hora: "12:00"));
    }

    public function testLaClaseDeAsientoYElTrayectoNuncaSeRelajan(): void
    {
        $this->assertNull(self::elegir([self::t(1, clase: "B")]));
        $this->assertNull(self::elegir([self::t(1, trayecto: 21)]));
        $this->assertSame(1, self::elegir([self::t(1, trayecto: null)]), "sin trayecto aplica a cualquiera");
    }

    public function testUnaTarifaTodaviaNoVigenteNoAplica(): void
    {
        $candidatos = [self::t(1, "2026-01-01"), self::t(2, "2026-11-01")];

        $this->assertSame(1, self::elegir($candidatos));
        $this->assertSame(2, self::elegir($candidatos, ahora: "2026-11-01 00:00"));
        $this->assertNull(self::elegir([self::t(2, "2026-11-01")]));
    }
}
