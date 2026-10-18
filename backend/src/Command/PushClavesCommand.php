<?php

declare(strict_types=1);

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Genera un par de claves VAPID para las notificaciones del chat. Se generan
 * una vez por instalación: cambiarlas invalida todas las suscripciones (cada
 * usuario tendría que volver a activarlas).
 */
#[AsCommand(name: "app:push:claves", description: "Genera las claves VAPID de las notificaciones push (para .env.local)")]
final class PushClavesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $claves = VAPID::createVapidKeys();
        $output->writeln(sprintf("PUSH_VAPID_PUBLICA=%s", $claves["publicKey"]));
        $output->writeln(sprintf("PUSH_VAPID_PRIVADA=%s", $claves["privateKey"]));

        return Command::SUCCESS;
    }
}
