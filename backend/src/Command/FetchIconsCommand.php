<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Icon;
use App\Entity\IconCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:icons:fetch',
    description: 'Obtiene y persiste los iconos y categorías desde Google Fonts Metadata API'
)]
class FetchIconsCommand extends Command {
    private const METADATA_URL = 'https://fonts.google.com/metadata/icons';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addOption('truncate', null, InputOption::VALUE_NONE, 'Limpia iconos y categorías antes de importar')
            ->addOption('append', null, InputOption::VALUE_NONE, 'Salta iconos que ya existen (por codigo)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $truncate = $input->getOption('truncate');
        $append = $input->getOption('append');

        if ($truncate) {
            $io->warning('Truncando tablas icon_category_icon, icon, icon_category...');
            $conn = $this->entityManager->getConnection();
            $conn->executeStatement('DELETE FROM icon_category_icon');
            $conn->executeStatement('DELETE FROM icon');
            $conn->executeStatement('DELETE FROM icon_category');
            $this->entityManager->flush();
            $io->info('Datos limpiados.');
        }

        $io->title('Fetching icons from Google Fonts Metadata API');

        $io->comment('Downloading metadata...');
        $response = $this->httpClient->request('GET', self::METADATA_URL);
        $content = $response->getContent();

        $prefix = ")]}'\n";
        if (str_starts_with($content, $prefix)) {
            $content = substr($content, strlen($prefix));
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $iconsData = $data['icons'] ?? [];
        $totalIcons = count($iconsData);
        $io->info(sprintf('Found %d icons in metadata', $totalIcons));

        $categoryNames = [];
        foreach ($iconsData as $iconData) {
            foreach ($iconData['categories'] as $cat) {
                $categoryNames[$cat] = true;
            }
        }
        $io->info(sprintf('Found %d categories', count($categoryNames)));

        $io->section('Processing categories');
        $categoryMap = [];
        $categoryRepo = $this->entityManager->getRepository(IconCategory::class);
        foreach (array_keys($categoryNames) as $catName) {
            $category = $categoryRepo->findOneBy(['name' => $catName]);
            if (!$category) {
                $category = new IconCategory();
                $category->setName($catName);
                $this->entityManager->persist($category);
                $io->text("  Created category: {$catName}");
            }
            $categoryMap[$catName] = $category;
        }
        $this->entityManager->flush();

        $io->section('Processing icons');
        $iconRepo = $this->entityManager->getRepository(Icon::class);
        $progress = $io->createProgressBar($totalIcons);
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
        $progress->setMessage('Starting...');
        $progress->start();

        $batchSize = 100;
        $count = 0;

        foreach ($iconsData as $iconData) {
            $codigo = $iconData['name'];

            $progress->setMessage($codigo);

            $icon = $append ? $iconRepo->findOneBy(['codigo' => $codigo]) : null;

            if (!$icon) {
                $icon = new Icon();
                $icon->setCodigo($codigo);
                $icon->setName($codigo);
                $icon->setCodepoint($iconData['codepoint'] ?? null);
                $icon->setPopularity($iconData['popularity'] ?? null);
                $icon->setTags($iconData['tags'] ?? null);
                $this->entityManager->persist($icon);
            }

            foreach ($iconData['categories'] as $catName) {
                if (isset($categoryMap[$catName])) {
                    $categoryMap[$catName]->addIcon($icon);
                }
            }

            ++$count;
            if ($count % $batchSize === 0) {
                $this->entityManager->flush();
                $progress->advance($batchSize);
            }
        }

        $this->entityManager->flush();
        $progress->finish();
        $io->newLine(2);

        $io->success(sprintf('Imported %d icons across %d categories', $count, count($categoryMap)));

        return Command::SUCCESS;
    }
}
