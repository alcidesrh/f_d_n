<?php

declare(strict_types=1);

namespace App\Tests\Bitacora;

use App\Actividad\AjustesBitacora;
use App\Actividad\RegistroActividad;
use App\Bitacora\RegistradorCambios;
use App\Entity\Base\Base;
use App\Entity\BoletoVenta;
use App\Entity\Bus;
use App\Entity\ConfiguracionBitacora;
use App\Entity\Enum\OperacionCambio;
use App\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class RegistradorCambiosTest extends TestCase
{
    public function testLosCambiosTapanSecretosEIgnoranLoQueCambiaSolo(): void
    {
        $this->assertSame(
            [
                "nombre" => ["de" => "Viejo", "a" => "Nuevo"],
                "password" => ["de" => "***", "a" => "***"],
                "claveCifrada" => ["de" => "***", "a" => "***"],
                "activo" => ["de" => true, "a" => false],
                "fecha" => ["de" => "2026-11-01 08:00", "a" => null],
            ],
            RegistradorCambios::cambios([
                "nombre" => ["Viejo", "Nuevo"],
                "password" => ["h1", "h2"],
                "claveCifrada" => ["a", "b"],
                "activo" => [true, false],
                "fecha" => [new \DateTimeImmutable("2026-11-01 08:00"), null],
                "updatedAt" => [new \DateTimeImmutable(), new \DateTimeImmutable()],
            ]),
        );
    }

    public function testValorLegible(): void
    {
        $this->assertSame(str_repeat("x", 200), RegistradorCambios::valor(str_repeat("x", 300)));
        $this->assertSame('{"a":1}', RegistradorCambios::valor(["a" => 1]));
        $this->assertNull(RegistradorCambios::valor(new \stdClass()));
    }

    public function testClaseCorta(): void
    {
        $this->assertSame("Bus", RegistradorCambios::clase(new Bus()));
    }

    public function testLosValoresGuardadosSonEstables(): void
    {
        $this->assertSame(["creado", "modificado", "eliminado"], array_column(OperacionCambio::cases(), "value"));
    }

    /**
     * Doctrine quita el id de la entidad al borrarla: la etiqueta (que sin
     * `nombre` es el id) se toma en `onFlush`. Antes, borrar una venta con un
     * usuario en sesión —la venta cuya factura no salió— fallaba en
     * `postRemove` y deshacía el borrado.
     */
    public function testAlEliminarAnotaElIdYLaEtiquetaDeAntesDelBorrado(): void
    {
        $venta = (new BoletoVenta())->setId(86869);
        $usuario = (new Usuario())->setUsername("taquilla");
        $usuario->setId(1);

        $security = $this->createStub(Security::class);
        $security->method("getUser")->willReturn($usuario);
        $registrador = new RegistradorCambios($security, new AjustesBitacora(fija: new ConfiguracionBitacora()), new RegistroActividad());

        $insertado = null;
        $conexion = $this->createMock(Connection::class);
        $conexion->expects($this->once())->method("insert")->willReturnCallback(function (string $tabla, array $fila) use (&$insertado) {
            $insertado = $fila;

            return 1;
        });
        $uow = $this->createStub(UnitOfWork::class);
        $uow->method("getScheduledEntityDeletions")->willReturn([$venta]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method("getUnitOfWork")->willReturn($uow);
        $em->method("getConnection")->willReturn($conexion);

        $registrador->onFlush(new OnFlushEventArgs($em));
        // Lo que hace Doctrine al borrar (`TypedNoDefaultReflectionProperty::setValue(null)`).
        \Closure::bind(static function (Base $e): void {
            unset($e->id);
        }, null, Base::class)($venta);
        $registrador->postRemove(new PostRemoveEventArgs($venta, $em));

        $this->assertSame("eliminado", $insertado["operacion"]);
        $this->assertSame(86869, $insertado["registro_id"]);
        $this->assertSame(["clase" => "BoletoVenta", "id" => 86869, "etiqueta" => "86869"], json_decode($insertado["detalle"], true));
    }
}
