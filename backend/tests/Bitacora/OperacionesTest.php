<?php

declare(strict_types=1);

namespace App\Tests\Bitacora;

use App\Bitacora\TipoRegistro;
use App\Entity\BoletoAsiento;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\OperacionBoleto;
use App\Entity\Enum\OperacionSalida;
use App\Entity\Salida;
use PHPUnit\Framework\TestCase;

final class OperacionesTest extends TestCase
{
    public function testCadaEstadoDeSalidaDejaSuOperacion(): void
    {
        $this->assertNull(OperacionSalida::deEstado(EstadoSalida::PROGRAMADA), "programada es el estado inicial: ya lo dice «creada»");
        $this->assertSame(OperacionSalida::ABORDANDO, OperacionSalida::deEstado(EstadoSalida::ABORDANDO));
        $this->assertSame(OperacionSalida::INICIADA, OperacionSalida::deEstado(EstadoSalida::INICIADA));
        $this->assertSame(OperacionSalida::FINALIZADA, OperacionSalida::deEstado(EstadoSalida::FINALIZADA));
        $this->assertSame(OperacionSalida::CANCELADA, OperacionSalida::deEstado(EstadoSalida::CANCELADA));
    }

    public function testLosValoresGuardadosSonEstables(): void
    {
        // Se guardan tal cual en `bitacora.operacion`: cambiarlos rompería la historia.
        $this->assertSame(["creada", "abordando", "iniciada", "finalizada", "cancelada", "cambio_bus", "eliminada"], array_column(OperacionSalida::cases(), "value"));
        $this->assertSame(["creado", "anulado", "reasignado"], array_column(OperacionBoleto::cases(), "value"));
    }

    public function testTodaOperacionTieneEtiqueta(): void
    {
        foreach ([...OperacionSalida::cases(), ...OperacionBoleto::cases()] as $operacion) {
            $this->assertNotSame("", $operacion->etiqueta());
        }
    }

    public function testTipoDeRegistro(): void
    {
        $this->assertSame(TipoRegistro::SALIDA, TipoRegistro::deObjeto(new Salida()));
        $this->assertSame(TipoRegistro::BOLETO, TipoRegistro::deObjeto(new BoletoAsiento()));
        $this->assertNull(TipoRegistro::deObjeto(new \stdClass()));
        $this->assertSame(Salida::class, TipoRegistro::from("salida")->clase());
        $this->assertSame(OperacionBoleto::class, TipoRegistro::from("boleto")->operaciones());
    }
}
