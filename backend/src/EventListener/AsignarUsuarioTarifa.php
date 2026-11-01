<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\BoletoTarifa;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * La tarifa lleva el usuario de la sesión, al crearla y al editarla: el
 * formulario no lo pide (`usuario` no es de entrada en la API). Sin sesión
 * (migración, consola) se deja el que tenga.
 *
 * El alta se completa en `prePersist`, antes de que Doctrine calcule sus
 * cambios: recalcularlos en `onFlush` dejaría el INSERT sin los demás campos.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::onFlush)]
final class AsignarUsuarioTarifa
{
    public function __construct(private readonly Security $security) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entidad = $args->getObject();
        $usuario = $this->usuarioDeSesion();
        if ($entidad instanceof BoletoTarifa && $usuario !== null) {
            $entidad->setUsuario($usuario);
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $usuario = $this->usuarioDeSesion();
        if ($usuario === null) {
            return;
        }
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $tarifa) {
            if ($tarifa instanceof BoletoTarifa && $tarifa->getUsuario() !== $usuario) {
                $tarifa->setUsuario($usuario);
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(BoletoTarifa::class), $tarifa);
            }
        }
    }

    private function usuarioDeSesion(): ?Usuario
    {
        $usuario = $this->security->getUser();

        return $usuario instanceof Usuario ? $usuario : null;
    }
}
