<?php

declare(strict_types=1);

namespace App\Tests\Metadata;

use App\Metadata\Cambios;
use App\Metadata\Estado;
use App\Metadata\Huellas;
use App\Metadata\ModoPublicacion;
use App\Metadata\PublicacionMetadata;
use App\Metadata\PublicacionRechazada;
use PHPUnit\Framework\TestCase;

final class RevisionesTest extends TestCase
{
    /** Revisión 7: schema `s1` desde la 3; Bus cambió en la 5, Piloto en la 7. */
    private static function publicado(): Estado
    {
        return new Estado(7, "s1", 3, [
            "Bus" => ["huella" => "b1", "revision" => 5],
            "Piloto" => ["huella" => "p1", "revision" => 7],
        ]);
    }

    public function testLaPrimeraRevisionTomaTodoComoNuevo(): void
    {
        $cambios = Estado::inicial()->cambios("s1", ["Bus" => "b1", "Piloto" => "p1"], true);
        $this->assertTrue($cambios->esquema);
        $this->assertSame(["Bus", "Piloto"], $cambios->agregadas);

        $estado = Estado::inicial()->siguiente(1, $cambios, "s1", ["Bus" => "b1", "Piloto" => "p1"]);
        $this->assertSame(1, $estado->esquemaRevision);
        $this->assertSame(["Bus" => 1, "Piloto" => 1], (array) $estado->manifiesto()["configs"]);
    }

    public function testSoloLaEntidadQueCambioTomaLaRevisionNueva(): void
    {
        $antes = self::publicado();
        $huellas = ["Bus" => "b2", "Piloto" => "p1"];
        $cambios = $antes->cambios(null, $huellas);
        $this->assertSame(["Bus"], $cambios->modificadas);
        $this->assertFalse($cambios->esquema, "sin huella del schema no se compara");

        $despues = $antes->siguiente(8, $cambios, null, $huellas);
        $this->assertSame(["Bus" => 8, "Piloto" => 7], (array) $despues->manifiesto()["configs"]);
        $this->assertSame(["revision" => 3, "huella" => "s1"], $despues->manifiesto()["esquema"], "el schema conserva la revisión en que cambió");
    }

    public function testUnaEntidadSinConfiguracionSeElimina(): void
    {
        $antes = self::publicado();
        $this->assertSame(["Piloto"], $antes->cambios(null, ["Piloto" => null])->eliminadas, "null: ya no tiene configuración");
        $this->assertSame(["Piloto"], $antes->cambios(null, ["Bus" => "b1"], true)->eliminadas, "con todas, la que falta se eliminó");
        $this->assertSame([], $antes->cambios(null, ["Bus" => "b1"])->eliminadas, "sin todas, la que falta no se miró");
        $this->assertSame([], $antes->cambios(null, ["Nueva" => null])->eliminadas, "sin configuración antes ni ahora no es un cambio");

        $cambios = $antes->cambios(null, ["Bus" => "b1"], true);
        $this->assertSame(["Bus" => 5], (array) $antes->siguiente(8, $cambios, null, ["Bus" => "b1"])->manifiesto()["configs"]);
    }

    public function testElSchemaCambiaSoloConOtraHuella(): void
    {
        $antes = self::publicado();
        $this->assertTrue($antes->cambios(null, null)->vacio());
        $this->assertTrue($antes->cambios("s1")->vacio());

        $cambios = $antes->cambios("s2");
        $this->assertSame(["esquema" => true, "agregadas" => [], "modificadas" => [], "eliminadas" => []], $cambios->toArray());
        $despues = $antes->siguiente(8, $cambios, "s2", null);
        $this->assertSame(["revision" => 8, "huella" => "s2"], $despues->manifiesto()["esquema"]);
        $this->assertSame(["Bus" => 5, "Piloto" => 7], (array) $despues->manifiesto()["configs"], "la configuración no se toca");
    }

    public function testRecargarTodoYElAvisoQuedanHastaLaSiguienteQueLosPida(): void
    {
        $antes = self::publicado();
        $conAviso = $antes->siguiente(8, new Cambios(), null, null, completa: true, aviso: true, nota: "Nuevo campo copiloto");
        $this->assertSame(8, $conAviso->manifiesto()["completa"]);
        $this->assertSame(["revision" => 8, "nota" => "Nuevo campo copiloto"], $conAviso->manifiesto()["aviso"]);

        $siguiente = $conAviso->siguiente(9, new Cambios(modificadas: ["Bus"]), null, ["Bus" => "b3"]);
        $this->assertSame(8, $siguiente->manifiesto()["completa"]);
        $this->assertSame(["revision" => 8, "nota" => "Nuevo campo copiloto"], $siguiente->manifiesto()["aviso"]);
    }

    public function testElManifiestoNoLlevaLasHuellasDeLaConfiguracion(): void
    {
        $manifiesto = self::publicado()->manifiesto();
        $this->assertSame(["revision", "esquema", "configs", "completa", "aviso"], array_keys($manifiesto));
        $this->assertSame('{"Bus":5,"Piloto":7}', json_encode($manifiesto["configs"]));
        $this->assertSame("{}", json_encode(Estado::inicial()->manifiesto()["configs"]), "sin configuraciones es un objeto vacío");
    }

    public function testElEstadoGuardadoSeLeeIgual(): void
    {
        $estado = self::publicado()->siguiente(8, new Cambios(), null, null, completa: true, aviso: true, nota: null);
        $leido = Estado::desde(8, json_decode(json_encode($estado->toArray(), JSON_THROW_ON_ERROR), true));
        $this->assertEquals($estado, $leido);
        $this->assertEquals(new Estado(3, "", 0), Estado::desde(3, ["configs" => ["Bus" => "roto"]]), "lo que no se entiende se ignora");
    }

    public function testCambiosSeLeenDeLoGuardado(): void
    {
        $cambios = new Cambios(true, ["Encomienda"], ["Bus"], ["Viejo"]);
        $this->assertEquals($cambios, Cambios::desde($cambios->toArray()));
        $this->assertFalse($cambios->vacio());
        $this->assertTrue(Cambios::desde([])->vacio());
    }

    public function testLaHuellaNoDependeDelOrdenDeLasClavesPeroSiDelDeLasListas(): void
    {
        $this->assertSame(
            Huellas::de(["listOptions" => ["pageSize" => 25, "density" => "compact"], "columnas" => [["field" => "a"], ["field" => "b"]]]),
            Huellas::de(["columnas" => [["field" => "a"], ["field" => "b"]], "listOptions" => ["density" => "compact", "pageSize" => 25]]),
        );
        $this->assertNotSame(
            Huellas::de(["columnas" => [["field" => "a"], ["field" => "b"]]]),
            Huellas::de(["columnas" => [["field" => "b"], ["field" => "a"]]]),
            "el orden de las columnas es parte de la configuración",
        );
        $this->assertNotSame(Huellas::de(["visible" => true]), Huellas::de(["visible" => 1]));
        $this->assertSame(64, strlen(Huellas::de(null)));
    }

    public function testElModoDesconocidoEsAutomatico(): void
    {
        $this->assertSame(ModoPublicacion::MANUAL, ModoPublicacion::desde(" Manual "));
        $this->assertSame(ModoPublicacion::AUTOMATICA, ModoPublicacion::desde("automatica"));
        $this->assertSame(ModoPublicacion::AUTOMATICA, ModoPublicacion::desde(""));
        $this->assertSame(ModoPublicacion::AUTOMATICA, ModoPublicacion::desde("a mano"));
    }

    public function testLaNotaSinEspaciosYConTope(): void
    {
        $this->assertSame("Se agregó el copiloto", PublicacionMetadata::nota("  Se agregó el copiloto \n"));
        $this->assertNull(PublicacionMetadata::nota("   "));
        $this->assertNull(PublicacionMetadata::nota(null));
        $this->assertSame(PublicacionMetadata::NOTA_MAXIMA, mb_strlen((string) PublicacionMetadata::nota(str_repeat("ñ", PublicacionMetadata::NOTA_MAXIMA))));

        try {
            PublicacionMetadata::nota(str_repeat("a", PublicacionMetadata::NOTA_MAXIMA + 1));
            $this->fail("debía rechazarse");
        } catch (PublicacionRechazada $e) {
            $this->assertSame(["nota_larga", 422], [$e->codigo, $e->estadoHttp]);
        }
    }
}
