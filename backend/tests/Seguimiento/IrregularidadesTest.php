<?php

declare(strict_types=1);

namespace App\Tests\Seguimiento;

use App\Seguimiento\Irregularidad;
use App\Seguimiento\Irregularidades;
use App\Seguimiento\UmbralesIrregularidad;
use PHPUnit\Framework\TestCase;

final class IrregularidadesTest extends TestCase
{
    private const T0 = 1_790_000_000;
    /** Una estación de la ruta y un punto en carretera a ~11 km. */
    private const ESTACION = [14.6000, -90.5000];
    private const CARRETERA = [14.7000, -90.5000];

    /**
     * Lecturas cada `$cada` segundos en un mismo sitio, con esas velocidades.
     *
     * @param list<int>            $velocidades
     * @param array{float, float}  $sitio
     *
     * @return list<array{int, float, float, int, int}>
     */
    private static function lecturas(array $velocidades, array $sitio = self::CARRETERA, int $cada = 60, int $desde = self::T0): array
    {
        return array_map(static fn (int $v, int $i): array => [$desde + $i * $cada, $sitio[0], $sitio[1], $v, 0], $velocidades, array_keys($velocidades));
    }

    public function testExcesoSostenido(): void
    {
        $h = Irregularidades::detectar(self::lecturas([80, 95, 104, 98, 99, 85]), []);

        self::assertCount(1, $h);
        self::assertSame(Irregularidad::EXCESO_VELOCIDAD, $h[0]->tipo);
        self::assertSame(self::T0 + 60, $h[0]->desde);
        self::assertSame(180, $h[0]->segundos());
        self::assertSame(104, $h[0]->maximaKmh);
    }

    public function testUnPicoSueltoNoEsExceso(): void
    {
        self::assertSame([], Irregularidades::detectar(self::lecturas([80, 120, 85, 110, 80]), []));
    }

    public function testElExcesoQueSigueSeDaConLaMismaClave(): void
    {
        $antes = Irregularidades::detectar(self::lecturas([95, 96, 97, 98]), []);
        $despues = Irregularidades::detectar(self::lecturas([95, 96, 97, 98, 99, 100]), []);

        self::assertCount(1, $antes);
        self::assertCount(1, $despues);
        self::assertSame($antes[0]->clave(), $despues[0]->clave());
        self::assertSame(300, $despues[0]->segundos());
    }

    public function testParadaLargaFueraDeLasParadasDeLaRuta(): void
    {
        // Detenido: una lectura cada 2 min (`HistorialGps::QUIETO_SEGUNDOS`), 22 min.
        $h = Irregularidades::detectar(self::lecturas(array_fill(0, 12, 0), self::CARRETERA, 120), [self::ESTACION]);

        self::assertCount(1, $h);
        self::assertSame(Irregularidad::PARADA_LARGA, $h[0]->tipo);
        self::assertSame(1320, $h[0]->segundos());
        self::assertSame(self::CARRETERA[0], $h[0]->lat);
    }

    public function testDetenidoEnUnaParadaDeLaRutaEsNormal(): void
    {
        self::assertSame([], Irregularidades::detectar(self::lecturas(array_fill(0, 30, 0), self::ESTACION, 120), [self::ESTACION]));
    }

    public function testUnaParadaCortaNoSeAvisa(): void
    {
        self::assertSame([], Irregularidades::detectar(self::lecturas([60, 0, 0, 0, 0, 0, 60], self::CARRETERA, 120), [self::ESTACION]));
    }

    public function testUnHuecoSinSenalCortaElTramo(): void
    {
        // Dos minutos de exceso, 10 min sin lecturas y dos más: ninguno llega a tres seguidos.
        $lecturas = [...self::lecturas([95, 96, 97]), ...self::lecturas([95, 96, 97], self::CARRETERA, 60, self::T0 + 720)];

        self::assertSame([], Irregularidades::detectar($lecturas, []));
    }

    public function testUmbralesPropios(): void
    {
        $u = new UmbralesIrregularidad(velocidadMaximaKmh: 70, segundosExceso: 60, segundosDetenido: 240);
        $h = Irregularidades::detectar([...self::lecturas([75, 78]), ...self::lecturas([0, 0, 0], self::CARRETERA, 120, self::T0 + 120)], [], $u);

        self::assertSame([Irregularidad::EXCESO_VELOCIDAD, Irregularidad::PARADA_LARGA], array_map(static fn (Irregularidad $i): string => $i->tipo, $h));
    }
}
