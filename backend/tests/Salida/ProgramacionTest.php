<?php

declare(strict_types=1);

namespace App\Tests\Salida;

use App\Entity\Enum\EstadoSalida;
use App\Salida\AgendaBus;
use App\Salida\FiltroSalidas;
use App\Salida\FirmaSalida;
use App\Salida\Programacion\Programacion;
use App\Salida\SalidaRechazada;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Reglas puras de la gestión de salidas (ADR-024): programador, agenda de buses, firma y filtro. */
final class ProgramacionTest extends TestCase
{
    private static function hoy(): \DateTimeImmutable
    {
        return new \DateTimeImmutable("2026-10-02 09:00");
    }

    /** @param array<string, mixed> $extra */
    private static function programacion(array $extra = []): Programacion
    {
        return Programacion::desdeArray([
            "trayectoId" => 7,
            "momentos" => [["hora" => "14:30", "busId" => 2], ["hora" => "6:05", "busId" => 1]],
            "desde" => "2026-10-05",
            ...$extra,
        ], self::hoy());
    }

    public function testSinHastaEsUnSoloDiaConLasHorasOrdenadas(): void
    {
        $p = self::programacion();
        $fechas = array_map(static fn(array $s) => [$s["fecha"]->format("Y-m-d H:i"), $s["busId"]], $p->salidas());

        $this->assertSame([["2026-10-05 06:05", 1], ["2026-10-05 14:30", 2]], $fechas);
        $this->assertSame("06:05", $p->momentos[0]->hora);
        $this->assertSame([1, 2], $p->busIds());
    }

    public function testRepiteCadaNDiasHastaLaFechaIncluida(): void
    {
        $diario = self::programacion(["hasta" => "2026-10-11"]);
        $this->assertCount(7, $diario->dias());
        $this->assertCount(14, $diario->salidas());

        $diaPorMedio = self::programacion(["hasta" => "2026-10-11", "intervaloDias" => 2]);
        $this->assertSame(
            ["2026-10-05", "2026-10-07", "2026-10-09", "2026-10-11"],
            array_map(static fn(\DateTimeImmutable $d) => $d->format("Y-m-d"), $diaPorMedio->dias()),
        );

        $cadaTres = self::programacion(["hasta" => "2026-10-12", "intervaloDias" => 3]);
        $dias = $cadaTres->dias();
        $this->assertSame("2026-10-11", end($dias)->format("Y-m-d"));
    }

    public function testHoyEsValido(): void
    {
        $this->assertCount(1, self::programacion(["desde" => "2026-10-02"])->dias());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidas(): iterable
    {
        yield "sin trayecto" => [["trayectoId" => null], "trayecto_requerido"];
        yield "sin momentos" => [["momentos" => []], "sin_momentos"];
        yield "hora inválida" => [["momentos" => [["hora" => "24:00", "busId" => 1]]], "hora_invalida"];
        yield "minutos inválidos" => [["momentos" => [["hora" => "10:60", "busId" => 1]]], "hora_invalida"];
        yield "sin bus" => [["momentos" => [["hora" => "10:00"]]], "bus_requerido"];
        yield "momento repetido" => [["momentos" => [["hora" => "10:00", "busId" => 1], ["hora" => "10:00", "busId" => 1]]], "momento_repetido"];
        yield "día pasado" => [["desde" => "2026-10-01"], "dia_pasado"];
        yield "fecha mal escrita" => [["desde" => "2026-02-30"], "fecha_invalida"];
        yield "hasta antes de desde" => [["hasta" => "2026-10-04"], "rango_invalido"];
        yield "más de un año" => [["hasta" => "2027-10-06"], "rango_demasiado_largo"];
        yield "intervalo cero" => [["intervaloDias" => 0], "intervalo_invalido"];
        yield "demasiadas salidas" => [[
            "hasta" => "2027-10-04",
            "momentos" => array_map(static fn(int $h) => ["hora" => sprintf("%02d:00", $h), "busId" => $h + 1], range(0, 9)),
        ], "demasiadas_salidas"];
    }

    /** @param array<string, mixed> $datos */
    #[DataProvider("invalidas")]
    public function testRechazaProgramacionesInvalidas(array $datos, string $codigo): void
    {
        try {
            self::programacion($datos);
            $this->fail("Debió rechazar: {$codigo}");
        } catch (SalidaRechazada $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }

    public function testMismoBusAMismaHoraConDistintoBusEsValido(): void
    {
        $p = self::programacion(["momentos" => [["hora" => "10:00", "busId" => 1], ["hora" => "10:00", "busId" => 2]]]);
        $this->assertCount(2, $p->salidas());
    }

    public function testAgendaDetectaSolapesSegunLaDuracion(): void
    {
        $agenda = new AgendaBus();
        $agenda->ocupar(1, new \DateTimeImmutable("2026-10-05 06:00"), 240, "A");

        $this->assertSame("A", $agenda->choque(1, new \DateTimeImmutable("2026-10-05 09:59"), 60));
        $this->assertSame("A", $agenda->choque(1, new \DateTimeImmutable("2026-10-05 05:30"), 60), "llega antes de que salga A pero sigue en ruta");
        $this->assertNull($agenda->choque(1, new \DateTimeImmutable("2026-10-05 10:00"), 60), "A ya llegó");
        $this->assertNull($agenda->choque(1, new \DateTimeImmutable("2026-10-05 05:00"), 60), "llega justo cuando sale A");
        $this->assertNull($agenda->choque(2, new \DateTimeImmutable("2026-10-05 07:00"), 60), "otro bus");
    }

    public function testSinDuracionSoloChocaALaMismaHora(): void
    {
        $agenda = new AgendaBus();
        $agenda->ocupar(1, new \DateTimeImmutable("2026-10-05 06:00"), null, "A");

        $this->assertSame("A", $agenda->choque(1, new \DateTimeImmutable("2026-10-05 06:00"), null));
        $this->assertNull($agenda->choque(1, new \DateTimeImmutable("2026-10-05 06:01"), null));
    }

    public function testFirmaIgnoraElDiaPeroNoLaHora(): void
    {
        $lunes = FirmaSalida::de(7, 2, 1, new \DateTimeImmutable("2026-10-05 06:30"));
        $this->assertSame($lunes, FirmaSalida::de(7, 2, 1, new \DateTimeImmutable("2026-10-12 06:30")));
        $this->assertNotSame($lunes, FirmaSalida::de(7, 2, 1, new \DateTimeImmutable("2026-10-12 06:31")));
        $this->assertNotSame($lunes, FirmaSalida::de(7, 3, 1, new \DateTimeImmutable("2026-10-12 06:30")));
        $this->assertNotSame($lunes, FirmaSalida::de(8, 2, 1, new \DateTimeImmutable("2026-10-12 06:30")));
        $this->assertNotSame($lunes, FirmaSalida::de(7, null, 1, new \DateTimeImmutable("2026-10-12 06:30")));

        $this->assertSame("2026-10-12 14:45", FirmaSalida::conHora(new \DateTime("2026-10-12 06:30"), new \DateTime("2026-10-05 14:45"))->format("Y-m-d H:i"));
    }

    public function testFiltroPorDefectoOmiteFinalizadasYCanceladas(): void
    {
        $f = FiltroSalidas::desdeQuery([]);
        $this->assertSame([EstadoSalida::INICIADA, EstadoSalida::ABORDANDO, EstadoSalida::PROGRAMADA], $f->estados);
        $this->assertSame(FiltroSalidas::PROXIMAS, $f->orden);
        $this->assertSame(25, $f->porPagina);
    }

    public function testFiltroLeeLaQueryEIgnoraLoInvalido(): void
    {
        $f = FiltroSalidas::desdeQuery([
            "estado" => "finalizada,nada,cancelada",
            "empresa" => "3",
            "trayecto" => "x",
            "desde" => "2026-10-01",
            "hasta" => "2026-13-01",
            "orden" => "fecha",
            "direccion" => "desc",
            "pagina" => "-4",
            "porPagina" => "33",
        ]);
        $this->assertSame([EstadoSalida::FINALIZADA, EstadoSalida::CANCELADA], $f->estados);
        $this->assertSame(3, $f->empresa);
        $this->assertNull($f->trayecto);
        $this->assertSame("2026-10-01", $f->desde?->format("Y-m-d"));
        $this->assertNull($f->hasta);
        $this->assertSame([FiltroSalidas::FECHA, "desc", 1, 25], [$f->orden, $f->direccion, $f->pagina, $f->porPagina]);

        $this->assertSame([], FiltroSalidas::desdeQuery(["estado" => ""])->estados, "vacío = todos");
    }
}
