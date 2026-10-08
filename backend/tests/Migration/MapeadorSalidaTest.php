<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoSalida;
use App\Migration\Mapeador;
use App\Migration\Salida\RutaMigrada;
use PHPUnit\Framework\TestCase;

final class MapeadorSalidaTest extends TestCase
{
    private const IDS = ["usuario" => 7, "cliente" => 9, "estacion" => 3, "agencia" => 40, "tipo_pago" => 1, "moneda" => 2];

    public function testEstadoDeLaSalidaSeTraduceDelLegado(): void
    {
        $this->assertSame(EstadoSalida::PROGRAMADA, Mapeador::estadoSalida(1));
        $this->assertSame(EstadoSalida::ABORDANDO, Mapeador::estadoSalida("2"));
        $this->assertSame(EstadoSalida::INICIADA, Mapeador::estadoSalida(3));
        $this->assertSame(EstadoSalida::CANCELADA, Mapeador::estadoSalida(4));
        $this->assertSame(EstadoSalida::FINALIZADA, Mapeador::estadoSalida(5));
        $this->assertSame(EstadoSalida::PROGRAMADA, Mapeador::estadoSalida(null), "desconocido → programada");

        $s = (new Mapeador())->salida(["id" => 81, "fecha" => "2026-10-03 20:00:00", "estado_id" => "5"], 1, 2, 3);
        $this->assertSame("finalizada", $s["estado"]);
        $this->assertSame("81", $s["legacy_id"]);
    }

    public function testPrecioEnMonedaBase(): void
    {
        $this->assertSame(34650, Mapeador::precioBoleto(["precioCalculado" => "45.00", "precioCalculadoMonedaBase" => "346.5"]), "pagado en USD: el importe en GTQ");
        $this->assertSame(12000, Mapeador::precioBoleto(["precioCalculado" => "120", "precioCalculadoMonedaBase" => null]), "boleto viejo sin moneda base");
        $this->assertSame(0, Mapeador::precioBoleto([]));
    }

    public function testCanalDeLaVenta(): void
    {
        $this->assertSame(CanalVenta::AGENCIA, Mapeador::canalVenta(["creacion_tipo" => "4", "pagina_web_reserva_id" => null]));
        $this->assertSame(CanalVenta::WEB, Mapeador::canalVenta(["creacion_tipo" => "1", "pagina_web_reserva_id" => "55"]));
        $this->assertSame(CanalVenta::ESTACION, Mapeador::canalVenta(["creacion_tipo" => "2"]));
    }

    public function testVentaDeTaquillaCertificada(): void
    {
        $v = (new Mapeador())->boletoVenta(["creacion_tipo" => 1, "fg_autorizacion" => " 313308 ", "fecha_creacion" => "2026-09-17 11:15:10"], self::IDS, 12);

        $this->assertSame("estacion", $v["canal"]);
        $this->assertSame(3, $v["estacion_id"]);
        $this->assertNull($v["agencia_id"]);
        $this->assertSame("certificada", $v["estado_facturacion"]);
        $this->assertSame("313308", $v["referencia_pago"]);
        $this->assertSame("2026-09-17 11:15:10", $v["created_at"]);
        $this->assertFalse($v["cortesia"]);
    }

    public function testVentaSinFacturaNuncaQuedaPendiente(): void
    {
        $v = (new Mapeador())->boletoVenta(["creacion_tipo" => 4, "autorizacion_cortesia_id" => "8", "voucher_agencia_id" => "3"], self::IDS, null);

        $this->assertSame("agencia", $v["canal"]);
        $this->assertSame(40, $v["agencia_id"]);
        $this->assertNull($v["estacion_id"]);
        $this->assertSame("no_aplica", $v["estado_facturacion"], "pendiente haría que el cron certifique ventas viejas");
        $this->assertTrue($v["cortesia"]);
        $this->assertTrue($v["voucher"]);
        $this->assertNull($v["referencia_pago"]);
    }

    public function testFacturaCertificadaComoSnapshot(): void
    {
        $old = [
            "fg_uuid" => "7572F593-BDBE-42CB-8D48-9D1D813C885A", "fg_dte" => "3183362763", "fg_serie" => "7572F593",
            "fg_fecha" => "2026-07-08 11:57:57", "fg_certificada" => "2026-07-08 11:57:57", "fg_total" => "270",
        ];
        $f = (new Mapeador())->factura($old, ["nit" => "123", "nombre" => "Fuente del Norte", "nombre_comercial" => null], ["nit" => " c/f ", "nombre" => ""]);

        $this->assertSame("7572f593-bdbe-42cb-8d48-9d1d813c885a", $f["uuid"]);
        $this->assertSame("3183362763", $f["dte"]);
        $this->assertSame(27000, $f["total_monto"]);
        $this->assertSame("CF", $f["receptop_nit"]);
        $this->assertSame("Consumidor final", $f["receptor_nombre"]);
        $this->assertSame("Fuente del Norte", $f["emisor_nombre"]);

        $this->assertNull((new Mapeador())->factura(["fg_uuid" => null, "fg_dte" => null], ["nit" => null, "nombre" => "X", "nombre_comercial" => null], ["nit" => null, "nombre" => null]), "sin certificar: no hay factura");
    }

    public function testSeguroVacioNoChocaConElIndiceUnico(): void
    {
        $b = (new Mapeador())->bus(["codigo" => "95", "placa" => "P-1", "numeroSeguro" => ""], 1);

        $this->assertNull($b["numeroSeguro"]);
    }

    public function testParesDeLaRuta(): void
    {
        $ruta = new RutaMigrada(10, 1, 3, ["1:3" => 10, "1:2" => 11, "2:3" => 12]);

        $this->assertSame(11, $ruta->entre(1, 2));
        $this->assertSame(10, $ruta->entre(1, 3));
        $this->assertNull($ruta->entre(3, 1), "los trayectos son vectoriales");
    }
}
