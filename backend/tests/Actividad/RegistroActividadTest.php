<?php

declare(strict_types=1);

namespace App\Tests\Actividad;

use App\Actividad\Particiones;
use App\Actividad\RegistroActividad;
use App\Actividad\TipoActividad;
use PHPUnit\Framework\TestCase;

final class RegistroActividadTest extends TestCase
{
    public function testAnotarCompletaLasColumnasYUsaElRequestIdDeLaPeticion(): void
    {
        $r = new RegistroActividad();
        $r->usarRequestId("3F2A9C1E-5B7D-4E8A-9C21-7D4E5F6A8B90");
        $r->anotar(TipoActividad::PETICION, ["usuario_id" => 4, "ruta" => "/api/x", "columna_que_no_existe" => 1]);

        $fila = $r->pendientes()[0];
        $this->assertSame("peticion", $fila["tipo"]);
        $this->assertSame(4, $fila["usuario_id"]);
        $this->assertSame("3f2a9c1e-5b7d-4e8a-9c21-7d4e5f6a8b90", $fila["request_id"]);
        $this->assertArrayNotHasKey("columna_que_no_existe", $fila);
        $this->assertNotNull($fila["fecha"]);
        $this->assertNull($fila["detalle"]);
    }

    public function testUnRequestIdInvalidoSeIgnoraYCadaPeticionTieneElSuyo(): void
    {
        $r = new RegistroActividad();
        $r->usarRequestId("'; DROP TABLE x");
        $primero = $r->requestId();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $primero);
        $this->assertSame($primero, $r->requestId(), "el mismo durante la petición");

        $r->reset();
        $this->assertNotSame($primero, $r->requestId());
        $this->assertSame([], $r->pendientes());
    }

    public function testParticionesPorMesEnHoraDeGuatemala(): void
    {
        // 2026-12-01 03:00 UTC todavía es 30 de noviembre en Guatemala.
        $this->assertSame("actividad_2026_11", Particiones::nombre(new \DateTimeImmutable("2026-12-01 03:00", new \DateTimeZone("UTC"))));
        $this->assertSame("2026-11-01 00:00:00-06:00", Particiones::inicioDeMes(new \DateTimeImmutable("2026-11-20 10:00", new \DateTimeZone("America/Guatemala")))->format("Y-m-d H:i:sP"));
    }
}
