<?php

declare(strict_types=1);

namespace App\Tests\Seguimiento;

use App\Seguimiento\FuenteGps;
use App\Seguimiento\TelefonosDePrueba;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class TelefonosDePruebaTest extends TestCase
{
    private const MIN = 60;

    /** En marcha cada `$cada` s de `$desde` a `$hasta` (incluido), a `$kmh`. @return list<array{int, int}> */
    private static function lecturas(int $desde, int $hasta, int $kmh, int $cada = 30): array
    {
        return array_map(static fn(int $t) => [$t, $kmh], range($desde, $hasta, $cada));
    }

    public function testMasDeCincoMinutosSinLecturasEmpiezaOtraTrayectoria(): void
    {
        $l = [...self::lecturas(0, 600, 20), ...self::lecturas(901, 1200, 20)];

        self::assertSame([[0, 20], [21, count($l) - 1]], TelefonosDePrueba::partir($l));
    }

    public function testCincoMinutosJustosSigueLaMisma(): void
    {
        $l = [[0, 20], [5 * self::MIN, 20], [10 * self::MIN, 20]];

        self::assertSame([[0, 2]], TelefonosDePrueba::partir($l), 'cuenta el hueco entre lecturas seguidas, no desde la primera');
    }

    public function testUnaParadaLargaTerminaAlDetenerseYLaSiguienteEmpiezaAlArrancar(): void
    {
        // En marcha 0–10 min, detenido 10:30–20 min (cada 30 s), en marcha desde 20:30.
        $l = [...self::lecturas(0, 600, 20), ...self::lecturas(630, 1200, 0), ...self::lecturas(1230, 1800, 20)];

        // 0..20 en marcha, 21 la primera detenida, 40 la última detenida, 41.. en marcha.
        self::assertSame([[0, 21], [40, 60]], TelefonosDePrueba::partir($l));
    }

    public function testUnaParadaCortaQuedaDentro(): void
    {
        // Detenido de 10:30 a 15 min: arranca a los 15:30, 5 min después de detenerse.
        $l = [...self::lecturas(0, 600, 20), ...self::lecturas(630, 900, 0), ...self::lecturas(930, 1200, 20)];

        self::assertSame([[0, count($l) - 1]], TelefonosDePrueba::partir($l));
    }

    public function testLaParadaSeMideHastaLaLecturaQueArrancaAunqueLasQuietasVenganRaleadas(): void
    {
        // Quieto se guarda una cada 2 min: 10, 12 y 14 min; arranca a los 16 (6 min detenido).
        $l = [[0, 20], [300, 20], [600, 0], [720, 0], [840, 0], [960, 20], [1000, 20]];

        self::assertSame([[0, 2], [4, 6]], TelefonosDePrueba::partir($l));
    }

    public function testLaEsperaAntesDeArrancarNoEsDeLaTrayectoria(): void
    {
        // Encendido y quieto 20 min, luego en marcha.
        $l = [...self::lecturas(0, 1200, 0, 120), ...self::lecturas(1230, 1500, 20)];

        self::assertSame([[10, count($l) - 1]], TelefonosDePrueba::partir($l), 'empieza en la última lectura detenida');
    }

    public function testLoQueNuncaSeMovioNoEsTrayectoria(): void
    {
        self::assertSame([], TelefonosDePrueba::partir([]));
        self::assertSame([], TelefonosDePrueba::partir(self::lecturas(0, 3600, 0, 120)), 'quieto una hora');
        self::assertSame([], TelefonosDePrueba::partir([[0, 1], [60, 2]]), 'quieto un minuto');
        self::assertSame([[0, 0]], TelefonosDePrueba::partir([[5, 20]]));
    }

    public function testReconoceLosNombresSinDistinguirMayusculasNiEspacios(): void
    {
        $telefonos = new TelefonosDePrueba($this->createStub(Connection::class), $this->createStub(FuenteGps::class), ' celular-prueba , Otro,,');

        self::assertTrue($telefonos->es('CELULAR-PRUEBA'));
        self::assertTrue($telefonos->es(' otro '));
        self::assertFalse($telefonos->es('445'));
        self::assertFalse($telefonos->es(''));
    }

    public function testSinNombresNingunoEsDePrueba(): void
    {
        $telefonos = new TelefonosDePrueba($this->createStub(Connection::class), $this->createStub(FuenteGps::class), '');

        self::assertFalse($telefonos->es(''));
        self::assertFalse($telefonos->es('445'));
    }
}
