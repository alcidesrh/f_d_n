<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cierre de salidas por la hora (ADR-074): el programador busca cada pocos
 * minutos las salidas abiertas que ya pasaron. Con este índice parcial no
 * recorre el historial (casi todo finalizado o cancelado). Como los demás
 * `nat_*`, solo existe aquí (ADR-056).
 */
final class Version20261128140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Índice de las salidas abiertas por fecha, para cerrarlas por la hora (ADR-074)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE INDEX nat_salida_abierta_fecha ON salida (fecha) WHERE estado IN ('programada', 'abordando', 'iniciada')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX nat_salida_abierta_fecha');
    }
}
