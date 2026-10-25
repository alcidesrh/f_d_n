<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use App\Migration\PdoLegado;
use PHPUnit\Framework\TestCase;

final class PdoLegadoTest extends TestCase
{
    private int $conexiones = 0;

    private bool $enTransaccion = false;

    public function testSeConectaAlPrimerUsoYSeRehaceTrasSoltar(): void
    {
        $pdo = $this->pdoLegado();
        $this->assertSame(0, $this->conexiones, "no se conecta al crearse");

        $pdo->query("SELECT 1");
        $pdo->prepare("SELECT 2");
        $this->assertSame(1, $this->conexiones, "reusa la conexión");

        $pdo->soltar();
        $pdo->exec("SELECT 1");
        $this->assertSame(2, $this->conexiones, "tras soltar, otra");

        $pdo->reset();
        $pdo->query("SELECT 1");
        $this->assertSame(3, $this->conexiones, "al terminar la petición, otra");
    }

    public function testNoSueltaLaConexionAMitadDeUnaTransaccion(): void
    {
        $pdo = $this->pdoLegado();
        $pdo->beginTransaction();
        $this->enTransaccion = true;
        $pdo->soltar();
        $pdo->commit();
        $this->assertSame(1, $this->conexiones);
    }

    private function pdoLegado(): PdoLegado
    {
        return new PdoLegado(function () {
            $this->conexiones++;
            $real = $this->createStub(\PDO::class);
            $real->method("query")->willReturn($this->createStub(\PDOStatement::class));
            $real->method("prepare")->willReturn($this->createStub(\PDOStatement::class));
            $real->method("exec")->willReturn(0);
            $real->method("beginTransaction")->willReturn(true);
            $real->method("commit")->willReturn(true);
            $real->method("inTransaction")->willReturnCallback(fn() => $this->enTransaccion);

            return $real;
        });
    }
}
