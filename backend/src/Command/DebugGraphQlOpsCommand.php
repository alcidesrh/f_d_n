<?php

namespace App\Command;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use App\Entity\CollectionFieldConfig;
use App\Entity\EntityConfiguration;
use App\Entity\FormFieldConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:debug-graphq-ops')]
class DebugGraphQlOpsCommand extends Command
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $factory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ([EntityConfiguration::class, CollectionFieldConfig::class, FormFieldConfig::class] as $class) {
            $shortName = (new \ReflectionClass($class))->getShortName();
            $rmc = $this->factory->create($class);

            $output->writeln("\n=== $shortName ===");

            $apiResource = $rmc[0] ?? null;
            if (!$apiResource) {
                $output->writeln("  No ApiResource found");
                continue;
            }

            $output->writeln("  GraphQL Operations:");
            foreach ($apiResource->getGraphQlOperations() ?? [] as $name => $op) {
                $nested = method_exists($op, 'getNested') ? var_export($op->getNested(), true) : 'N/A';
                $output->writeln("    $name: " . get_class($op) . ' nested=' . $nested . ' provider=' . var_export($op->getProvider(), true));
            }

            $output->writeln("  getOperation(collection, forceGraphQl=true):");
            try {
                $op = $rmc->getOperation(null, true, false, true);
                if (method_exists($op, 'getNested')) {
                    $output->writeln("    " . get_class($op) . ' nested=' . var_export($op->getNested(), true) . ' provider=' . var_export($op->getProvider(), true));
                } else {
                    $output->writeln("    " . get_class($op) . ' (no getNested method)');
                }
            } catch (\Throwable $e) {
                $output->writeln("    NOT FOUND: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
