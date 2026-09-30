<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renombra la entidad Recorrido a Salida (tabla `recorrido` -> `salida`,
 * columnas `recorrido_id` -> `salida_id`, permisos `recorrido.*` -> `salida.*`).
 * Usa RENAME para conservar los datos; los nombres de índices y claves foráneas
 * son los que Doctrine genera para la tabla/columna nuevas.
 */
final class Version20260930210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renombra Recorrido a Salida (tabla, columnas FK, índices y permisos)';
    }

    public function up(Schema $schema): void
    {
        $this->renombrar('recorrido', 'salida', 'recorrido_id', 'salida_id', [
            'idx_3a1543a8521e1991' => 'IDX_95F4C748521E1991',
            'idx_3a1543a82546731d' => 'IDX_95F4C7482546731D',
            'idx_3a1543a88e3e79aa' => 'IDX_95F4C7488E3E79AA',
            'idx_recorrido_fecha_empresa_trayecto' => 'idx_salida_fecha_empresa_trayecto',
            'idx_8594a0047a331ff' => 'IDX_8594A00426A36E51',
            'uq_boleto_asiento_asiento_trayecto_recorrido' => 'uq_boleto_asiento_asiento_trayecto_salida',
            'idx_b005e48e7a331ff' => 'IDX_B005E48E26A36E51',
            'idx_reserva_asiento_recorrido_expira' => 'idx_reserva_asiento_salida_expira',
        ], [
            ['salida', 'fk_3a1543a8521e1991', 'FK_95F4C748521E1991'],
            ['salida', 'fk_3a1543a82546731d', 'FK_95F4C7482546731D'],
            ['salida', 'fk_3a1543a88e3e79aa', 'FK_95F4C7488E3E79AA'],
            ['boleto_asiento', 'fk_8594a0047a331ff', 'FK_8594A00426A36E51'],
            ['reserva_asiento', 'fk_b005e48e7a331ff', 'FK_B005E48E26A36E51'],
        ]);

        $this->addSql("UPDATE action SET codigo = REPLACE(codigo, 'recorrido.', 'salida.'), nombre = REPLACE(nombre, 'recorrido.', 'salida.') WHERE codigo LIKE 'recorrido.%'");
    }

    public function down(Schema $schema): void
    {
        $this->renombrar('salida', 'recorrido', 'salida_id', 'recorrido_id', [
            'IDX_95F4C748521E1991' => 'idx_3a1543a8521e1991',
            'IDX_95F4C7482546731D' => 'idx_3a1543a82546731d',
            'IDX_95F4C7488E3E79AA' => 'idx_3a1543a88e3e79aa',
            'idx_salida_fecha_empresa_trayecto' => 'idx_recorrido_fecha_empresa_trayecto',
            'IDX_8594A00426A36E51' => 'idx_8594a0047a331ff',
            'uq_boleto_asiento_asiento_trayecto_salida' => 'uq_boleto_asiento_asiento_trayecto_recorrido',
            'IDX_B005E48E26A36E51' => 'idx_b005e48e7a331ff',
            'idx_reserva_asiento_salida_expira' => 'idx_reserva_asiento_recorrido_expira',
        ], [
            ['salida', 'FK_95F4C748521E1991', 'fk_3a1543a8521e1991'],
            ['salida', 'FK_95F4C7482546731D', 'fk_3a1543a82546731d'],
            ['salida', 'FK_95F4C7488E3E79AA', 'fk_3a1543a88e3e79aa'],
            ['boleto_asiento', 'FK_8594A00426A36E51', 'fk_8594a0047a331ff'],
            ['reserva_asiento', 'FK_B005E48E26A36E51', 'fk_b005e48e7a331ff'],
        ]);

        $this->addSql("UPDATE action SET codigo = REPLACE(codigo, 'salida.', 'recorrido.'), nombre = REPLACE(nombre, 'salida.', 'recorrido.') WHERE codigo LIKE 'salida.%'");
    }

    /**
     * @param array<string, string>            $indices  nombre actual => nombre nuevo
     * @param list<array{string, string, string}> $fks      [tabla (ya renombrada), nombre actual, nombre nuevo]
     */
    private function renombrar(string $desde, string $hacia, string $colDesde, string $colHacia, array $indices, array $fks): void
    {
        $this->addSql("ALTER TABLE $desde RENAME TO $hacia");
        $this->addSql("ALTER SEQUENCE {$desde}_id_seq RENAME TO {$hacia}_id_seq");
        $this->addSql("ALTER INDEX {$desde}_pkey RENAME TO {$hacia}_pkey");
        $this->addSql("ALTER TABLE boleto_asiento RENAME COLUMN $colDesde TO $colHacia");
        $this->addSql("ALTER TABLE reserva_asiento RENAME COLUMN $colDesde TO $colHacia");

        foreach ($fks as [$tabla, $actual, $nuevo]) {
            $this->addSql("ALTER TABLE $tabla RENAME CONSTRAINT $actual TO $nuevo");
        }
        foreach ($indices as $actual => $nuevo) {
            $this->addSql("ALTER INDEX $actual RENAME TO $nuevo");
        }
    }
}
