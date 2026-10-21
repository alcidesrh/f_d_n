<?php

declare(strict_types=1);

namespace App\Tests\Encomienda;

use App\Encomienda\EncomiendaRechazada;
use App\Encomienda\ReglasEncomienda;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoSalida;
use PHPUnit\Framework\TestCase;

/**
 * Reglas puras de los movimientos de una encomienda (ADR-035) que dependen de
 * la operación: la salida, la parada y quién opera. Las de la encomienda
 * misma (guards del workflow) están en `FlujoEncomiendaTest`.
 */
final class ReglasEncomiendaTest extends TestCase
{
    private const GUATE = 1;
    private const RIO_DULCE = 2;
    private const FLORES = 3;
    private const EMPRESA = 7;
    /** Guatemala → Río Dulce → Flores. */
    private const PARADAS = [self::GUATE, self::RIO_DULCE, self::FLORES];

    private function codigo(callable $f): ?string
    {
        try {
            $f();
        } catch (EncomiendaRechazada $e) {
            return $e->codigo;
        }

        return null;
    }

    public function testSeEmbarcaEnUnaSalidaAbiertaDeSuEmpresaQueSigue(): void
    {
        $embarcar = fn(int $donde = self::GUATE, EstadoSalida $s = EstadoSalida::PROGRAMADA, int $empresa = self::EMPRESA) => ReglasEncomienda::exigirSalidaParaEmbarcar($donde, $s, self::PARADAS, self::EMPRESA, $empresa);

        $this->assertNull($this->codigo(fn() => $embarcar()));
        // Con la salida en camino se sube en una parada intermedia.
        $this->assertNull($this->codigo(fn() => $embarcar(self::RIO_DULCE, EstadoSalida::INICIADA)));

        $this->assertSame("salida_cerrada", $this->codigo(fn() => $embarcar(s: EstadoSalida::FINALIZADA)));
        $this->assertSame("salida_cerrada", $this->codigo(fn() => $embarcar(s: EstadoSalida::CANCELADA)));
        $this->assertSame("otra_empresa", $this->codigo(fn() => $embarcar(empresa: 99)));
        // En la última parada la salida ya no sigue.
        $this->assertSame("fuera_de_ruta", $this->codigo(fn() => $embarcar(self::FLORES)));
        $this->assertSame("fuera_de_ruta", $this->codigo(fn() => $embarcar(50)));
    }

    public function testSeDesembarcaEnUnaParadaDeSuSalida(): void
    {
        $this->assertNull($this->codigo(fn() => ReglasEncomienda::exigirParadaDeSalida(self::RIO_DULCE, self::PARADAS)));
        $this->assertSame("fuera_de_ruta", $this->codigo(fn() => ReglasEncomienda::exigirParadaDeSalida(50, self::PARADAS)));
    }

    public function testOperaUnUsuarioDeEsaEstacionODeOficina(): void
    {
        $this->assertNull($this->codigo(fn() => ReglasEncomienda::exigirEstacion(self::GUATE, self::GUATE, "otra")));
        $this->assertNull($this->codigo(fn() => ReglasEncomienda::exigirEstacion(null, self::GUATE, "otra")));
        $this->assertSame("otra_estacion", $this->codigo(fn() => ReglasEncomienda::exigirEstacion(self::FLORES, self::GUATE, "otra")));
    }

    public function testLlegaADestino(): void
    {
        $this->assertTrue(ReglasEncomienda::llegaADestino(self::PARADAS, self::GUATE, self::FLORES));
        $this->assertFalse(ReglasEncomienda::llegaADestino(self::PARADAS, self::FLORES, self::GUATE));
        $this->assertFalse(ReglasEncomienda::llegaADestino(self::PARADAS, self::GUATE, 50));
    }

    public function testTransicionesDelEstado(): void
    {
        $this->assertTrue(EstadoEncomienda::DESEMBARCADA->puedeTransicionarA(EstadoEncomienda::EMBARCADA));
        $this->assertTrue(EstadoEncomienda::DESEMBARCADA->puedeTransicionarA(EstadoEncomienda::ENTREGADA));
        $this->assertFalse(EstadoEncomienda::RECIBIDA->puedeTransicionarA(EstadoEncomienda::ENTREGADA));
        $this->assertFalse(EstadoEncomienda::TRANSITO->puedeTransicionarA(EstadoEncomienda::ANULADA));
        $this->assertSame([], EstadoEncomienda::ENTREGADA->transicionesPermitidas());
    }
}
