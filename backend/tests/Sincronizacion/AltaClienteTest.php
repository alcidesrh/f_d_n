<?php

declare(strict_types=1);

namespace App\Tests\Sincronizacion;

use App\Entity\Cliente;
use App\Sincronizacion\Interruptor;
use App\Sincronizacion\Nuevo\AltaCliente;
use App\Sincronizacion\Nuevo\ClientesLocales;
use App\Sincronizacion\Nuevo\EscritorLegado;
use App\Sincronizacion\Nuevo\IdCliente;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AltaClienteTest extends TestCase
{
    public function testEncendidaElClienteTomaElIdDelLegado(): void
    {
        $escritor = $this->createMock(EscritorLegado::class);
        $escritor->expects($this->once())->method("altaCliente")
            ->with($this->callback(static fn(array $d) => $d["nombre"] === "Ana López" && $d["nit"] === "CF" && $d["correo"] === "ana@ejemplo.com"))
            ->willReturn(14513051);
        $cliente = self::cliente();

        $this->alta(activo: true, escritor: $escritor)->prePersist(new PrePersistEventArgs($cliente, $this->em(existe: false)));

        $this->assertSame(14513051, $cliente->getId());
    }

    public function testApagadaNoSeTocaElLegado(): void
    {
        $escritor = $this->createMock(EscritorLegado::class);
        $escritor->expects($this->never())->method("altaCliente");

        $this->alta(activo: false, escritor: $escritor)->prePersist(new PrePersistEventArgs(self::cliente(), $this->em(existe: false)));
    }

    public function testSoloTraerNoCreaClientes(): void
    {
        $escritor = $this->createMock(EscritorLegado::class);
        $escritor->expects($this->never())->method("altaCliente");

        $this->expectExceptionObject(new VentaRechazada("En modo «solo traer» no se crean clientes: el id lo da el legado."));
        $this->alta(activo: true, soloTraer: true, escritor: $escritor)->prePersist(new PrePersistEventArgs(self::cliente(), $this->em(existe: false)));
    }

    public function testLaPersonaQueYaEstaAquiSeRechaza(): void
    {
        $escritor = $this->createStub(EscritorLegado::class);
        $escritor->method("altaCliente")->willReturn(7);

        try {
            $this->alta(activo: true, escritor: $escritor)->prePersist(new PrePersistEventArgs(self::cliente(), $this->em(existe: true)));
            $this->fail("Tenía que rechazarse.");
        } catch (VentaRechazada $e) {
            $this->assertSame("cliente_existente", $e->codigo);
            $this->assertSame(["cliente" => 7], $e->detalle);
        }
    }

    public function testSinLegadoNoSeCreaElCliente(): void
    {
        $escritor = $this->createMock(EscritorLegado::class);
        $escritor->method("altaCliente")->willThrowException(new \PDOException("caído"));
        $escritor->expects($this->once())->method("cerrarConexion");

        try {
            $this->alta(activo: true, escritor: $escritor)->prePersist(new PrePersistEventArgs(self::cliente(), $this->em(existe: false)));
            $this->fail("Tenía que rechazarse.");
        } catch (VentaRechazada $e) {
            $this->assertSame(503, $e->estadoHttp);
        }
    }

    public function testApagadaElClienteQuedaAnotado(): void
    {
        $locales = $this->createMock(ClientesLocales::class);
        $locales->expects($this->once())->method("anotar")->with(55);
        $cliente = self::cliente();
        $cliente->setId(55);

        $this->alta(activo: false, locales: $locales)->postPersist(new PostPersistEventArgs($cliente, $this->em(existe: false)));
    }

    public function testEncendidaNoSeAnota(): void
    {
        $locales = $this->createMock(ClientesLocales::class);
        $locales->expects($this->never())->method("anotar");
        $cliente = self::cliente();
        $cliente->setId(55);

        $this->alta(activo: true, locales: $locales)->postPersist(new PostPersistEventArgs($cliente, $this->em(existe: false)));
    }

    public function testSoloClienteUsaElGeneradorPropio(): void
    {
        $cliente = new ClassMetadata(Cliente::class);
        $otra = new ClassMetadata(\stdClass::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $alta = $this->alta(activo: false);
        $alta->loadClassMetadata(new LoadClassMetadataEventArgs($cliente, $em));
        $alta->loadClassMetadata(new LoadClassMetadataEventArgs($otra, $em));

        $this->assertInstanceOf(IdCliente::class, $cliente->idGenerator);
        $this->assertFalse($cliente->isIdGeneratorIdentity() || $cliente->idGenerator->isPostInsertGenerator());
        $this->assertSame(ClassMetadata::GENERATOR_TYPE_NONE, $otra->generatorType);
        $this->assertInstanceOf(IdCliente::class, unserialize(serialize($cliente->idGenerator)), "va en la caché de la metadata");
    }

    private function alta(bool $activo, bool $soloTraer = false, ?EscritorLegado $escritor = null, ?ClientesLocales $locales = null): AltaCliente
    {
        $interruptor = $this->createStub(Interruptor::class);
        $interruptor->method("activo")->willReturn($activo);
        $interruptor->method("instalada")->willReturn(true);
        $interruptor->method("soloTraer")->willReturn($soloTraer);

        return new AltaCliente(
            $interruptor,
            $escritor ?? $this->createStub(EscritorLegado::class),
            $locales ?? $this->createStub(ClientesLocales::class),
            new NullLogger(),
        );
    }

    private function em(bool $existe): EntityManagerInterface
    {
        $db = $this->createStub(Connection::class);
        $db->method("fetchOne")->willReturn($existe ? 1 : false);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method("getConnection")->willReturn($db);

        return $em;
    }

    private static function cliente(): Cliente
    {
        return (new Cliente())->setNombre("Ana")->setApellido("López")->setNit("CF")->setEmail("ana@ejemplo.com");
    }
}
