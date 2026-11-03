<?php

declare(strict_types=1);

namespace App\Tests\Seguimiento;

use App\Seguimiento\VentanaTrayectoria;
use PHPUnit\Framework\TestCase;

final class VentanaTrayectoriaTest extends TestCase
{
    private static function h(string $hora): \DateTimeImmutable
    {
        return new \DateTimeImmutable("2026-11-03 $hora", new \DateTimeZone('America/Guatemala'));
    }

    public function testDeMediaHoraAntesATresHorasDespuesDeLaLlegada(): void
    {
        $v = VentanaTrayectoria::de(self::h('06:00'), self::h('14:00'), null);

        self::assertSame(self::h('05:30')->getTimestamp(), $v->desde);
        self::assertSame(self::h('17:00')->getTimestamp(), $v->hasta);
    }

    public function testNoEntraEnLaSiguienteSalidaDelBus(): void
    {
        $v = VentanaTrayectoria::de(self::h('06:00'), self::h('14:00'), self::h('15:00'));

        self::assertSame(self::h('14:30')->getTimestamp(), $v->hasta, 'termina donde empieza la de la siguiente');
    }

    public function testConUnHorarioApretadoLlegaAlMenosALaLlegadaProgramada(): void
    {
        $v = VentanaTrayectoria::de(self::h('06:00'), self::h('14:00'), self::h('14:15'));

        self::assertSame(self::h('14:00')->getTimestamp(), $v->hasta);
    }

    public function testSinLlegadaSuponeDoceHoras(): void
    {
        $v = VentanaTrayectoria::de(self::h('06:00'), null, null);

        self::assertSame(self::h('21:00')->getTimestamp(), $v->hasta);
    }

    public function testSinLlegadaValidaMandaLaSiguienteSalida(): void
    {
        // Horario con la llegada a la misma hora de la salida: no se sabe cuándo llega.
        $v = VentanaTrayectoria::de(self::h('06:00'), self::h('06:00'), self::h('07:30'));

        self::assertSame(self::h('07:00')->getTimestamp(), $v->hasta);
    }
}
