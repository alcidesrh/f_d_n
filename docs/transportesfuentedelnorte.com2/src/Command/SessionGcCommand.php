<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

#[AsCommand(
    name: 'session:gc',
    description: 'Eliminar sesiones expiradas',
)]
class SessionGcCommand extends Command
{

    public function __construct()
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $nativeSessionStorage = new NativeSessionStorage();
        $nativeSessionStorage->start();
        // $result = $nativeSessionStorage->getSaveHandler()->gc(1);

        return Command::SUCCESS;
    }
}
