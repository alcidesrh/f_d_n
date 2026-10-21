<?php

declare(strict_types=1);

namespace App\Metadata;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Anota de qué entidades se guardó configuración del cliente en cada flush,
 * venga de donde venga (editor, GraphQL, REST, sincronización, migración).
 * Publicarla queda para el final de la petición (`PublicarAlTerminar`).
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class VigilanteConfiguracion
{
    public function __construct(private readonly ConfiguracionesTocadas $tocadas) {}

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ([$uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates(), $uow->getScheduledEntityDeletions()] as $entidades) {
            foreach ($entidades as $entidad) {
                if ($entidad instanceof ConfiguracionDelCliente) {
                    $this->tocadas->anotar($entidad->entidadConfigurada());
                }
            }
        }
        foreach ([$uow->getScheduledCollectionUpdates(), $uow->getScheduledCollectionDeletions()] as $colecciones) {
            foreach ($colecciones as $coleccion) {
                $duenio = $coleccion->getOwner();
                if ($duenio instanceof ConfiguracionDelCliente) {
                    $this->tocadas->anotar($duenio->entidadConfigurada());
                }
            }
        }
    }
}
