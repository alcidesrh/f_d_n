<?php

declare(strict_types=1);

namespace App\Tests\Sincronizacion;

use App\Sincronizacion\Legado\Importacion\SondaLegado;
use App\Sincronizacion\Conciliador;
use App\Sincronizacion\Legado\ConexionLegado;
use App\Sincronizacion\Nuevo\ClientesLocales;
use App\Sincronizacion\Nuevo\EscritorLegado;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class IdentidadClienteTest extends TestCase
{
    public function testUnClienteConocidoTieneElMismoIdAlla(): void
    {
        $this->assertSame(12, $this->escritor(local: false, enLegado: true)->cliente(12));
    }

    public function testUnClienteSinLlevarNoSeUsaAllaAunqueElIdExista(): void
    {
        $this->expectExceptionMessage("todavía no se ha llevado");
        $this->escritor(local: true, enLegado: true)->cliente(12);
    }

    public function testUnClienteQueElLegadoNoTieneNoSeInventa(): void
    {
        $this->expectExceptionMessage("no existe en el legado");
        $this->escritor(local: false, enLegado: false)->cliente(12);
    }

    public function testConDocumentoSeUsaLaPersonaQueYaEstaAlla(): void
    {
        $legado = $this->createMock(ConexionLegado::class);
        $legado->method("valor")->willReturn(44);
        $legado->expects($this->never())->method("insertar");

        $escritor = new EscritorLegado($legado, $this->createStub(Connection::class));

        $this->assertSame(44, $escritor->altaCliente(["dpi" => "123", "nit" => "CF", "nombre" => "Ana"]));
    }

    public function testSinDocumentoSiempreEsUnaPersonaNueva(): void
    {
        $legado = $this->createMock(ConexionLegado::class);
        $legado->expects($this->never())->method("valor");
        $legado->expects($this->once())->method("insertar")->with("cliente", $this->callback(static fn(array $d) => $d["nitCreacionCopia"] === "CF"))->willReturn(45);

        $escritor = new EscritorLegado($legado, $this->createStub(Connection::class));

        $this->assertSame(45, $escritor->altaCliente(["dpi" => null, "nit" => "CF", "nombre" => "Ana"]));
    }

    public function testEnSoloTraerNoSeLlevaNada(): void
    {
        $db = $this->createStub(Connection::class);
        $db->method("fetchOne")->willReturn(3);
        $escritor = $this->createMock(EscritorLegado::class);
        $escritor->expects($this->never())->method("altaCliente");
        $legado = new ConexionLegado(new SondaLegado("h", 1, "d", "u", "c"), "h", "1", "d", "u", "c", soloTraer: true);

        $this->assertSame(3, (new ClientesLocales($db, $escritor, $legado))->realinear());
    }

    public function testChocaElMismoIdConOtroUsuario(): void
    {
        $alla = [["id" => 1, "username" => "Ana"], ["id" => 2, "username" => "luis"], ["id" => 3, "username" => "sin_migrar"]];
        $aqui = [["id" => 1, "username" => "ana"], ["id" => 2, "username" => "pedro"], ["id" => 4, "username" => "solo_aqui"]];

        $this->assertSame([2], Conciliador::usuariosQueChocan($alla, $aqui));
    }

    private function escritor(bool $local, bool $enLegado): EscritorLegado
    {
        $db = $this->createStub(Connection::class);
        $db->method("fetchOne")->willReturn($local ? 1 : false);
        $legado = $this->createStub(ConexionLegado::class);
        $legado->method("valor")->willReturn($enLegado ? 1 : null);

        return new EscritorLegado($legado, $db);
    }
}
