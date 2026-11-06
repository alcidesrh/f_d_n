<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Una salida del legado no se repite; el barrido de salidas anota lo que no pudo traer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_salida_legacy_id ON salida (legacy_id)');
        $this->addSql('ALTER TABLE sync_estado ADD barrido_en TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, ADD salidas_sin_traer JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sync_estado DROP barrido_en, DROP salidas_sin_traer');
        $this->addSql('DROP INDEX uniq_salida_legacy_id');
    }
}
