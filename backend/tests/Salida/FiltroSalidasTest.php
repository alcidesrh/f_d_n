<?php

declare(strict_types=1);

namespace App\Tests\Salida;

use App\Salida\FiltroSalidas;
use PHPUnit\Framework\TestCase;

/** La hora exacta del listado de salidas: ese minuto en cada día del rango. */
final class FiltroSalidasTest extends TestCase
{
    public function testLaHoraSeLeeSoloComoHHMM(): void
    {
        self::assertSame('06:30', FiltroSalidas::desdeQuery(['hora' => '06:30'])->hora);
        self::assertNull(FiltroSalidas::desdeQuery(['hora' => '6:30'])->hora);
        self::assertNull(FiltroSalidas::desdeQuery(['hora' => '24:00'])->hora);
        self::assertNull(FiltroSalidas::desdeQuery([])->hora);
    }

    public function testUnaFranjaPorDiaDelRango(): void
    {
        $f = FiltroSalidas::desdeQuery(['desde' => '2026-11-14', 'hasta' => '2026-11-16', 'hora' => '06:30']);
        $franjas = array_map(static fn(array $x) => [$x[0]->format('Y-m-d H:i'), $x[1]->format('Y-m-d H:i')], $f->franjasDeHora());

        self::assertSame([
            ['2026-11-14 06:30', '2026-11-14 06:31'],
            ['2026-11-15 06:30', '2026-11-15 06:31'],
            ['2026-11-16 06:30', '2026-11-16 06:31'],
        ], $franjas);
    }

    public function testSinRangoOConUnoMuyLargoLaHoraNoFiltra(): void
    {
        self::assertSame([], FiltroSalidas::desdeQuery(['desde' => '2026-11-14', 'hora' => '06:30'])->franjasDeHora());
        self::assertSame([], FiltroSalidas::desdeQuery(['desde' => '2026-01-01', 'hasta' => '2026-12-31', 'hora' => '06:30'])->franjasDeHora());
    }
}
