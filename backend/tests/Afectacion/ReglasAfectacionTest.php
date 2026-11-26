<?php

declare(strict_types=1);

namespace App\Tests\Afectacion;

use App\Acceso\Ambito;
use App\Acceso\Concesion;
use App\Acceso\PermisosUsuario;
use App\Acceso\Ubicacion;
use App\Afectacion\ConsultaAfectaciones;
use App\Afectacion\ReglasAfectacion as R;
use App\Entity\Afectacion;
use App\Entity\Bus;
use App\Entity\Empresa;
use App\Entity\Enum\PrioridadAfectacion;
use App\Entity\Estacion;
use App\Entity\TipoAfectacion;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

/** Dónde cae una afectación para cada capacidad, quién la ve y los textos (ADR-070). */
final class ReglasAfectacionTest extends TestCase
{
    private const DIMENSIONES = [
        "afectacion.reportar" => ["empresa", "estacion"],
        "afectacion.informatica" => ["empresa", "estacion"],
        "afectacion.flota" => ["empresa"],
        "afectacion.supervisar" => ["empresa"],
        "afectacion.tipos" => [],
    ];

    /** @param list<string> $capacidades */
    private static function permisos(array $capacidades, Ambito $ambito): PermisosUsuario
    {
        return new PermisosUsuario([new Concesion(1, 1, "Rol", false, $capacidades, [], $ambito)], array_keys(self::DIMENSIONES));
    }

    public function testCadaCapacidadMiraSoloSusDimensiones(): void
    {
        self::assertEquals(Ubicacion::empresa(1)->conEstacion(10), R::ubicacion(["empresa", "estacion"], 1, 10));
        self::assertEquals(Ubicacion::empresa(1), R::ubicacion(["empresa"], 1, 10));
        self::assertEquals(Ubicacion::ninguna(), R::ubicacion([], 1, 10));
    }

    public function testQuienAtiendeLaFlotaNoNecesitaLaEstacion(): void
    {
        // Mecánico de la empresa 1 asignado solo a la estación 20.
        $mecanico = self::permisos(["afectacion.flota"], new Ambito([1], [20]));

        self::assertTrue(R::alcanza($mecanico, ["afectacion.flota"], self::DIMENSIONES, 1, 10));
        self::assertTrue(R::alcanza($mecanico, ["afectacion.flota"], self::DIMENSIONES, 1, null));
        self::assertFalse(R::alcanza($mecanico, ["afectacion.flota"], self::DIMENSIONES, 2, 20));
    }

    public function testQuienAtiendeInformaticaSoloLaDeSusEstaciones(): void
    {
        $informatico = self::permisos(["afectacion.informatica"], new Ambito([1], [10, 11]));

        self::assertTrue(R::alcanza($informatico, ["afectacion.informatica"], self::DIMENSIONES, 1, 11));
        self::assertFalse(R::alcanza($informatico, ["afectacion.informatica"], self::DIMENSIONES, 1, 12));
        // Sin estación (un bus en ruta): solo la ve quien tiene todas.
        self::assertFalse(R::alcanza($informatico, ["afectacion.informatica"], self::DIMENSIONES, 1, null));
        self::assertTrue(R::alcanza(self::permisos(["afectacion.informatica"], new Ambito([1], null)), ["afectacion.informatica"], self::DIMENSIONES, 1, null));
    }

    public function testQuienVeUnTipo(): void
    {
        $t = (new TipoAfectacion())->setOwner("afectacion.reportar")->setTarget("afectacion.informatica")->setEscalamiento(["afectacion.supervisar", "", "afectacion.supervisar", "iam.ver"]);

        self::assertSame(["afectacion.supervisar", "iam.ver"], $t->getEscalamiento());
        self::assertSame(["afectacion.informatica", "afectacion.supervisar", "iam.ver"], $t->capacidadesEscaladas());
        self::assertSame(["afectacion.reportar", "afectacion.informatica", "afectacion.supervisar", "iam.ver"], R::capacidadesQueVen($t));
    }

    public function testCondicionDelListadoPorGrupoDeTipos(): void
    {
        $capacidades = ["afectacion.informatica", "afectacion.reportar", "afectacion.supervisar"];

        // Sin ninguna: no ve el grupo.
        self::assertNull(ConsultaAfectaciones::condicionGrupo(self::permisos(["afectacion.flota"], Ambito::total()), $capacidades, self::DIMENSIONES, "v0"));

        // Una sin restricción: todo el grupo.
        self::assertSame([null, []], ConsultaAfectaciones::condicionGrupo(self::permisos(["afectacion.supervisar"], Ambito::total()), $capacidades, self::DIMENSIONES, "v0"));

        // La de estación filtra por empresa y estación; la de supervisar, solo por empresa.
        $p = new PermisosUsuario([
            new Concesion(1, 1, "Taquilla", false, ["afectacion.reportar"], [], new Ambito([1], [10])),
            new Concesion(2, 2, "Gerencia", false, ["afectacion.supervisar"], [], new Ambito([2], [10])),
        ], array_keys(self::DIMENSIONES));
        [$sql, $parametros] = ConsultaAfectaciones::condicionGrupo($p, $capacidades, self::DIMENSIONES, "v0");

        self::assertSame("(((a.empresa IN (:v0_1_e0) AND a.estacion IN (:v0_1_s0))) OR ((a.empresa IN (:v0_2_e0))))", $sql);
        self::assertSame(["v0_1_e0" => [1], "v0_1_s0" => [10], "v0_2_e0" => [2]], $parametros);
    }

    public function testLapso(): void
    {
        self::assertSame("0 min", R::lapso(-5));
        self::assertSame("45 min", R::lapso(45));
        self::assertSame("3 h", R::lapso(200));
        self::assertSame("47 h", R::lapso(47 * 60 + 59));
        self::assertSame("2 días", R::lapso(48 * 60));
    }

    public function testTextosDeLosAvisos(): void
    {
        $empresa = (new Empresa())->setNombre("Transportes del Norte");
        $estacion = (new Estacion())->setNombre("Estación Central");
        $tipo = (new TipoAfectacion())->setNombre("Impresora averiada")->setPrioridad(PrioridadAfectacion::URGENTE);
        $quien = (new Usuario())->setUsername("jperez");
        $a = new Afectacion($tipo, "  La impresora de boletos no enciende.  ", $empresa, $estacion, null, $quien, new \DateTimeImmutable("2026-11-26 08:00"));
        self::fijarId($a, 12);

        self::assertSame("Estación Central", R::lugar($a));
        self::assertSame(
            "Afectación #12 · Urgente: Impresora averiada en Estación Central.\n«La impresora de boletos no enciende.»\nLa reportó jperez.",
            R::aviso($a, false, new \DateTimeImmutable("2026-11-26 08:01")),
        );
        self::assertStringStartsWith("Sin resolver desde hace 3 h (aviso n.º 1).\n", R::aviso($a, true, new \DateTimeImmutable("2026-11-26 11:10")));

        $bus = (new Bus())->setCodigo("120")->setEmpresa($empresa);
        $averia = new Afectacion($tipo, "No arranca.", $empresa, $estacion, $bus, null, new \DateTimeImmutable("2026-11-26 08:00"), "gps:5");
        self::assertSame("bus 120 (Estación Central)", R::lugar($averia));
        self::assertStringEndsWith("La detectó el sistema.", R::aviso($averia, false, new \DateTimeImmutable("2026-11-26 08:00")));

        $a->resolver($quien, "Se cambió el cabezal.", new \DateTimeImmutable("2026-11-26 09:00"));
        self::assertSame("Se resolvió su afectación #12 (Impresora averiada en Estación Central). La resolvió jperez. «Se cambió el cabezal.»", R::avisoResuelta($a));
    }

    public static function fijarId(object $entidad, int $id): void
    {
        (new \ReflectionProperty($entidad, "id"))->setValue($entidad, $id);
    }
}
