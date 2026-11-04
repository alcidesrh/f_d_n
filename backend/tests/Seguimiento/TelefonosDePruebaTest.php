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

    public function testMasDeMediaHoraSinLecturasEmpiezaOtraTrayectoria(): void
    {
        $t = TelefonosDePrueba::partir([1000, 1010, 1020, 1020 + 31 * self::MIN, 1030 + 31 * self::MIN]);

        self::assertSame([
            ['desde' => 1000, 'hasta' => 1020, 'lecturas' => 3],
            ['desde' => 1020 + 31 * self::MIN, 'hasta' => 1030 + 31 * self::MIN, 'lecturas' => 2],
        ], $t);
    }

    public function testMediaHoraJustaSigueLaMisma(): void
    {
        $t = TelefonosDePrueba::partir([0, 30 * self::MIN, 60 * self::MIN]);

        self::assertSame([['desde' => 0, 'hasta' => 60 * self::MIN, 'lecturas' => 3]], $t, 'cuenta el hueco entre lecturas seguidas, no desde la primera');
    }

    public function testSinLecturasNoHayTrayectorias(): void
    {
        self::assertSame([], TelefonosDePrueba::partir([]));
        self::assertSame([['desde' => 5, 'hasta' => 5, 'lecturas' => 1]], TelefonosDePrueba::partir([5]));
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
