<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\BoletoTarifa;
use App\Entity\Usuario;
use App\EventListener\AsignarUsuarioTarifa;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * La tarifa toma el usuario de la sesión al crearla (en `prePersist`, antes de
 * calcular los cambios) y al editarla (en `onFlush`). Sin sesión, no se toca.
 */
final class AsignarUsuarioTarifaTest extends TestCase
{
    public function testElAltaTomaElUsuarioDeLaSesionAntesDeCalcularLosCambios(): void
    {
        $usuario = new Usuario();
        $alta = new BoletoTarifa();

        (new AsignarUsuarioTarifa($this->sesion($usuario)))->prePersist(
            new PrePersistEventArgs($alta, $this->createStub(EntityManagerInterface::class)),
        );

        $this->assertSame($usuario, $alta->getUsuario());
    }

    public function testLaEdicionRecalculaConElUsuarioDeLaSesion(): void
    {
        $usuario = new Usuario();
        $edicion = new BoletoTarifa();
        $metadata = $this->createStub(ClassMetadata::class);
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityUpdates')->willReturn([$edicion]);
        $uow->expects($this->once())->method('recomputeSingleEntityChangeSet')->with($metadata, $edicion);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($metadata);

        (new AsignarUsuarioTarifa($this->sesion($usuario)))->onFlush(new OnFlushEventArgs($em));

        $this->assertSame($usuario, $edicion->getUsuario());
    }

    public function testSinSesionNoTocaLasTarifas(): void
    {
        $alta = new BoletoTarifa();
        $listener = new AsignarUsuarioTarifa($this->sesion(null));

        $listener->prePersist(new PrePersistEventArgs($alta, $this->createStub(EntityManagerInterface::class)));

        $this->assertNull($alta->getUsuario());
    }

    private function sesion(?Usuario $usuario): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($usuario);

        return $security;
    }
}
