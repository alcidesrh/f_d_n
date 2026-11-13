<?php

declare(strict_types=1);

namespace App\Tests\Seguimiento;

use App\Chat\Predeterminado\Contenidos;
use App\Seguimiento\Irregularidad;
use App\Seguimiento\Irregularidades;
use App\Seguimiento\IrregularidadesEnCurso;
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

    public function testSoloSeAvisaLoDeAhora(): void
    {
        $ahora = self::T0 + 3600;
        $vieja = new Irregularidad(Irregularidad::EXCESO_VELOCIDAD, self::T0, self::T0 + 300, 14.7, -90.5, 110);
        $deAhora = new Irregularidad(Irregularidad::EXCESO_VELOCIDAD, $ahora - 400, $ahora - 60, 14.7, -90.5, 110);
        $parada = new Irregularidad(Irregularidad::PARADA_LARGA, $ahora - 1500, $ahora - 30, 14.7, -90.5, 0);

        self::assertSame([$deAhora, $parada], IrregularidadesEnCurso::filtrar([$vieja, $deAhora, $parada], self::T0, false, true, $ahora));
        // Sin ninguna parada ubicada, toda parada parecería fuera de lugar.
        self::assertSame([$deAhora], IrregularidadesEnCurso::filtrar([$deAhora, $parada], self::T0, false, false, $ahora));
        // Empieza donde se empezó a mirar: venía de antes y ya se avisó.
        self::assertSame([$deAhora], IrregularidadesEnCurso::filtrar([$deAhora, $parada], $parada->desde, true, true, $ahora));
        self::assertSame([$deAhora, $parada], IrregularidadesEnCurso::filtrar([$deAhora, $parada], $parada->desde, false, true, $ahora));
    }

    public function testUmbralesDelEntorno(): void
    {
        $u = IrregularidadesEnCurso::umbralesDe("80", " 5 ", "30");
        self::assertSame([80, 300, 1800], [$u->velocidadMaximaKmh, $u->segundosExceso, $u->segundosDetenido]);

        // Vacío, mal escrito o absurdo: el de siempre.
        $d = new UmbralesIrregularidad();
        $u = IrregularidadesEnCurso::umbralesDe(null, "", "muchos");
        self::assertSame([$d->velocidadMaximaKmh, $d->segundosExceso, $d->segundosDetenido], [$u->velocidadMaximaKmh, $u->segundosExceso, $u->segundosDetenido]);
        self::assertSame($d->velocidadMaximaKmh, IrregularidadesEnCurso::umbralesDe("5", null, null)->velocidadMaximaKmh);
    }

    public function testElAvisoDiceQueBusQuePasoYDonde(): void
    {
        $salida = ["bus" => "214", "empresa" => "FDN", "rutaNombre" => "Guatemala → Flores"];
        // 2026-10-29 15:42 en Guatemala (UTC-6).
        $desde = (new \DateTimeImmutable("2026-10-29 15:42", new \DateTimeZone("America/Guatemala")))->getTimestamp();

        $exceso = Contenidos::textoIrregularidad($salida, new Irregularidad(Irregularidad::EXCESO_VELOCIDAD, $desde, $desde + 290, 15.12345, -90.54321, 104), 90);
        self::assertStringStartsWith("Bus 214 · FDN · Guatemala → Flores\nExceso de velocidad desde las 3:42", $exceso);
        self::assertStringContainsString(": hasta 104 km/h (límite 90) durante 5 min.\n", $exceso);
        self::assertStringEndsWith("Ubicación: 15.12345, -90.54321", $exceso);

        $parada = Contenidos::textoIrregularidad($salida, new Irregularidad(Irregularidad::PARADA_LARGA, $desde, $desde + 1500, 15.1, -90.5, 0), 90);
        self::assertStringContainsString("Parada larga desde las 3:42", $parada);
        self::assertStringContainsString(": detenido 25 min fuera de las paradas de la ruta.\n", $parada);
    }

    public function testUmbralesPropios(): void
    {
        $u = new UmbralesIrregularidad(velocidadMaximaKmh: 70, segundosExceso: 60, segundosDetenido: 240);
        $h = Irregularidades::detectar([...self::lecturas([75, 78]), ...self::lecturas([0, 0, 0], self::CARRETERA, 120, self::T0 + 120)], [], $u);

        self::assertSame([Irregularidad::EXCESO_VELOCIDAD, Irregularidad::PARADA_LARGA], array_map(static fn (Irregularidad $i): string => $i->tipo, $h));
    }
}
