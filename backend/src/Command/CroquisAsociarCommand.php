<?php

declare(strict_types=1);

namespace App\Command;

use App\Croquis\Moldes;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Asocia cada bus con el molde de su distribución (ADR-027), creando los
 * moldes que falten. Idempotente: se corre tras la migración del esquema y
 * cuando se quiera verificar. Al guardar un croquis y al migrar del legado
 * la asociación se hace sola.
 */
#[AsCommand(name: "app:croquis:asociar", description: "Asocia cada bus con el molde (Croquis) de su distribución")]
final class CroquisAsociarCommand
{
    public function __construct(
        private readonly Moldes $moldes,
        private readonly Connection $db,
    ) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $cambiados = $this->db->transactional(fn() => $this->moldes->asociar());
        $io->success(sprintf(
            "Buses con molde nuevo o distinto: %d. Moldes: %d. Buses sin croquis: %d.",
            $cambiados,
            (int) $this->db->fetchOne("SELECT COUNT(*) FROM croquis"),
            (int) $this->db->fetchOne("SELECT COUNT(*) FROM bus WHERE croquis_id IS NULL"),
        ));

        return 0;
    }
}
