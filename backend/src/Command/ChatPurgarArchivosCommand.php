<?php

declare(strict_types=1);

namespace App\Command;

use App\Chat\Archivos;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Borra los archivos del chat que se subieron y nunca se enviaron (más de 24 h). Para cron. */
#[AsCommand(name: "app:chat:purgar-archivos", description: "Borra archivos del chat subidos y no enviados hace más de 24 h")]
final class ChatPurgarArchivosCommand extends Command
{
    public function __construct(private readonly Archivos $archivos)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $n = $this->archivos->purgarSueltos(new \DateTimeImmutable("-24 hours"));
        $output->writeln(sprintf("Archivos sueltos borrados: %d", $n));

        return Command::SUCCESS;
    }
}
