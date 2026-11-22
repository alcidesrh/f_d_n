<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reconocer al cliente que vuelve (ADR-068): la búsqueda de la pantalla de
 * venta compara sin acentos ni signos y busca también por teléfono y por el
 * documento limpio. Las expresiones son, letra por letra, las de
 * `App\Venta\IdentidadCliente` (`SQL_CLAVE`, `SQL_TELEFONO`, `SQL_DOCUMENTO`).
 * Como los demás `nat_*`, solo existen aquí (ADR-056).
 */
final class Version20261122100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Búsqueda de clientes sin acentos, por teléfono y por documento limpio (ADR-068)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX nat_cliente_busqueda');
        $this->addSql(
            "CREATE INDEX nat_cliente_busqueda ON cliente
              USING gin (LOWER(REGEXP_REPLACE(TRANSLATE(nombre || ' ' || COALESCE(apellido, '') || ' ' || COALESCE(nit, ''), 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun'), '[^[:alnum:]]+', ' ', 'g')) gin_trgm_ops)",
        );
        $this->addSql("CREATE INDEX nat_cliente_telefono ON cliente (RIGHT(REGEXP_REPLACE(telefono, '[^0-9]', '', 'g'), 8)) WHERE (telefono IS NOT NULL)");
        $this->addSql("CREATE INDEX nat_cliente_documento ON cliente (REGEXP_REPLACE(UPPER(numero_documento), '[^0-9A-Z]', '', 'g')) WHERE (numero_documento IS NOT NULL)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX nat_cliente_documento');
        $this->addSql('DROP INDEX nat_cliente_telefono');
        $this->addSql('DROP INDEX nat_cliente_busqueda');
        $this->addSql(
            "CREATE INDEX nat_cliente_busqueda ON cliente
              USING gin (LOWER(nombre || ' ' || COALESCE(apellido, '') || ' ' || COALESCE(nit, '')) gin_trgm_ops)",
        );
    }
}
