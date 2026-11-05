<?php

declare(strict_types=1);

namespace App\Tests\Actividad;

use App\Actividad\Detalle;
use App\Actividad\OperacionGraphql;
use App\Actividad\RutaNormalizada;
use PHPUnit\Framework\TestCase;

final class DetalleTest extends TestCase
{
    public function testFiltrarDejaSoloLaListaBlancaYTapaLosSecretos(): void
    {
        $cuerpo = [
            "salida" => 12,
            "asientos" => [3, 4],
            "cliente" => ["nit" => "123", "direccion" => "Zona 1"],
            "password" => "secreta",
            "tarjeta" => ["numero" => "4111"],
            "otro" => "no va",
            "boletos" => [["id" => 1, "comentario" => "x"]],
        ];

        $this->assertSame([
            "salida" => 12,
            "asientos" => [3, 4],
            "cliente" => ["nit" => "123"],
            "password" => "***",
            "tarjeta" => "***",
            "boletos" => [["id" => 1]],
        ], Detalle::filtrar($cuerpo, ["salida", "asientos", "cliente", "nit", "boletos", "id", "password"]));
    }

    public function testSinSecretosConservaLoDePantalla(): void
    {
        $this->assertSame(
            ["titulo" => "Venta", "datos" => ["token" => "***", "n" => 2]],
            Detalle::sinSecretos(["titulo" => "Venta", "datos" => ["token" => "abc", "n" => 2]]),
        );
    }

    public function testJsonVacioEsNullYElExcesoSeResume(): void
    {
        $this->assertNull(Detalle::json(["a" => null, "b" => []], 100));
        $this->assertSame('{"a":1}', Detalle::json(["a" => 1], 100));

        $grande = Detalle::json(["cuerpo" => str_repeat("x", 500)], 200);
        $this->assertNotNull($grande);
        $this->assertLessThanOrEqual(200, strlen($grande));
        $this->assertTrue(json_decode($grande, true)["recortado"]);
    }

    public function testRutaNormalizadaQuitaIdsYDiceElRegistro(): void
    {
        $r = RutaNormalizada::de("/api/salidas/123/croquis");
        $this->assertSame("/api/salidas/{id}/croquis", $r->ruta);
        $this->assertSame("salidas", $r->entidad);
        $this->assertSame(123, $r->registroId);

        $this->assertSame("/api/chat/archivos/{id}", RutaNormalizada::de("/api/chat/archivos/3f2a9c1e-5b7d-4e8a-9c21-7d4e5f6a8b90")->ruta);
        $this->assertNull(RutaNormalizada::de("/api/venta/cotizacion")->registroId);
    }

    public function testNombreDeLaOperacionGraphql(): void
    {
        $this->assertSame("query Salidas", OperacionGraphql::nombre(["query" => "query Salidas { salidas { id } }"]));
        $this->assertSame("mutation createBus", OperacionGraphql::nombre(["operationName" => "createBus", "query" => 'mutation createBus($i: X) { createBus(input: $i) { bus { id } } }']));
        $this->assertSame("query usuarios", OperacionGraphql::nombre(["query" => "{ usuarios { id } }"]));
        $this->assertNull(OperacionGraphql::nombre([]));
    }
}
