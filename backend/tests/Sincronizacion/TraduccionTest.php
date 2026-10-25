<?php

declare(strict_types=1);

namespace App\Tests\Sincronizacion;

use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\ModalidadEncomienda;
use App\Sincronizacion\Nuevo\Traduccion;
use PHPUnit\Framework\TestCase;

final class TraduccionTest extends TestCase
{
    public function testCadaEstadoDeBoletoTieneSuEstadoEnElLegado(): void
    {
        $this->assertSame(1, Traduccion::estadoBoleto(EstadoBoletoAsiento::EMITIDO));
        $this->assertSame(2, Traduccion::estadoBoleto(EstadoBoletoAsiento::CHEQUEADO));
        $this->assertSame(3, Traduccion::estadoBoleto(EstadoBoletoAsiento::TRANSITO));
        $this->assertSame(3, Traduccion::estadoBoleto(EstadoBoletoAsiento::FINALIZADO), "el legado no distingue finalizado");
        $this->assertSame(4, Traduccion::estadoBoleto(EstadoBoletoAsiento::ANULADO));
        $this->assertSame(5, Traduccion::estadoBoleto(EstadoBoletoAsiento::REASIGNADO));
        $this->assertSame(6, Traduccion::estadoBoleto(EstadoBoletoAsiento::CANCELADO));
        $this->assertSame(6, Traduccion::estadoBoleto(EstadoBoletoAsiento::POSPUESTO), "pospuesto libera como cancelado");
    }

    public function testOcuparElAsientoEsLoMismoEnLosDosSistemas(): void
    {
        foreach (EstadoBoletoAsiento::cases() as $e) {
            $libera = in_array($e->value, EstadoBoletoAsiento::liberanAsiento(), true);
            $this->assertSame(!$libera, Traduccion::ocupa(Traduccion::estadoBoleto($e)), $e->value);
        }
    }

    public function testTipoDeDocumentoDelBoleto(): void
    {
        $this->assertSame(1, Traduccion::tipoDocumentoBoleto(CanalVenta::ESTACION, false));
        $this->assertSame(3, Traduccion::tipoDocumentoBoleto(CanalVenta::ESTACION, true));
        $this->assertSame(5, Traduccion::tipoDocumentoBoleto(CanalVenta::AGENCIA, false));
        $this->assertSame(8, Traduccion::tipoDocumentoBoleto(CanalVenta::WEB, false));
    }

    public function testEncomienda(): void
    {
        $this->assertSame(1, Traduccion::estadoEncomienda(EstadoEncomienda::RECIBIDA));
        $this->assertSame(6, Traduccion::estadoEncomienda(EstadoEncomienda::ANULADA));
        $this->assertSame(2, Traduccion::tipoDocumentoEncomienda(ModalidadEncomienda::POR_COBRAR));
        $this->assertSame(4, Traduccion::tipoDocumentoEncomienda(ModalidadEncomienda::INTERNA));
        $this->assertSame(1, Traduccion::tipoDocumentoEncomienda(ModalidadEncomienda::CON_BOLETO));
        $this->assertSame(2, Traduccion::tipoEncomienda("12"));
        $this->assertSame(3, Traduccion::tipoEncomienda(null));
    }

    public function testDineroYCliente(): void
    {
        $this->assertSame("150.00", Traduccion::quetzales(15000));
        $this->assertSame("0.05", Traduccion::quetzales("5"));
        $this->assertSame("CF", Traduccion::nit(" c/f "));
        $this->assertSame("12345678", Traduccion::nit("1234567-8"));
        $this->assertSame(
            ["nombre" => "Ana María López de León", "nombre1" => "Ana", "nombre2" => "María", "apellido1" => "López", "apellido2" => "de León"],
            Traduccion::nombreCliente("Ana  María", "López de León"),
        );
        $this->assertSame("S/N", Traduccion::nombreCliente(null, "S/N")["nombre"]);
    }
}
