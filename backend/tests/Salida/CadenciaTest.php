<?php

declare(strict_types=1);

namespace App\Tests\Salida;

use App\Salida\Horizonte\Cadencia;
use App\Salida\Horizonte\HorizonteSalidas;
use PHPUnit\Framework\TestCase;

/** Las cuentas del horizonte de los esquemas automáticos (ADR-044). */
final class CadenciaTest extends TestCase
{
    private static function d(string $dia): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dia);
    }

    public function testElSiguienteDiaDeLaCadencia(): void
    {
        $ancla = self::d('2026-11-01');
        self::assertSame('2026-11-01', Cadencia::siguiente($ancla, 3, self::d('2026-10-20'))->format('Y-m-d'));
        self::assertSame('2026-11-04', Cadencia::siguiente($ancla, 3, self::d('2026-11-02'))->format('Y-m-d'));
        self::assertSame('2026-11-04', Cadencia::siguiente($ancla, 3, self::d('2026-11-04 15:00'))->format('Y-m-d'));
        self::assertSame('2026-11-05', Cadencia::siguiente($ancla, 1, self::d('2026-11-05'))->format('Y-m-d'));
    }

    public function testLaVentanaVaDeLaFronteraAlHorizonte(): void
    {
        $hoy = self::d('2026-10-29');
        self::assertSame(
            ['2026-10-29', '2027-01-26'],
            array_map(static fn($d) => $d->format('Y-m-d'), array_values(Cadencia::ventana($hoy, 90, null))),
        );
        self::assertSame('2026-12-01', Cadencia::ventana($hoy, 90, self::d('2026-11-30'))['desde']->format('Y-m-d'));
        // Una frontera vieja (el esquema estuvo apagado) no despliega días pasados.
        self::assertSame('2026-10-29', Cadencia::ventana($hoy, 90, self::d('2026-09-01'))['desde']->format('Y-m-d'));
        self::assertNull(Cadencia::ventana($hoy, 90, self::d('2027-01-26')));
    }

    public function testLosTramosEmpiezanEnDiasDeLaCadenciaYCubrenLaVentana(): void
    {
        $tramos = Cadencia::tramos(self::d('2026-10-01'), 1, self::d('2026-10-29'), self::d('2026-11-30'));
        self::assertSame(
            [['2026-10-29', '2026-11-11'], ['2026-11-12', '2026-11-25'], ['2026-11-26', '2026-11-30']],
            array_map(static fn(array $t) => [$t['desde']->format('Y-m-d'), $t['hasta']->format('Y-m-d')], $tramos),
        );

        // Día por medio: tramos de 14 días que empiezan en días de salida.
        $tramos = Cadencia::tramos(self::d('2026-10-30'), 2, self::d('2026-10-29'), self::d('2026-11-20'));
        self::assertSame('2026-10-30', $tramos[0]['desde']->format('Y-m-d'));
        self::assertSame('2026-11-13', $tramos[1]['desde']->format('Y-m-d'));

        // Cada 20 días: un tramo por salida.
        $tramos = Cadencia::tramos(self::d('2026-10-01'), 20, self::d('2026-10-29'), self::d('2026-12-31'));
        self::assertSame(['2026-11-10', '2026-11-30', '2026-12-20'], array_map(static fn(array $t) => $t['desde']->format('Y-m-d'), $tramos));

        self::assertSame([], Cadencia::tramos(self::d('2026-10-01'), 20, self::d('2026-10-22'), self::d('2026-11-09')));
    }

    public function testElAvisoDeConflictosListaLosDiasYElChoque(): void
    {
        $texto = HorizonteSalidas::textoConflictos('Diario Petén', 'Guatemala - Flores', [
            ['fecha' => '2026-11-03T06:00:00-06:00', 'bus' => '101', 'choque' => ['id' => 9, 'ruta' => 'Flores - Guatemala', 'fecha' => '2026-11-03T05:00:00-06:00']],
        ]);
        self::assertStringContainsString('no creó 1 salida porque', $texto);
        self::assertStringContainsString('• 03/11 06:00 · bus 101 · ocupado en Flores - Guatemala (03/11 05:00)', $texto);
    }
}
