<?php

declare(strict_types=1);

namespace App\Migration\Job;

use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * OutputInterface que escribe a output.log del job (sin decoración ANSI:
 * el formatter base de Symfony elimina las etiquetas <info>/<error>…).
 */
final class SalidaJobOutput extends Output
{
    public function __construct(
        private readonly AlmacenDeJobs $almacen,
        private readonly string $jobId,
        int $verbosity = OutputInterface::VERBOSITY_NORMAL,
    ) {
        parent::__construct($verbosity, false);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->almacen->appendLog(
            $this->jobId,
            $message . ($newline ? PHP_EOL : ""),
        );
    }
}
