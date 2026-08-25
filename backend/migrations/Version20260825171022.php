<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260825171022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE permiso_permiso DROP CONSTRAINT fk_ec89d1ff6cefad37');
        $this->addSql('ALTER TABLE permiso_permiso DROP CONSTRAINT permiso_permiso_pkey');
        $this->addSql('ALTER TABLE permiso_permiso ADD child_id INT NOT NULL');
        $this->addSql('ALTER TABLE permiso_permiso RENAME COLUMN permiso_id TO parent_id');
        $this->addSql('ALTER TABLE permiso_permiso ADD CONSTRAINT FK_EC89D1FF727ACA70 FOREIGN KEY (parent_id) REFERENCES permiso (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE permiso_permiso ADD CONSTRAINT FK_EC89D1FFDD62C21B FOREIGN KEY (child_id) REFERENCES permiso (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_EC89D1FF727ACA70 ON permiso_permiso (parent_id)');
        $this->addSql('CREATE INDEX IDX_EC89D1FFDD62C21B ON permiso_permiso (child_id)');
        $this->addSql('ALTER TABLE permiso_permiso ADD PRIMARY KEY (parent_id, child_id)');
        $this->addSql('ALTER TABLE role_role DROP CONSTRAINT fk_e9d6f8fed60322ac');
        $this->addSql('ALTER TABLE role_role DROP CONSTRAINT role_role_pkey');
        $this->addSql('ALTER TABLE role_role ADD child_id INT NOT NULL');
        $this->addSql('ALTER TABLE role_role RENAME COLUMN role_id TO parent_id');
        $this->addSql('ALTER TABLE role_role ADD CONSTRAINT FK_E9D6F8FE727ACA70 FOREIGN KEY (parent_id) REFERENCES role (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE role_role ADD CONSTRAINT FK_E9D6F8FEDD62C21B FOREIGN KEY (child_id) REFERENCES role (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E9D6F8FE727ACA70 ON role_role (parent_id)');
        $this->addSql('CREATE INDEX IDX_E9D6F8FEDD62C21B ON role_role (child_id)');
        $this->addSql('ALTER TABLE role_role ADD PRIMARY KEY (parent_id, child_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE permiso_permiso DROP CONSTRAINT FK_EC89D1FF727ACA70');
        $this->addSql('ALTER TABLE permiso_permiso DROP CONSTRAINT FK_EC89D1FFDD62C21B');
        $this->addSql('DROP INDEX IDX_EC89D1FF727ACA70');
        $this->addSql('DROP INDEX IDX_EC89D1FFDD62C21B');
        $this->addSql('ALTER TABLE permiso_permiso DROP CONSTRAINT permiso_permiso_pkey');
        $this->addSql('ALTER TABLE permiso_permiso ADD permiso_id INT NOT NULL');
        $this->addSql('ALTER TABLE permiso_permiso DROP parent_id');
        $this->addSql('ALTER TABLE permiso_permiso DROP child_id');
        $this->addSql('ALTER TABLE permiso_permiso ADD CONSTRAINT fk_ec89d1ff6cefad37 FOREIGN KEY (permiso_id) REFERENCES permiso (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE permiso_permiso ADD PRIMARY KEY (permiso_id)');
        $this->addSql('ALTER TABLE role_role DROP CONSTRAINT FK_E9D6F8FE727ACA70');
        $this->addSql('ALTER TABLE role_role DROP CONSTRAINT FK_E9D6F8FEDD62C21B');
        $this->addSql('DROP INDEX IDX_E9D6F8FE727ACA70');
        $this->addSql('DROP INDEX IDX_E9D6F8FEDD62C21B');
        $this->addSql('ALTER TABLE role_role DROP CONSTRAINT role_role_pkey');
        $this->addSql('ALTER TABLE role_role ADD role_id INT NOT NULL');
        $this->addSql('ALTER TABLE role_role DROP parent_id');
        $this->addSql('ALTER TABLE role_role DROP child_id');
        $this->addSql('ALTER TABLE role_role ADD CONSTRAINT fk_e9d6f8fed60322ac FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE role_role ADD PRIMARY KEY (role_id)');
    }
}
