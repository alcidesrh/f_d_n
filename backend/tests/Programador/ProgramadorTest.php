<?php

declare(strict_types=1);

namespace App\Tests\Programador;

use App\Programador\EstadoDeTarea;
use App\Programador\MensajeDeTarea;
use App\Programador\Programa;
use App\Programador\Tarea;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

/** El catálogo de tareas, su horario y cuándo una se da por detenida (ADR-044). */
final class ProgramadorTest extends TestCase
{
    public function testCadaTareaReenviaSuMensajeALaColaTareas(): void
    {
        foreach (Tarea::cases() as $tarea) {
            $mensaje = $tarea->mensaje();
            self::assertInstanceOf(MensajeDeTarea::class, $mensaje);
            self::assertSame($tarea, $mensaje->tarea(), $tarea->value);

            $recurrente = Programa::recurrente($tarea, '06:00');
            $reenvio = iterator_to_array($recurrente->getMessages(new \Symfony\Component\Scheduler\Generator\MessageContext('fdn', 'x', $recurrente->getTrigger(), new \DateTimeImmutable())))[0];
            self::assertInstanceOf(RedispatchMessage::class, $reenvio);
            self::assertSame([Tarea::COLA_TAREAS], (array) $reenvio->transportNames);
            self::assertInstanceOf(PeriodicalTrigger::class, $recurrente->getTrigger());
        }
    }

    public function testLasDiariasCorrenASuHora(): void
    {
        $siguiente = Programa::recurrente(Tarea::REPORTES_DIARIOS, '06:30')->getTrigger()->getNextRunDate(new \DateTimeImmutable('2026-10-29 07:00'));
        self::assertSame('06:30', $siguiente?->format('H:i'));
        self::assertSame('01:30', Tarea::HORIZONTE_SALIDAS->hora('06:00'));
        self::assertNull(Tarea::PURGAR_VENTA->hora('06:00'));
    }

    public function testCuandoCorre(): void
    {
        self::assertSame('Cada minuto', Tarea::PURGAR_VENTA->cuando('06:00'));
        self::assertSame('Cada 10 minutos', Tarea::CERTIFICAR_PENDIENTES->cuando('06:00'));
        self::assertSame('Cada día a las 06:30', Tarea::REPORTES_DIARIOS->cuando('06:30'));
    }

    public function testHoraDeReportesInvalida(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Programa::recurrente(Tarea::REPORTES_DIARIOS, '6 am');
    }

    public function testDetenidaSegunSuTolerancia(): void
    {
        $ahora = new \DateTimeImmutable('2026-10-29 12:00');
        $t = Tarea::SINCRONIZAR_LEGADO; // cada minuto, tolera 15

        self::assertFalse((new EstadoDeTarea($t, ultimoExito: $ahora->modify('-10 minutes')))->detenida($ahora));
        self::assertTrue((new EstadoDeTarea($t, ultimoExito: $ahora->modify('-20 minutes')))->detenida($ahora));
        // Falla desde hace rato: cuenta desde la última buena, no desde el último intento.
        self::assertTrue((new EstadoDeTarea($t, ultimoInicio: $ahora->modify('-1 minute'), ultimoExito: $ahora->modify('-1 hour')))->detenida($ahora));
        // Nunca terminó bien: desde la primera vez que empezó.
        self::assertTrue((new EstadoDeTarea($t, primerInicio: $ahora->modify('-1 hour'), ultimoInicio: $ahora->modify('-1 minute')))->detenida($ahora));
    }

    public function testUnaQueNuncaCorrioSeJuzgaDesdeQueElProgramadorArranco(): void
    {
        $ahora = new \DateTimeImmutable('2026-10-29 12:00');
        $diaria = Tarea::REPORTES_DIARIOS; // tolera 26 h

        // Instalación nueva: nada corrió nunca, no hay alarma.
        self::assertFalse(EstadoDeTarea::deFila($diaria, null)->detenida($ahora));
        // El programador arrancó hace 3 h: la diaria aún no llega a su hora.
        self::assertFalse(EstadoDeTarea::deFila($diaria, null, $ahora->modify('-3 hours'))->detenida($ahora));
        // Hace dos días y la diaria nunca corrió: detenida.
        self::assertTrue(EstadoDeTarea::deFila($diaria, null, $ahora->modify('-2 days'))->detenida($ahora));
    }

    public function testDeFilaLeeElLatido(): void
    {
        $e = EstadoDeTarea::deFila(Tarea::PURGAR_VENTA, [
            'primer_inicio' => '2026-10-01 00:00:00',
            'ultimo_inicio' => '2026-10-29 11:59:00',
            'ultimo_fin' => '2026-10-29 11:59:01',
            'ultimo_exito' => '2026-10-29 11:59:01',
            'duracion_ms' => 820,
            'resultado' => 'ok',
            'resumen' => '{"reservas":2,"ventas":1}',
            'corridas' => 40,
            'fallos_seguidos' => 0,
        ]);
        $a = $e->toArray(new \DateTimeImmutable('2026-10-29 12:00'));
        self::assertSame(['reservas' => 2, 'ventas' => 1], $a['resumen']);
        self::assertSame(820, $a['duracionMs']);
        self::assertFalse($a['detenida']);
    }
}
