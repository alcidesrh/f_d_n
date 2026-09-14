<?php

declare(strict_types=1);

namespace App\Tests\Migration\Job;

use App\Migration\Job\AlmacenDeJobs;
use App\Migration\Job\Progreso;
use App\Migration\Job\SalidaJobOutput;
use PHPUnit\Framework\TestCase;

final class AlmacenDeJobsTest extends TestCase
{
    private string $dir;

    private AlmacenDeJobs $almacen;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . "/migracion-test-" . uniqid();
        $this->almacen = new AlmacenDeJobs($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . "/*", GLOB_ONLYDIR) ?: [] as $dir) {
            @unlink($dir . "/status.json");
            @unlink($dir . "/output.log");
            @unlink($dir . "/cancelar.flag");
            @rmdir($dir);
        }
        @rmdir($this->dir);
    }

    public function testCrearYLeerDevuelveElEstadoInicial(): void
    {
        $job = $this->almacen->crear("entidad", [
            "entidad" => "salida",
            "cantidad" => 50,
        ]);

        self::assertSame("pending", $job["estado"]);
        self::assertSame("entidad", $job["tipo"]);
        self::assertSame("salida", $job["entidad"]);
        self::assertDirectoryExists($this->almacen->ruta($job["id"]));

        $leido = $this->almacen->leer($job["id"]);
        self::assertNotNull($leido);
        self::assertSame($job["id"], $leido["id"]);
        self::assertSame(50, $leido["parametros"]["cantidad"]);
    }

    public function testHayEjecutandoRespetaElSingleFlight(): void
    {
        $job = $this->almacen->crear("estaticos", []);

        $actual = $this->almacen->hayEjecutando();
        self::assertNotNull($actual);
        self::assertSame($job["id"], $actual["id"]);

        $job["estado"] = "done";
        $this->almacen->escribir($job["id"], $job);

        self::assertNull($this->almacen->hayEjecutando());
    }

    public function testCancelacionSolicitadaMarcaElFlag(): void
    {
        $job = $this->almacen->crear("iam", []);
        self::assertFalse($this->almacen->cancelacionSolicitada($job["id"]));

        $this->almacen->marcarCancelacion($job["id"]);

        self::assertTrue($this->almacen->cancelacionSolicitada($job["id"]));
    }

    public function testLogYTailPorOffset(): void
    {
        $job = $this->almacen->crear("config", []);

        $salida = new SalidaJobOutput($this->almacen, $job["id"]);
        $salida->writeln("<info>Iniciado</info>");
        $salida->writeln("Paso 2");

        $tail1 = $this->almacen->leerLog($job["id"], 0);
        self::assertStringContainsString("Iniciado", $tail1["log"]);
        self::assertStringContainsString("Paso 2", $tail1["log"]);
        self::assertGreaterThan(0, $tail1["offset"]);

        // El formatter base sin decoración elimina las etiquetas <info>.
        self::assertStringNotContainsString("<info>", $tail1["log"]);

        $tail2 = $this->almacen->leerLog($job["id"], $tail1["offset"]);
        self::assertSame("", $tail2["log"]);

        // Job sigue activo → fin=false; al terminar → fin=true.
        self::assertFalse($tail2["fin"]);
        $job["estado"] = "done";
        $this->almacen->escribir($job["id"], $job);
        self::assertTrue(
            $this->almacen->leerLog($job["id"], $tail2["offset"])["fin"],
        );
    }

    public function testProgresoPersisteContadoresYMensaje(): void
    {
        $job = $this->almacen->crear("todo", []);
        $progreso = new Progreso($this->almacen, $job["id"]);

        $progreso->informar(5, 10, ["empresa" => 3], "Migrando Empresas…");

        $estado = $this->almacen->leer($job["id"]);
        self::assertSame(5, $estado["procesados"]);
        self::assertSame(10, $estado["total"]);
        self::assertSame(["empresa" => 3], $estado["contadores"]);
        self::assertSame("Migrando Empresas…", $estado["mensaje"]);

        // Los contadores se acumulan entre informar().
        $progreso->informar(10, 10, ["piloto" => 1], "Listo");
        $estado = $this->almacen->leer($job["id"]);
        self::assertSame(
            ["empresa" => 3, "piloto" => 1],
            $estado["contadores"],
        );
    }
}
