<?php

declare(strict_types=1);

namespace App\Tests\Salida;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoSalida;
use App\Salida\Abordaje\ReglasAbordaje;
use App\Salida\SalidaRechazada;
use PHPUnit\Framework\TestCase;

/** Reglas puras del abordaje: chequear boletos por salida (ADR-032). */
final class AbordajeTest extends TestCase
{
    private const ORIGEN = 1;
    private const INTERMEDIA = 2;
    private const DESTINO = 3;

    private static function salida(): \DateTimeImmutable
    {
        return new \DateTimeImmutable("2026-10-11 18:00");
    }

    public function testSelectorSegunLaEstacionDelUsuario(): void
    {
        $visible = static fn(EstadoSalida $e, ?int $estacion) => ReglasAbordaje::visibleEnSelector($e, $estacion, self::ORIGEN, [self::INTERMEDIA, self::DESTINO]);

        // De oficina: todo lo que no terminó.
        $this->assertTrue($visible(EstadoSalida::PROGRAMADA, null));
        $this->assertTrue($visible(EstadoSalida::INICIADA, null));
        $this->assertFalse($visible(EstadoSalida::FINALIZADA, null));
        $this->assertFalse($visible(EstadoSalida::CANCELADA, null));
        // En el origen: las que todavía no salen.
        $this->assertTrue($visible(EstadoSalida::PROGRAMADA, self::ORIGEN));
        $this->assertTrue($visible(EstadoSalida::ABORDANDO, self::ORIGEN));
        $this->assertFalse($visible(EstadoSalida::INICIADA, self::ORIGEN));
        // Más adelante: las que ya vienen en camino.
        $this->assertFalse($visible(EstadoSalida::ABORDANDO, self::INTERMEDIA));
        $this->assertTrue($visible(EstadoSalida::INICIADA, self::INTERMEDIA));
        $this->assertTrue($visible(EstadoSalida::INICIADA, self::DESTINO));
        // Fuera de la ruta: nada.
        $this->assertFalse($visible(EstadoSalida::INICIADA, 99));
    }

    public function testVentanaDelSelector(): void
    {
        [$desde, $hasta] = ReglasAbordaje::ventanaSelector(self::salida());

        $this->assertSame("2026-10-10 18:00", $desde->format("Y-m-d H:i"));
        $this->assertSame("2026-10-12 00:00", $hasta->format("Y-m-d H:i"));
    }

    public function testSoloElOrigenOOficinaAbordanEInician(): void
    {
        ReglasAbordaje::exigirDesdeElOrigen(null, self::ORIGEN, "Melchor", "iniciar la salida");
        ReglasAbordaje::exigirDesdeElOrigen(self::ORIGEN, self::ORIGEN, "Melchor", "iniciar la salida");

        $this->expectExceptionObject(new SalidaRechazada("Solo un usuario de la estación Melchor (origen de la ruta) o de oficina puede iniciar la salida."));
        ReglasAbordaje::exigirDesdeElOrigen(self::INTERMEDIA, self::ORIGEN, "Melchor", "iniciar la salida");
    }

    public function testSeAbordaUnaProgramadaDesdeDosHorasAntes(): void
    {
        ReglasAbordaje::exigirAbordable(EstadoSalida::PROGRAMADA, self::salida(), self::salida()->modify("-2 hours"));
        ReglasAbordaje::exigirAbordable(EstadoSalida::PROGRAMADA, self::salida(), self::salida()->modify("+1 hour"));

        $this->assertSame("fuera_de_ventana", $this->codigo(fn() => ReglasAbordaje::exigirAbordable(EstadoSalida::PROGRAMADA, self::salida(), self::salida()->modify("-121 minutes"))));
        $this->assertSame("salida_no_programada", $this->codigo(fn() => ReglasAbordaje::exigirAbordable(EstadoSalida::ABORDANDO, self::salida(), self::salida())));
    }

    public function testSoloSeIniciaLaQueAborda(): void
    {
        ReglasAbordaje::exigirIniciable(EstadoSalida::ABORDANDO);

        $this->assertSame("salida_no_abordando", $this->codigo(fn() => ReglasAbordaje::exigirIniciable(EstadoSalida::PROGRAMADA)));
    }

    public function testVentanaDeChequeo(): void
    {
        $chequear = fn(EstadoSalida $e, string $cuando) => $this->codigo(fn() => ReglasAbordaje::exigirChequeable($e, self::salida(), self::salida()->modify($cuando)));

        $this->assertNull($chequear(EstadoSalida::ABORDANDO, "-2 hours"));
        $this->assertNull($chequear(EstadoSalida::INICIADA, "+30 hours"));
        $this->assertSame("fuera_de_ventana", $chequear(EstadoSalida::ABORDANDO, "-3 hours"));
        $this->assertSame("fuera_de_ventana", $chequear(EstadoSalida::INICIADA, "+31 hours"));
        $this->assertSame("salida_no_abordando", $chequear(EstadoSalida::PROGRAMADA, "now"));
        $this->assertSame("salida_no_abordando", $chequear(EstadoSalida::FINALIZADA, "now"));
    }

    public function testCadaBoletoLoChequeaLaEstacionDondeSube(): void
    {
        $this->assertTrue(ReglasAbordaje::puedeChequearEn(null, self::INTERMEDIA));
        $this->assertTrue(ReglasAbordaje::puedeChequearEn(self::INTERMEDIA, self::INTERMEDIA));
        $this->assertFalse(ReglasAbordaje::puedeChequearEn(self::ORIGEN, self::INTERMEDIA));
    }

    public function testChequeadoConLaSalidaIniciadaQuedaEnTransito(): void
    {
        $this->assertSame(EstadoBoletoAsiento::CHEQUEADO, ReglasAbordaje::estadoAlChequear(EstadoSalida::ABORDANDO));
        $this->assertSame(EstadoBoletoAsiento::TRANSITO, ReglasAbordaje::estadoAlChequear(EstadoSalida::INICIADA));
        $this->assertTrue(EstadoBoletoAsiento::EMITIDO->puedeTransicionarA(EstadoBoletoAsiento::TRANSITO));
        $this->assertTrue(EstadoBoletoAsiento::EMITIDO->puedeTransicionarA(EstadoBoletoAsiento::CANCELADO));
        // Chequeado que al final no viaja: se cancela desde el listado de boletos.
        $this->assertTrue(EstadoBoletoAsiento::CHEQUEADO->puedeTransicionarA(EstadoBoletoAsiento::CANCELADO));
        $this->assertFalse(EstadoBoletoAsiento::TRANSITO->puedeTransicionarA(EstadoBoletoAsiento::CANCELADO));
    }

    public function testCancelarDesdeElListado(): void
    {
        ReglasAbordaje::exigirCancelable(EstadoBoletoAsiento::EMITIDO, EstadoSalida::PROGRAMADA);
        ReglasAbordaje::exigirCancelable(EstadoBoletoAsiento::CHEQUEADO, EstadoSalida::ABORDANDO);
        ReglasAbordaje::exigirCancelable(EstadoBoletoAsiento::EMITIDO, EstadoSalida::INICIADA);

        foreach ([EstadoBoletoAsiento::TRANSITO, EstadoBoletoAsiento::ANULADO, EstadoBoletoAsiento::CANCELADO, EstadoBoletoAsiento::FINALIZADO] as $estado) {
            $this->assertSame("boleto_no_cancelable", $this->codigo(fn() => ReglasAbordaje::exigirCancelable($estado, EstadoSalida::PROGRAMADA)), $estado->value);
        }
        $this->assertSame("salida_no_cancelable", $this->codigo(fn() => ReglasAbordaje::exigirCancelable(EstadoBoletoAsiento::EMITIDO, EstadoSalida::FINALIZADA)));
        $this->assertSame("salida_no_cancelable", $this->codigo(fn() => ReglasAbordaje::exigirCancelable(EstadoBoletoAsiento::EMITIDO, EstadoSalida::CANCELADA)));
    }

    public function testRevertirUnCancelado(): void
    {
        ReglasAbordaje::exigirRevertible(EstadoBoletoAsiento::CANCELADO, EstadoSalida::PROGRAMADA, false, false);
        ReglasAbordaje::exigirRevertible(EstadoBoletoAsiento::CANCELADO, EstadoSalida::INICIADA, false, false);
        $this->assertTrue(EstadoBoletoAsiento::CANCELADO->puedeTransicionarA(EstadoBoletoAsiento::EMITIDO));

        foreach ([EstadoBoletoAsiento::EMITIDO, EstadoBoletoAsiento::CHEQUEADO, EstadoBoletoAsiento::ANULADO, EstadoBoletoAsiento::FINALIZADO] as $estado) {
            $this->assertSame("boleto_no_cancelado", $this->codigo(fn() => ReglasAbordaje::exigirRevertible($estado, EstadoSalida::PROGRAMADA, false, false)), $estado->value);
        }
        $this->assertSame("salida_no_cancelable", $this->codigo(fn() => ReglasAbordaje::exigirRevertible(EstadoBoletoAsiento::CANCELADO, EstadoSalida::FINALIZADA, false, false)));
        $this->assertSame("cortesia", $this->codigo(fn() => ReglasAbordaje::exigirRevertible(EstadoBoletoAsiento::CANCELADO, EstadoSalida::PROGRAMADA, true, false)));
        $this->assertSame("agencia", $this->codigo(fn() => ReglasAbordaje::exigirRevertible(EstadoBoletoAsiento::CANCELADO, EstadoSalida::PROGRAMADA, false, true)));
    }

    public function testAlIniciarSeCancelanLosNoChequeadosDelOrigen(): void
    {
        $this->assertSame(EstadoBoletoAsiento::TRANSITO, ReglasAbordaje::estadoAlIniciar(EstadoBoletoAsiento::CHEQUEADO, 0));
        $this->assertSame(EstadoBoletoAsiento::TRANSITO, ReglasAbordaje::estadoAlIniciar(EstadoBoletoAsiento::CHEQUEADO, 2));
        $this->assertSame(EstadoBoletoAsiento::CANCELADO, ReglasAbordaje::estadoAlIniciar(EstadoBoletoAsiento::EMITIDO, 0));
        // Sube más adelante: lo chequea su parada.
        $this->assertNull(ReglasAbordaje::estadoAlIniciar(EstadoBoletoAsiento::EMITIDO, 2));
        $this->assertNull(ReglasAbordaje::estadoAlIniciar(EstadoBoletoAsiento::ANULADO, 0));
    }

    public function testSoloElDestinoOOficinaFinalizanUnaIniciada(): void
    {
        ReglasAbordaje::exigirDesdeElDestino(null, self::DESTINO, "Guatemala", "finalizar la salida");
        ReglasAbordaje::exigirDesdeElDestino(self::DESTINO, self::DESTINO, "Guatemala", "finalizar la salida");
        ReglasAbordaje::exigirFinalizable(EstadoSalida::INICIADA);

        $this->assertSame("estacion_no_destino", $this->codigo(fn() => ReglasAbordaje::exigirDesdeElDestino(self::ORIGEN, self::DESTINO, "Guatemala", "finalizar la salida")));
        $this->assertSame("salida_no_iniciada", $this->codigo(fn() => ReglasAbordaje::exigirFinalizable(EstadoSalida::ABORDANDO)));
        $this->assertSame("salida_no_iniciada", $this->codigo(fn() => ReglasAbordaje::exigirFinalizable(EstadoSalida::FINALIZADA)));
    }

    public function testAlFinalizarSeCierranLosBoletos(): void
    {
        $this->assertSame([EstadoBoletoAsiento::FINALIZADO], ReglasAbordaje::pasosAlFinalizar(EstadoBoletoAsiento::TRANSITO));
        $this->assertSame([EstadoBoletoAsiento::TRANSITO, EstadoBoletoAsiento::FINALIZADO], ReglasAbordaje::pasosAlFinalizar(EstadoBoletoAsiento::CHEQUEADO));
        $this->assertSame([EstadoBoletoAsiento::CANCELADO], ReglasAbordaje::pasosAlFinalizar(EstadoBoletoAsiento::EMITIDO));
        $this->assertSame([], ReglasAbordaje::pasosAlFinalizar(EstadoBoletoAsiento::ANULADO));
        $this->assertSame([], ReglasAbordaje::pasosAlFinalizar(EstadoBoletoAsiento::CANCELADO));
        // Cada paso es una transición válida.
        foreach (EstadoBoletoAsiento::cases() as $estado) {
            $actual = $estado;
            foreach (ReglasAbordaje::pasosAlFinalizar($estado) as $paso) {
                $this->assertTrue($actual->puedeTransicionarA($paso), "{$actual->value} → {$paso->value}");
                $actual = $paso;
            }
        }
    }

    public function testElCanceladoLiberaElAsiento(): void
    {
        $this->assertSame(["anulado", "reasignado", "cancelado", "pospuesto"], EstadoBoletoAsiento::liberanAsiento());
    }

    public function testSeleccion(): void
    {
        $this->assertSame([[1, 2], [3]], ReglasAbordaje::seleccion([1, "2", 2, "x"], [3]));
        $this->assertSame("sin_boletos", $this->codigo(fn() => ReglasAbordaje::seleccion([], [])));
        $this->assertSame("seleccion_invalida", $this->codigo(fn() => ReglasAbordaje::seleccion([1], [1])));
    }

    /** El código del rechazo, o null si no rechaza. */
    private function codigo(callable $f): ?string
    {
        try {
            $f();
        } catch (SalidaRechazada $e) {
            return $e->codigo;
        }

        return null;
    }
}
