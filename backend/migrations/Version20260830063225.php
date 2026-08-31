<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830063225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renombra la secuencia identity salida.id a salida_id_seq (heredada como servicio_id_seq).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER SEQUENCE servicio_id_seq RENAME TO salida_id_seq');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER SEQUENCE salida_id_seq RENAME TO servicio_id_seq');
    }
}