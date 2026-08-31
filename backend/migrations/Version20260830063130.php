<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830063130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renombra la entidad Servicio a Salida: tabla servicio -> salida y columna boleto_asiento.servicio_id -> salida_id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servicio RENAME TO salida');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT servicio_pkey TO salida_pkey');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT fk_cb86f22a2546731d TO fk_95f4c7482546731d');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT fk_cb86f22a521e1991 TO fk_95f4c748521e1991');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT fk_cb86f22a6bf700bd TO fk_95f4c7486bf700bd');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT fk_cb86f22a9aad4a8d TO fk_95f4c7489aad4a8d');
        $this->addSql('ALTER INDEX idx_cb86f22a2546731d RENAME TO idx_95f4c7482546731d');
        $this->addSql('ALTER INDEX idx_cb86f22a521e1991 RENAME TO idx_95f4c748521e1991');
        $this->addSql('ALTER INDEX idx_cb86f22a6bf700bd RENAME TO idx_95f4c7486bf700bd');
        $this->addSql('ALTER INDEX idx_cb86f22a9aad4a8d RENAME TO idx_95f4c7489aad4a8d');
        $this->addSql('ALTER TABLE boleto_asiento RENAME COLUMN servicio_id TO salida_id');
        $this->addSql('ALTER INDEX idx_8594a00471caa3e7 RENAME TO idx_8594a00426a36e51');
        $this->addSql('ALTER TABLE boleto_asiento RENAME CONSTRAINT fk_8594a00471caa3e7 TO fk_8594a00426a36e51');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boleto_asiento RENAME CONSTRAINT fk_8594a00426a36e51 TO fk_8594a00471caa3e7');
        $this->addSql('ALTER INDEX idx_8594a00426a36e51 RENAME TO idx_8594a00471caa3e7');
        $this->addSql('ALTER TABLE boleto_asiento RENAME COLUMN salida_id TO servicio_id');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT salida_pkey TO servicio_pkey');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT fk_95f4c7482546731d TO fk_cb86f22a2546731d');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT fk_95f4c748521e1991 TO fk_cb86f22a521e1991');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT fk_95f4c7486bf700bd TO fk_cb86f22a6bf700bd');
        $this->addSql('ALTER TABLE salida RENAME CONSTRAINT fk_95f4c7489aad4a8d TO fk_cb86f22a9aad4a8d');
        $this->addSql('ALTER INDEX idx_95f4c7482546731d RENAME TO idx_cb86f22a2546731d');
        $this->addSql('ALTER INDEX idx_95f4c748521e1991 RENAME TO idx_cb86f22a521e1991');
        $this->addSql('ALTER INDEX idx_95f4c7486bf700bd RENAME TO idx_cb86f22a6bf700bd');
        $this->addSql('ALTER INDEX idx_95f4c7489aad4a8d RENAME TO idx_cb86f22a9aad4a8d');
        $this->addSql('ALTER TABLE salida RENAME TO servicio');
    }
}