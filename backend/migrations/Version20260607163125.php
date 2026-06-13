<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607163125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE permiso_action (permiso_id INT NOT NULL, action_id INT NOT NULL, PRIMARY KEY (permiso_id, action_id))');
        $this->addSql('CREATE INDEX IDX_B5BDB1496CEFAD37 ON permiso_action (permiso_id)');
        $this->addSql('CREATE INDEX IDX_B5BDB1499D32F035 ON permiso_action (action_id)');
        $this->addSql('CREATE TABLE user_direct_action (user_id INT NOT NULL, action_id INT NOT NULL, PRIMARY KEY (user_id, action_id))');
        $this->addSql('CREATE INDEX IDX_65ACBFE1A76ED395 ON user_direct_action (user_id)');
        $this->addSql('CREATE INDEX IDX_65ACBFE19D32F035 ON user_direct_action (action_id)');
        $this->addSql('CREATE TABLE user_denied_action (user_id INT NOT NULL, action_id INT NOT NULL, PRIMARY KEY (user_id, action_id))');
        $this->addSql('CREATE INDEX IDX_D1D70C43A76ED395 ON user_denied_action (user_id)');
        $this->addSql('CREATE INDEX IDX_D1D70C439D32F035 ON user_denied_action (action_id)');
        $this->addSql('ALTER TABLE permiso_action ADD CONSTRAINT FK_B5BDB1496CEFAD37 FOREIGN KEY (permiso_id) REFERENCES permiso (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE permiso_action ADD CONSTRAINT FK_B5BDB1499D32F035 FOREIGN KEY (action_id) REFERENCES action (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_direct_action ADD CONSTRAINT FK_65ACBFE1A76ED395 FOREIGN KEY (user_id) REFERENCES usuario (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_direct_action ADD CONSTRAINT FK_65ACBFE19D32F035 FOREIGN KEY (action_id) REFERENCES action (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_denied_action ADD CONSTRAINT FK_D1D70C43A76ED395 FOREIGN KEY (user_id) REFERENCES usuario (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_denied_action ADD CONSTRAINT FK_D1D70C439D32F035 FOREIGN KEY (action_id) REFERENCES action (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE action ADD codigo VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE action ADD recurso VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE action ADD operacion VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE action ADD grupo VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE permiso_action DROP CONSTRAINT FK_B5BDB1496CEFAD37');
        $this->addSql('ALTER TABLE permiso_action DROP CONSTRAINT FK_B5BDB1499D32F035');
        $this->addSql('ALTER TABLE user_direct_action DROP CONSTRAINT FK_65ACBFE1A76ED395');
        $this->addSql('ALTER TABLE user_direct_action DROP CONSTRAINT FK_65ACBFE19D32F035');
        $this->addSql('ALTER TABLE user_denied_action DROP CONSTRAINT FK_D1D70C43A76ED395');
        $this->addSql('ALTER TABLE user_denied_action DROP CONSTRAINT FK_D1D70C439D32F035');
        $this->addSql('DROP TABLE permiso_action');
        $this->addSql('DROP TABLE user_direct_action');
        $this->addSql('DROP TABLE user_denied_action');
        $this->addSql('ALTER TABLE action DROP codigo');
        $this->addSql('ALTER TABLE action DROP recurso');
        $this->addSql('ALTER TABLE action DROP operacion');
        $this->addSql('ALTER TABLE action DROP grupo');
    }
}
