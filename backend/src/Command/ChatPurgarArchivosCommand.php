<?php

declare(strict_types=1);

namespace App\Command;

use App\Chat\Archivos;
use App\Chat\Tarea\PurgarArchivosChat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Borra los archivos del chat que se subieron y nunca se enviaron (más de 24 h). El programador lo hace cada noche (ADR-044); esto es a mano. */
#[AsCommand(name: "app:chat:purgar-archivos", description: "Borra archivos del chat subidos y no enviados hace más de 24 h")]
final class ChatPurgarArchivosCommand extends Command
{
    public function __construct(private readonly Archivos $archivos)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $n = $this->archivos->purgarSueltos(new \DateTimeImmutable(PurgarArchivosChat::ANTIGUEDAD));
        $output->writeln(sprintf("Archivos sueltos borrados: %d", $n));

        return Command::SUCCESS;
    }
}
