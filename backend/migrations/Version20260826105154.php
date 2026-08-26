<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260826105154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu_role (menu_id INT NOT NULL, role_id INT NOT NULL, PRIMARY KEY (menu_id, role_id))');
        $this->addSql('CREATE INDEX IDX_9F267A24CCD7E912 ON menu_role (menu_id)');
        $this->addSql('CREATE INDEX IDX_9F267A24D60322AC ON menu_role (role_id)');
        $this->addSql('CREATE TABLE menu_permiso (menu_id INT NOT NULL, permiso_id INT NOT NULL, PRIMARY KEY (menu_id, permiso_id))');
        $this->addSql('CREATE INDEX IDX_75A1B620CCD7E912 ON menu_permiso (menu_id)');
        $this->addSql('CREATE INDEX IDX_75A1B6206CEFAD37 ON menu_permiso (permiso_id)');
        $this->addSql('ALTER TABLE menu_role ADD CONSTRAINT FK_9F267A24CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_role ADD CONSTRAINT FK_9F267A24D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_permiso ADD CONSTRAINT FK_75A1B620CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_permiso ADD CONSTRAINT FK_75A1B6206CEFAD37 FOREIGN KEY (permiso_id) REFERENCES permiso (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_role DROP CONSTRAINT FK_9F267A24CCD7E912');
        $this->addSql('ALTER TABLE menu_role DROP CONSTRAINT FK_9F267A24D60322AC');
        $this->addSql('ALTER TABLE menu_permiso DROP CONSTRAINT FK_75A1B620CCD7E912');
        $this->addSql('ALTER TABLE menu_permiso DROP CONSTRAINT FK_75A1B6206CEFAD37');
        $this->addSql('DROP TABLE menu_role');
        $this->addSql('DROP TABLE menu_permiso');
    }
}
