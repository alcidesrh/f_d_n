<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Command\SyncEntityConfigurationCommand;
use App\Entity\Configuration\CollectionFieldConfig;
use App\Entity\Configuration\EntityConfiguration;
use App\Entity\Configuration\FormFieldConfig;
use App\Repository\EntityConfigurationRepository;
use App\Services\Collection;
use App\Services\EntityConfigSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class EntityConfigurationByEntityClassProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private EntityConfigSynchronizer $configSynchronizer,
        // private readonly EntityConfigurationRepository $repository,
        private readonly RequestStack $requestStack,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?EntityConfiguration
    {
        $entityClass = $uriVariables['entityClass'] ?? null;
        if (!is_string($entityClass) || $entityClass === '') {
            $request = $this->requestStack->getCurrentRequest();
            $entityClass = $request?->query->get('entityClass');
        }

        if (!is_string($entityClass) || $entityClass === '') {
            return null;
        }
        return $this->configSynchronizer->syncEntity($entityClass, false);
    }
}
