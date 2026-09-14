<?php

declare(strict_types=1);

namespace App\Tests\Migration\Entidad;

use App\Migration\Entidad\EjecutorEntidad;
use App\Migration\Entidad\Especificacion;
use App\Migration\Entidad\MigradorEntidadInterface;
use App\Migration\Entidad\RegistroMigradores;
use App\Migration\Job\Progreso;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

final class EjecutorEntidadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . "/migracion-test-" . uniqid();
        @mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . "/*", GLOB_ONLYDIR) ?: [] as $dir) {
            @unlink($dir . "/status.json");
            @rmdir($dir);
        }
        @rmdir($this->dir);
    }
    /**
     * Grafo: bus → [empresa, marca]; trayecto → [estacion]; tarifa → [empresa, trayecto, usuario].
     *
     * @return array<string, string[]>
     */
    private function grafo(): array
    {
        return [
            "empresa" => [],
            "marca" => [],
            "estacion" => [],
            "usuario" => [],
            "bus" => ["empresa", "marca"],
            "trayecto" => ["estacion"],
            "tarifa" => ["empresa", "trayecto", "usuario"],
        ];
    }

    /**
     * @param array<string, string[]> $grafo
     */
    private function ejecutorPara(array $grafo): EjecutorEntidad
    {
        $registro = $this->createStub(RegistroMigradores::class);
        $registro
            ->method("tiene")
            ->willReturnCallback(fn(string $n): bool => isset($grafo[$n]));
        $registro
            ->method("obtener")
            ->willReturnCallback(function (string $n) use ($grafo) {
                $m = $this->createStub(MigradorEntidadInterface::class);
                $m->method("nombre")->willReturn($n);
                $m->method("dependencias")->willReturn($grafo[$n]);

                return $m;
            });

        return new EjecutorEntidad($registro);
    }

    public function testOrdenTopologicoDepePrimero(): void
    {
        $orden = $this->ejecutorPara($this->grafo())->orden("tarifa");

        self::assertSame(
            ["empresa", "estacion", "trayecto", "usuario", "tarifa"],
            $orden,
        );
        self::assertSame(
            ["empresa", "marca", "bus"],
            $this->ejecutorPara($this->grafo())->orden("bus"),
        );
    }

    public function testSinDependenciasDevuelveSoloLaEntidad(): void
    {
        $orden = $this->ejecutorPara($this->grafo())->orden("empresa");
        self::assertSame(["empresa"], $orden);
    }

    public function testDependenciaCiclicaLanzaExcepcion(): void
    {
        $grafo = [
            "a" => ["b"],
            "b" => ["a"],
        ];

        $this->expectException(\RuntimeException::class);
        $this->ejecutorPara($grafo)->orden("a");
    }

    public function testEjecutarRecorreElOrdenYTieneEnCuentaCancelacion(): void
    {
        $grafo = $this->grafo();
        $ejecutados = [];

        $registro = $this->createStub(RegistroMigradores::class);
        $registro
            ->method("tiene")
            ->willReturnCallback(fn(string $n): bool => isset($grafo[$n]));
        $registro
            ->method("obtener")
            ->willReturnCallback(function (string $n) use (
                $grafo,
                &$ejecutados,
            ) {
                $m = $this->createStub(MigradorEntidadInterface::class);
                $m->method("nombre")->willReturn($n);
                $m->method("dependencias")->willReturn($grafo[$n]);
                $m->method("migrar")->willReturnCallback(function () use (
                    $n,
                    &$ejecutados,
                ) {
                    $ejecutados[] = $n;

                    return [$n => 1];
                });

                return $m;
            });

        $progreso = new Progreso(
            new \App\Migration\Job\AlmacenDeJobs($this->dir),
            "job-test",
        );

        $resultado = new EjecutorEntidad($registro)->ejecutar(
            "tarifa",
            Especificacion::vacia("tarifa"),
            new NullOutput(),
            $progreso,
        );

        self::assertSame(
            ["empresa", "estacion", "trayecto", "usuario", "tarifa"],
            $ejecutados,
        );
        self::assertSame(
            [
                "empresa" => 1,
                "estacion" => 1,
                "trayecto" => 1,
                "usuario" => 1,
                "tarifa" => 1,
            ],
            $resultado,
        );
    }
}
