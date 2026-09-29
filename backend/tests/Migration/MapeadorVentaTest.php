<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use App\Migration\Mapeador;
use PHPUnit\Framework\TestCase;

final class MapeadorVentaTest extends TestCase
{
    public function testAgenciaDesdeEstacionDelLegado(): void
    {
        $a = (new Mapeador())->agencia([
            "id" => "40", "nombre" => "Agencia Flores", "direccion" => "Petén",
            "agencia_saldo" => "1250.50", "agencia_porciento_bonificacion" => "0.05000000",
            "moneda_sigla" => "gtq", "activo" => "1",
        ]);

        $this->assertSame(40, $a["id"]);
        $this->assertSame(125050, $a["saldo"], "saldo en centavos");
        $this->assertSame("5.00", $a["porcentaje_bonificacion"], "fracción → porcentaje");
        $this->assertSame("GTQ", $a["moneda"]);
        $this->assertSame("estacion-40", $a["legacy_id"]);
    }

    public function testPorcentajeYaExpresadoEnPorcentaje(): void
    {
        $a = (new Mapeador())->agencia(["id" => 1, "nombre" => "X", "agencia_porciento_bonificacion" => "7.5", "agencia_saldo" => null]);

        $this->assertSame("7.50", $a["porcentaje_bonificacion"]);
        $this->assertSame(0, $a["saldo"]);
        $this->assertSame("GTQ", $a["moneda"]);
    }

    public function testClienteLlevaDocumentoYNacionalidad(): void
    {
        $c = (new Mapeador())->cliente(["id" => 9, "nombre" => "Ana", "dpi" => "1234567890101", "tipo_documento_id" => "2", "nacionalidad_id" => "0"]);

        $this->assertSame("1234567890101", $c["numero_documento"]);
        $this->assertSame(2, $c["tipo_documento_id"]);
        $this->assertNull($c["nacionalidad_id"]);
    }
}
