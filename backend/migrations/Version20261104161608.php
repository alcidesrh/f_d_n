<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Índices:
 * - `api_token.token` único: el Bearer se busca en cada petición y se leía
 *   la tabla entera.
 * - Fuera los índices simples que otro de la misma tabla ya cubre por la
 *   izquierda (`App\Doctrine\Schema\IndicesCubiertos`): se pagaban en cada
 *   escritura sin servir a ninguna consulta.
 * - Fuera `enclave.test`, columna de una prueba sin mapeo ni datos.
 */
final class Version20261104161608 extends AbstractMigration
{
    /** Índice que sobra => su definición (para volver atrás). */
    private const CUBIERTOS = [
        "idx_8ce0c204a6f796be" => "agencia_deposito (agencia_id)",
        "idx_11d8407ea6f796be" => "agencia_empresa (agencia_id)",
        "idx_dabe9c73a6f796be" => "agencia_movimiento (agencia_id)",
        "idx_d76dd16868db5b2e" => "chat_mensaje (canal_id)",
        "idx_731cfb068db5b2e" => "chat_miembro (canal_id)",
        "idx_c29fa33968db5b2e" => "chat_subgrupo (canal_id)",
        "idx_58a68a539aeff118" => "correo_mensaje (cuenta_id)",
        "idx_20f0055a7a5a413a" => "enlace_administracion (seccion_id)",
        "idx_6f718a5bc7a0682f" => "enlace_administracion_role (enlace_administracion_id)",
        "idx_bdb6febf4c54f362" => "envio_mensaje_predeterminado (mensaje_id)",
        "idx_71d545a6db38439e" => "envio_reporte (usuario_id)",
        "idx_99694db4521e1991" => "esquema_salida (empresa_id)",
        "idx_94a4d17e521e1991" => "establecimiento (empresa_id)",
        "idx_2f5eceb62546731d" => "lectura_gps (bus_id)",
        "idx_2ffd89de52a5b3af" => "list_configuration (entity_config_id)",
        "idx_3d81a16c81b7086" => "list_configuration_role (list_configuration_id)",
        "idx_a4c37e1bf8243838" => "mensaje_predeterminado_archivo (mensaje_predeterminado_id)",
        "idx_94ef2c4ef8243838" => "mensaje_predeterminado_grupo (mensaje_predeterminado_id)",
        "idx_b38f7ac4f8243838" => "mensaje_predeterminado_usuario (mensaje_predeterminado_id)",
        "idx_9f267a24ccd7e912" => "menu_role (menu_id)",
        "idx_adb5c4f2abbc4845" => "parada (ruta_id)",
        "idx_ec1b7758c49c376a" => "parada_tiempo (parada_id)",
        "idx_reserva_asiento_token" => "reserva_asiento (token)",
        "idx_b005e48e26a36e51" => "reserva_asiento (salida_id)",
        "idx_18a5d16826a36e51" => "salida_parada (salida_id)",
        "idx_18a5d1687255ae3e" => "salida_parada (enclave_id)",
        "idx_3d222cbf0685708" => "seccion_administracion_role (seccion_administracion_id)",
        "idx_1e1fbc6ec680a87" => "solicitud_autorizacion (solicitante_id)",
        "idx_e9b9ef83db38439e" => "suscripcion_reporte (usuario_id)",
        "idx_e28b854293529ecd" => "tarifa_encomienda_distancia (origen_id)",
        "idx_e79d6af29557e6f6" => "taxonomy_node (taxonomy_id)",
        "idx_d1926d3393529ecd" => "trayecto (origen_id)",
    ];

    public function getDescription(): string
    {
        return "Índices: api_token.token único, fuera los cubiertos por otro índice y la columna enclave.test";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE UNIQUE INDEX UNIQ_7BA2F5EB5F37A13B ON api_token (token)");
        foreach (array_keys(self::CUBIERTOS) as $indice) {
            $this->addSql("DROP INDEX IF EXISTS {$indice}");
        }
        $this->addSql("ALTER TABLE enclave DROP COLUMN IF EXISTS test");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP INDEX UNIQ_7BA2F5EB5F37A13B");
        foreach (self::CUBIERTOS as $indice => $definicion) {
            $this->addSql("CREATE INDEX {$indice} ON {$definicion}");
        }
        $this->addSql("ALTER TABLE enclave ADD test BOOLEAN DEFAULT NULL");
    }
}
