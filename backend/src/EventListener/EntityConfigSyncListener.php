<?php

namespace App\EventListener;

use App\Services\EntityConfigSynchronizer;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

final class EntityConfigSyncListener implements EventSubscriber {
  public function __construct(
    private readonly EntityConfigSynchronizer $synchronizer
  ) {
  }

  public function getSubscribedEvents(): array {
    return [
      Events::loadClassMetadata,
    ];
  }

  public function loadClassMetadata(LoadClassMetadataEventArgs $args): void {
    $metadata = $args->getClassMetadata();

    // Ignorar MappedSuperclass, Embedded y entidades abstractas
    if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass || $metadata->isAbstract) {
      return;
    }

    $this->synchronizer->syncEntity($metadata->getName());
  }
}
