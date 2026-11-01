<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Forcon y Cybersource en la administración (ADR-047):
 *
 * - `config.proveedores` para `ROLE_ADMIN` (el rol de sistema ya las tiene todas).
 * - Enlaces «Certificador FEL (Forcon)» y «Pasarela de pago (Cybersource)» al
 *   final de la caja «Caja y facturación». Si la caja no existe, no se agregan
 *   (se pueden crear desde «Personalizar»). Idempotente.
 */
final class Version20261031150000 extends AbstractMigration
{
    private const ENLACES = [
        ['configuracion-forcon', 'Certificador FEL (Forcon)', 'verified-outline', '["forcon","fel","factura electrónica","certificador"]'],
        ['configuracion-cybersource', 'Pasarela de pago (Cybersource)', 'credit-card-outline', '["cybersource","pago","tarjeta","pasarela"]'],
    ];

    public function getDescription(): string
    {
        return 'Capacidad config.proveedores para ROLE_ADMIN y enlaces de Forcon y Cybersource en la administración';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE role SET capacidades = (capacidades::jsonb || '[\"config.proveedores\"]'::jsonb)::json WHERE nombre = 'ROLE_ADMIN' AND NOT jsonb_exists(capacidades::jsonb, 'config.proveedores')");

        foreach (self::ENLACES as [$ruta, $etiqueta, $icono, $claves]) {
            $this->addSql(
                "INSERT INTO enlace_administracion (seccion_id, etiqueta, icono, claves, ruta, parametros, posicion, activo)
                 SELECT s.id, :etiqueta, :icono, :claves, :ruta, '{}',
                        (SELECT COALESCE(MAX(e.posicion), -1) + 1 FROM enlace_administracion e WHERE e.seccion_id = s.id), true
                 FROM seccion_administracion s
                 WHERE s.titulo = 'Caja y facturación'
                   AND NOT EXISTS (SELECT 1 FROM enlace_administracion x WHERE x.ruta = :ruta)
                 ORDER BY s.id LIMIT 1",
                ['etiqueta' => $etiqueta, 'icono' => $icono, 'claves' => $claves, 'ruta' => $ruta],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM enlace_administracion WHERE ruta IN ('configuracion-forcon', 'configuracion-cybersource')");
        $this->addSql("UPDATE role SET capacidades = (capacidades::jsonb - 'config.proveedores')::json WHERE nombre = 'ROLE_ADMIN'");
    }
}
