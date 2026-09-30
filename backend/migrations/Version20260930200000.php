<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Acciones y permisos de la venta de asientos (ADR-021) en bases ya migradas:
 * mismos códigos que MigradorIAM::BASE_ACTIONS / BASE_PERMISOS. Idempotente.
 */
final class Version20260930200000 extends AbstractMigration
{
    private const ACCIONES = ['venta.vender', 'venta.cortesia', 'venta.sin_factura', 'agencia.acreditar'];

    private const PERMISOS = [
        'Venta Taquilla' => ['venta.vender'],
        'Supervision Venta' => ['venta.vender', 'venta.cortesia', 'venta.sin_factura', 'agencia.acreditar'],
    ];

    public function getDescription(): string
    {
        return 'Acciones venta.* / agencia.acreditar y permisos Venta Taquilla / Supervision Venta';
    }

    public function up(Schema $schema): void
    {
        foreach (self::ACCIONES as $codigo) {
            $this->addSql(
                'INSERT INTO action (codigo, nombre) SELECT :codigo, :codigo WHERE NOT EXISTS (SELECT 1 FROM action WHERE codigo = :codigo)',
                ['codigo' => $codigo],
            );
        }
        foreach (self::PERMISOS as $nombre => $codigos) {
            $this->addSql(
                'INSERT INTO permiso (nombre) SELECT :nombre WHERE NOT EXISTS (SELECT 1 FROM permiso WHERE nombre = :nombre)',
                ['nombre' => $nombre],
            );
            foreach ($codigos as $codigo) {
                $this->addSql(
                    'INSERT INTO permiso_action (permiso_id, action_id)
                     SELECT p.id, a.id FROM permiso p, action a WHERE p.nombre = :nombre AND a.codigo = :codigo
                     ON CONFLICT DO NOTHING',
                    ['nombre' => $nombre, 'codigo' => $codigo],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM permiso WHERE nombre IN ('Venta Taquilla', 'Supervision Venta')");
        $this->addSql("DELETE FROM action WHERE codigo IN ('venta.vender', 'venta.cortesia', 'venta.sin_factura', 'agencia.acreditar')");
    }
}
