<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\Cliente;
use App\Venta\Clientes;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class ClienteComodinTest extends TestCase
{
    public function testElComodinNoTieneNitNiApellido(): void
    {
        $cliente = Cliente::comodin();

        $this->assertTrue($cliente->isComodin());
        $this->assertSame("CONSUMIDOR FINAL", $cliente->getNombreCompleto());
        $this->assertSame("CF", $cliente->getNit());
    }

    public function testUnClienteCualquieraNoEsComodin(): void
    {
        $this->assertFalse((new Cliente())->isComodin());
    }

    public function testSeUsaElQueYaExiste(): void
    {
        $existente = Cliente::comodin();
        $em = $this->em([$existente]);
        $em->expects($this->never())->method("persist");

        $this->assertSame($existente, (new Clientes($em))->comodin());
    }

    public function testLaPrimeraVezSeCrea(): void
    {
        $em = $this->em([null, null]);
        $em->expects($this->once())->method("persist")->with($this->callback(static fn(Cliente $c) => $c->isComodin()));

        $this->assertTrue((new Clientes($em))->comodin()->isComodin());
    }

    public function testSiOtroLoCreoMientrasEsperabaSeUsaEse(): void
    {
        $otro = Cliente::comodin();
        $em = $this->em([null, $otro]);
        $em->expects($this->never())->method("persist");

        $this->assertSame($otro, (new Clientes($em))->comodin());
    }

    public function testNoSeEditaDesdeLaVenta(): void
    {
        $this->expectExceptionObject(new VentaRechazada("El consumidor final no se edita: si el cliente da sus datos, regístrelo.", "cliente_comodin"));

        (new Clientes($this->createStub(EntityManagerInterface::class)))->actualizar(Cliente::comodin(), ["nombre" => "Ana"]);
    }

    /** @param list<?Cliente> $busquedas lo que devuelve cada búsqueda del comodín */
    private function em(array $busquedas): EntityManagerInterface
    {
        $repositorio = $this->createStub(EntityRepository::class);
        $repositorio->method("findOneBy")->willReturnOnConsecutiveCalls(...$busquedas);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method("getRepository")->willReturn($repositorio);
        $em->method("getConnection")->willReturn($this->createStub(Connection::class));
        $em->method("wrapInTransaction")->willReturnCallback(static fn(callable $f) => $f());

        return $em;
    }
}
