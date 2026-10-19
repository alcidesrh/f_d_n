<?php

declare(strict_types=1);

namespace App\Tests\Encomienda;

use App\Encomienda\EncomiendaRechazada;
use App\Encomienda\ReglasEncomienda;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\ModalidadEncomienda;
use PHPUnit\Framework\TestCase;

/** Reglas puras de los movimientos de una encomienda (ADR-035). */
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

    public function testSeEmbarcaDesdeLaBodegaDeLaEstacion(): void
    {
        $embarcar = fn(EstadoEncomienda $e, ?int $usuario = self::GUATE, EstadoSalida $s = EstadoSalida::PROGRAMADA, int $donde = self::GUATE, int $empresa = self::EMPRESA) => ReglasEncomienda::alEmbarcar($e, $donde, $usuario, $s, self::PARADAS, self::EMPRESA, $empresa);

        $this->assertSame(EstadoEncomienda::EMBARCADA, $embarcar(EstadoEncomienda::RECIBIDA));
        $this->assertSame(EstadoEncomienda::EMBARCADA, $embarcar(EstadoEncomienda::RECIBIDA, null));
        // Con la salida en camino va directo a tránsito (se sube en una parada intermedia).
        $this->assertSame(EstadoEncomienda::TRANSITO, $embarcar(EstadoEncomienda::DESEMBARCADA, self::RIO_DULCE, EstadoSalida::INICIADA, self::RIO_DULCE));

        $this->assertSame("estado", $this->codigo(fn() => $embarcar(EstadoEncomienda::EMBARCADA)));
        $this->assertSame("estado", $this->codigo(fn() => $embarcar(EstadoEncomienda::ENTREGADA)));
        $this->assertSame("otra_estacion", $this->codigo(fn() => $embarcar(EstadoEncomienda::RECIBIDA, self::FLORES)));
        $this->assertSame("salida_cerrada", $this->codigo(fn() => $embarcar(EstadoEncomienda::RECIBIDA, s: EstadoSalida::FINALIZADA)));
        $this->assertSame("otra_empresa", $this->codigo(fn() => $embarcar(EstadoEncomienda::RECIBIDA, empresa: 99)));
        // En la última parada la salida ya no sigue.
        $this->assertSame("fuera_de_ruta", $this->codigo(fn() => $embarcar(EstadoEncomienda::DESEMBARCADA, self::FLORES, donde: self::FLORES)));
        $this->assertSame("fuera_de_ruta", $this->codigo(fn() => $embarcar(EstadoEncomienda::RECIBIDA, 50, donde: 50)));
    }

    public function testSeDesembarcaEnUnaParadaDeSuSalida(): void
    {
        ReglasEncomienda::exigirDesembarcable(EstadoEncomienda::TRANSITO, self::RIO_DULCE, self::PARADAS);
        ReglasEncomienda::exigirDesembarcable(EstadoEncomienda::EMBARCADA, self::GUATE, self::PARADAS);

        $this->assertSame("estado", $this->codigo(fn() => ReglasEncomienda::exigirDesembarcable(EstadoEncomienda::RECIBIDA, self::GUATE, self::PARADAS)));
        $this->assertSame("fuera_de_ruta", $this->codigo(fn() => ReglasEncomienda::exigirDesembarcable(EstadoEncomienda::TRANSITO, 50, self::PARADAS)));
    }

    public function testSeEntregaDesembarcadaEnSuDestino(): void
    {
        ReglasEncomienda::exigirEntregable(EstadoEncomienda::DESEMBARCADA, self::FLORES, self::FLORES, self::FLORES);
        ReglasEncomienda::exigirEntregable(EstadoEncomienda::DESEMBARCADA, self::FLORES, self::FLORES, null);

        // En un transbordo todavía no se entrega.
        $this->assertSame("estado", $this->codigo(fn() => ReglasEncomienda::exigirEntregable(EstadoEncomienda::DESEMBARCADA, self::RIO_DULCE, self::FLORES, self::RIO_DULCE)));
        $this->assertSame("estado", $this->codigo(fn() => ReglasEncomienda::exigirEntregable(EstadoEncomienda::TRANSITO, null, self::FLORES, self::FLORES)));
        $this->assertSame("otra_estacion", $this->codigo(fn() => ReglasEncomienda::exigirEntregable(EstadoEncomienda::DESEMBARCADA, self::FLORES, self::FLORES, self::GUATE)));
    }

    public function testSeEntregaEnTransitoSiSuSalidaLlegaAlDestino(): void
    {
        // El bus llegó y nadie la desembarcó: se entrega igual (se desembarca al entregarla).
        ReglasEncomienda::exigirEntregable(EstadoEncomienda::TRANSITO, null, self::FLORES, self::FLORES, self::PARADAS);

        // Su salida no pasa por el destino (hará transbordo) o todavía no salió.
        $this->assertSame("estado", $this->codigo(fn() => ReglasEncomienda::exigirEntregable(EstadoEncomienda::TRANSITO, null, self::FLORES, self::FLORES, [self::GUATE, self::RIO_DULCE])));
        $this->assertSame("estado", $this->codigo(fn() => ReglasEncomienda::exigirEntregable(EstadoEncomienda::EMBARCADA, null, self::FLORES, self::FLORES, self::PARADAS)));
    }

    public function testSeAnulaSoloSiNoHaViajado(): void
    {
        ReglasEncomienda::exigirAnulable(EstadoEncomienda::RECIBIDA, ModalidadEncomienda::PAGADA, self::GUATE, self::GUATE, self::GUATE);
        // Se bajó en el origen antes de salir.
        ReglasEncomienda::exigirAnulable(EstadoEncomienda::DESEMBARCADA, ModalidadEncomienda::POR_COBRAR, self::GUATE, self::GUATE, null);

        $this->assertSame("estado", $this->codigo(fn() => ReglasEncomienda::exigirAnulable(EstadoEncomienda::DESEMBARCADA, ModalidadEncomienda::PAGADA, self::FLORES, self::GUATE, null)));
        $this->assertSame("estado", $this->codigo(fn() => ReglasEncomienda::exigirAnulable(EstadoEncomienda::EMBARCADA, ModalidadEncomienda::PAGADA, null, self::GUATE, null)));
        $this->assertSame("con_boleto", $this->codigo(fn() => ReglasEncomienda::exigirAnulable(EstadoEncomienda::EMBARCADA, ModalidadEncomienda::CON_BOLETO, null, self::GUATE, null)));
        $this->assertSame("otra_estacion", $this->codigo(fn() => ReglasEncomienda::exigirAnulable(EstadoEncomienda::RECIBIDA, ModalidadEncomienda::INTERNA, self::GUATE, self::GUATE, self::FLORES)));
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
