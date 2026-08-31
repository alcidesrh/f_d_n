<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\VueRoute;
use App\Services\VueRouteSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:vue-routes:sync',
    description: 'Sincroniza las rutas VueRoute con las declaradas en el frontend. Recibe el árbol de rutas JSON por argumento (archivo) o por stdin.'
)]
class VueRoutesSyncCommand extends Command
{
    public function __construct(
        private readonly VueRouteSynchronizer $synchronizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'file',
                InputArgument::OPTIONAL,
                'Ruta a un archivo JSON con el árbol de rutas (ej: rutas.json). Si se omite, lee de stdin.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        try {
            $routes = $this->readRoutes($file);
        } catch (\Throwable $e) {
            $io->error("No se pudieron leer las rutas: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $this->synchronizer->sync($routes);

        $total = $this->entityManager
            ->getRepository(VueRoute::class)
            ->count([]);

        $io->success(sprintf('Rutas sincronizadas. Total de registros VueRoute: %d', $total));

        return Command::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readRoutes(?string $file): array
    {
        if ($file !== null) {
            if (!is_file($file) || !is_readable($file)) {
                throw new \InvalidArgumentException(sprintf('El archivo "%s" no existe o no es legible.', $file));
            }

            $raw = (string) file_get_contents($file);
        } else {
            $raw = stream_get_contents(STDIN);
            if ($raw === false || trim($raw) === '') {
                throw new \InvalidArgumentException('No se recibió JSON por stdin. Proporcione un archivo o un payload por stdin.');
            }
        }

        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('El JSON aportado no es un array de rutas válido.');
        }

        if (isset($payload['routes']) && is_array($payload['routes'])) {
            return $payload['routes'];
        }

        return $payload;
    }
}
