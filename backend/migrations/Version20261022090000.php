<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Accesos con alcance (ADR-039), reemplaza el Flat Permission Set (ADR-003):
 *
 * - `role` pasa a ser plano: `capacidades` (códigos del catálogo en el
 *   código), `limites`, `sistema` y `descripcion`; sin jerarquía, sin
 *   permisos ni acciones intermedias.
 * - `asignacion`: usuario × rol × empresas × estaciones (NULL = todas) ×
 *   vigencia. Se crea una por cada `user_role` (salvo ROLE_USER, que pasa a
 *   ser el rol base implícito). Alcance inferido: ROLE_SUPER_ADMIN y
 *   ROLE_ADMIN y los usuarios de agencia, todo; los demás, las empresas para
 *   las que vendió y, en los puestos de estación, su estación. Lo que no se
 *   pueda inferir queda vacío (no concede nada) y lo afina con el legado
 *   `app:acceso:alcance-legado`.
 * - `usuario.activo` (el login lo exige); se quita `usuario.empresa_id`.
 * - `agencia.empresa_id` → `agencia_empresa` (las que vende; ninguna = todas).
 * - Se borran `action`, `permiso` y sus uniones, la jerarquía de roles, los
 *   permisos y acciones por usuario y las listas sin uso de `vue_route`, y
 *   la configuración de pantalla y los enlaces de administración que los usaban.
 *
 * Las capacidades de cada rol del legado son una copia congelada de
 * `App\Acceso\Legado\EquivalenciasLegado` a esta fecha.
 */
final class Version20261022090000 extends AbstractMigration
{
    /** Todo el catálogo a esta fecha menos `migracion.ejecutar`. */
    private const ADMIN = ['venta.vender', 'venta.cortesia', 'venta.sin_factura', 'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar', 'boleto.chequear', 'salida.ver', 'salida.crear', 'salida.editar', 'salida.cancelar', 'salida.eliminar', 'salida.abordar', 'salida.iniciar', 'salida.finalizar', 'ruta.editar', 'encomienda.ver', 'encomienda.registrar', 'encomienda.interna', 'encomienda.embarcar', 'encomienda.entregar', 'encomienda.anular', 'encomienda.especiales', 'agencia.depositar', 'agencia.acreditar', 'reporte.ventas', 'reporte.encomiendas', 'pagina.administrar', 'config.editar', 'iam.ver', 'iam.asignar', 'iam.roles', 'bitacora.ver', 'agencia.editar', 'agencia.eliminar', 'tarifa.ver', 'tarifa.editar', 'tarifa.eliminar', 'boleto_venta.ver', 'bus.ver', 'bus.editar', 'bus.eliminar', 'bus_clase.editar', 'bus_clase.eliminar', 'bus_marca.editar', 'bus_marca.eliminar', 'cliente.ver', 'cliente.editar', 'cliente.eliminar', 'empresa.editar', 'empresa.eliminar', 'enclave.editar', 'enclave.eliminar', 'establecimiento.ver', 'establecimiento.editar', 'establecimiento.eliminar', 'estacion.editar', 'estacion.eliminar', 'factura.ver', 'localidad.editar', 'localidad.eliminar', 'menu.editar', 'menu.eliminar', 'moneda.editar', 'moneda.eliminar', 'nacion.editar', 'nacion.eliminar', 'piloto.ver', 'piloto.editar', 'piloto.eliminar', 'status.editar', 'status.eliminar', 'tarifa_encomienda_distancia.editar', 'tarifa_encomienda_distancia.eliminar', 'tarifa_encomienda_peso.editar', 'tarifa_encomienda_peso.eliminar', 'tipo_documento.editar', 'tipo_documento.eliminar', 'tipo_pago.editar', 'tipo_pago.eliminar', 'usuario.ver', 'usuario.editar', 'usuario.eliminar'];

    /** @var array<string, array{0: bool, 1: list<string>}> rol => [por estación, capacidades] */
    private const ROLES = [
        'ROLE_ADMIN_EMPRESA' => [false, ['salida.ver', 'salida.crear', 'salida.editar', 'salida.cancelar', 'salida.eliminar', 'salida.abordar', 'salida.iniciar', 'salida.finalizar', 'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar', 'venta.vender', 'venta.cortesia', 'venta.sin_factura', 'reporte.ventas', 'reporte.encomiendas', 'encomienda.ver', 'bus.ver', 'bus.editar', 'piloto.ver', 'piloto.editar', 'tarifa.ver', 'tarifa.editar', 'establecimiento.ver', 'empresa.editar', 'iam.ver', 'iam.asignar', 'usuario.ver', 'usuario.editar', 'bitacora.ver']],
        'ROLE_ADMIN_ESTACION' => [true, ['venta.vender', 'venta.cortesia', 'venta.sin_factura', 'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar', 'boleto.chequear', 'salida.ver', 'salida.abordar', 'salida.iniciar', 'salida.finalizar', 'encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar', 'encomienda.anular', 'reporte.ventas', 'reporte.encomiendas', 'iam.ver', 'usuario.ver']],
        'ROLE_ADMIN_BUSES' => [false, ['bus.ver', 'bus.editar', 'bus.eliminar', 'bus_clase.editar', 'bus_marca.editar', 'salida.ver']],
        'ROLE_ADMIN_PILOTOS' => [false, ['piloto.ver', 'piloto.editar', 'piloto.eliminar', 'bus.ver']],
        'ROLE_ADMIN_REIMPRIMIR_FACTURA' => [true, ['boleto.ver']],
        'ROLE_ADMIN_TARIFAS' => [false, ['tarifa.ver', 'tarifa.editar', 'tarifa.eliminar', 'tarifa_encomienda_distancia.editar', 'tarifa_encomienda_peso.editar', 'bus.ver', 'salida.ver']],
        'ROLE_ADMIN_TIEMPOS' => [false, ['ruta.editar', 'salida.ver']],
        'ROLE_ADMINISTRATIVOS' => [false, ['agencia.depositar', 'agencia.acreditar', 'reporte.ventas', 'reporte.encomiendas', 'boleto.ver', 'salida.ver', 'encomienda.ver']],
        'ROLE_AGENCIA' => [false, ['venta.vender', 'boleto.ver', 'agencia.depositar']],
        'ROLE_AGENCIA_SALIDA' => [false, ['salida.abordar', 'salida.iniciar', 'boleto.chequear', 'salida.ver']],
        'ROLE_ALQUILER' => [false, ['bus.ver', 'salida.ver']],
        'ROLE_ASIGNADOR_BUSES_PILOTOS' => [false, ['salida.ver', 'salida.crear', 'salida.editar', 'bus.ver', 'bus.editar', 'piloto.ver']],
        'ROLE_AUTORIZADOR' => [true, ['venta.cortesia', 'boleto.ver']],
        'ROLE_CHECK_BOLETO_MOVIL' => [true, ['boleto.chequear', 'salida.ver']],
        'ROLE_CHECK_BOLETO_WEB' => [true, ['boleto.chequear', 'salida.ver']],
        'ROLE_EMISOR_CORTESIA' => [true, ['venta.vender', 'venta.cortesia']],
        'ROLE_ENCOMIENDA_REGISTRAR_ESPECIAL' => [true, ['encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar', 'encomienda.especiales']],
        'ROLE_ENTREGA_MULTIPLE_ENCOMIENDA' => [true, ['encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar']],
        'ROLE_INSPECTOR_BOLETO' => [false, ['boleto.ver', 'salida.ver']],
        'ROLE_INSPECTOR_ENCOMIENDA' => [false, ['encomienda.ver']],
        'ROLE_MANIFIESTO_ESPECIAL' => [false, ['salida.ver']],
        'ROLE_NO_VENDER_VOUCHER' => [false, []],
        'ROLE_PROPIETARIO' => [false, ['reporte.ventas', 'reporte.encomiendas', 'agencia.depositar', 'agencia.acreditar', 'salida.ver', 'boleto.ver', 'encomienda.ver', 'bitacora.ver']],
        'ROLE_REASIGNACION' => [true, ['boleto.ver', 'boleto.reasignar']],
        'ROLE_RECEPTOR_ENCOMIENDAS' => [true, ['encomienda.ver', 'encomienda.registrar', 'encomienda.embarcar', 'encomienda.entregar']],
        'ROLE_REVISOR' => [false, ['boleto.ver', 'encomienda.ver', 'salida.ver']],
        'ROLE_SUPERVISOR_AGENCIA' => [false, ['agencia.depositar', 'agencia.acreditar']],
        'ROLE_SUPERVISOR_BOLETO' => [true, ['venta.vender', 'venta.cortesia', 'venta.sin_factura', 'boleto.ver', 'boleto.anular', 'boleto.reasignar', 'boleto.cancelar', 'boleto.chequear', 'salida.ver', 'reporte.ventas', 'cliente.ver', 'cliente.editar']],
        'ROLE_SUPERVISOR_BOLETO_VOUCHER' => [true, ['boleto.ver', 'reporte.ventas']],
        'ROLE_SUPERVISOR_ENCOMIENDA' => [true, ['encomienda.ver', 'encomienda.registrar', 'encomienda.interna', 'encomienda.embarcar', 'encomienda.entregar', 'encomienda.anular', 'encomienda.especiales', 'reporte.encomiendas']],
        'ROLE_SUPERVISOR_PORTAL' => [false, ['pagina.administrar', 'boleto.ver']],
        'ROLE_VENDEDOR_BOLETOS' => [true, ['venta.vender', 'boleto.ver']],
    ];

    /** Descripción para el editor de roles (las del legado, donde la había). */
    private const DESCRIPCIONES = [
        'ROLE_SUPER_ADMIN' => 'Administración total del sistema',
        'ROLE_USER' => 'Todos los usuarios: menús y cajas de administración comunes',
        'ROLE_ADMIN' => 'Administración parcial del sistema',
        'ROLE_ADMIN_BUSES' => 'Administra los buses',
        'ROLE_ADMIN_EMPRESA' => 'Administra el sistema por empresa',
        'ROLE_ADMIN_ESTACION' => 'Administra el sistema en las estaciones',
        'ROLE_ADMIN_PILOTOS' => 'Administra los pilotos',
        'ROLE_ADMIN_REIMPRIMIR_FACTURA' => 'Reimprime facturas',
        'ROLE_ADMIN_TARIFAS' => 'Administra las tarifas',
        'ROLE_ADMIN_TIEMPOS' => 'Administra los tiempos de las rutas',
        'ROLE_ADMINISTRATIVOS' => 'Personal de oficina de administración',
        'ROLE_AGENCIA' => 'Usuarios de agencias',
        'ROLE_AGENCIA_SALIDA' => 'Usuarios de agencias que inician salidas',
        'ROLE_ALQUILER' => 'Alquiler de buses',
        'ROLE_ASIGNADOR_BUSES_PILOTOS' => 'Planifica buses y pilotos de las salidas',
        'ROLE_AUTORIZADOR' => 'Autoriza cortesías',
        'ROLE_CHECK_BOLETO_MOVIL' => 'Chequea boletos (móvil)',
        'ROLE_CHECK_BOLETO_WEB' => 'Chequea boletos',
        'ROLE_EMISOR_CORTESIA' => 'Emite cortesías',
        'ROLE_ENCOMIENDA_REGISTRAR_ESPECIAL' => 'Registra encomiendas especiales',
        'ROLE_ENTREGA_MULTIPLE_ENCOMIENDA' => 'Entrega múltiple de encomiendas',
        'ROLE_INSPECTOR_BOLETO' => 'Inspecciona boletos',
        'ROLE_INSPECTOR_ENCOMIENDA' => 'Inspecciona encomiendas',
        'ROLE_MANIFIESTO_ESPECIAL' => 'Manifiestos de salidas',
        'ROLE_NO_VENDER_VOUCHER' => 'Sin efecto en el sistema nuevo (era una negación)',
        'ROLE_PROPIETARIO' => 'Dueños de las empresas',
        'ROLE_REASIGNACION' => 'Reasigna boletos',
        'ROLE_RECEPTOR_ENCOMIENDAS' => 'Recibe encomiendas en las estaciones',
        'ROLE_REVISOR' => 'Revisión (solo lectura)',
        'ROLE_SUPERVISOR_AGENCIA' => 'Supervisa agencias y sus depósitos',
        'ROLE_SUPERVISOR_BOLETO' => 'Responsable de la venta de boletos en las estaciones',
        'ROLE_SUPERVISOR_BOLETO_VOUCHER' => 'Supervisa vouchers de boletos',
        'ROLE_SUPERVISOR_ENCOMIENDA' => 'Responsable de las encomiendas en las estaciones',
        'ROLE_SUPERVISOR_PORTAL' => 'Supervisa la página web',
        'ROLE_VENDEDOR_BOLETOS' => 'Vende boletos en las estaciones',
    ];

    public function getDescription(): string
    {
        return 'Accesos con alcance (ADR-039): roles planos con capacidades, asignaciones con empresas y estaciones, usuario activo, agencias con varias empresas; fuera el Flat Permission Set';
    }

    public function up(Schema $schema): void
    {
        // 1. Estructuras nuevas.
        $this->addSql('ALTER TABLE role ADD descripcion VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE role ADD capacidades JSON DEFAULT '[]' NOT NULL");
        $this->addSql("ALTER TABLE role ADD limites JSON DEFAULT '{}' NOT NULL");
        $this->addSql('ALTER TABLE role ADD sistema BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE TABLE asignacion (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, empresas JSON DEFAULT NULL, estaciones JSON DEFAULT NULL, desde DATE DEFAULT NULL, hasta DATE DEFAULT NULL, motivo VARCHAR(255) DEFAULT NULL, creada TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modificada TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, usuario_id INT NOT NULL, rol_id INT NOT NULL, otorgada_por_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2562927114BB3589 ON asignacion (otorgada_por_id)');
        $this->addSql('CREATE INDEX idx_asignacion_usuario ON asignacion (usuario_id)');
        $this->addSql('CREATE INDEX idx_asignacion_rol ON asignacion (rol_id)');
        $this->addSql('ALTER TABLE asignacion ADD CONSTRAINT FK_25629271DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE asignacion ADD CONSTRAINT FK_256292714BAB96C FOREIGN KEY (rol_id) REFERENCES role (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE asignacion ADD CONSTRAINT FK_2562927114BB3589 FOREIGN KEY (otorgada_por_id) REFERENCES usuario (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE usuario ADD activo BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('CREATE TABLE agencia_empresa (agencia_id INT NOT NULL, empresa_id INT NOT NULL, PRIMARY KEY (agencia_id, empresa_id))');
        $this->addSql('CREATE INDEX IDX_11D8407EA6F796BE ON agencia_empresa (agencia_id)');
        $this->addSql('CREATE INDEX IDX_11D8407E521E1991 ON agencia_empresa (empresa_id)');
        $this->addSql('ALTER TABLE agencia_empresa ADD CONSTRAINT FK_11D8407EA6F796BE FOREIGN KEY (agencia_id) REFERENCES agencia (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE agencia_empresa ADD CONSTRAINT FK_11D8407E521E1991 FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE NOT DEFERRABLE');

        // 2. Roles: capacidades, sistema y descripción.
        $this->addSql("UPDATE role SET sistema = true WHERE nombre = 'ROLE_SUPER_ADMIN'");
        $this->addSql("UPDATE role SET capacidades = CAST(:c AS json) WHERE nombre = 'ROLE_ADMIN'", ['c' => json_encode(self::ADMIN, JSON_THROW_ON_ERROR)]);
        foreach (self::ROLES as $rol => [, $capacidades]) {
            $this->addSql('UPDATE role SET capacidades = CAST(:c AS json) WHERE nombre = :rol', ['c' => json_encode($capacidades, JSON_THROW_ON_ERROR), 'rol' => $rol]);
        }
        foreach (self::DESCRIPCIONES as $rol => $descripcion) {
            $this->addSql('UPDATE role SET descripcion = :d WHERE nombre = :rol', ['d' => $descripcion, 'rol' => $rol]);
        }

        // 3. Una asignación por cada rol de cada usuario (el base es implícito).
        $porEstacion = array_keys(array_filter(self::ROLES, static fn(array $r) => $r[0]));
        $this->addSql(
            "INSERT INTO asignacion (usuario_id, rol_id, empresas, estaciones, motivo, creada, modificada)
             SELECT ur.user_id, ur.role_id,
                    CASE WHEN r.nombre IN ('ROLE_SUPER_ADMIN', 'ROLE_ADMIN') OR u.agencia_id IS NOT NULL THEN NULL
                         ELSE COALESCE((SELECT json_agg(DISTINCT s.empresa_id) FROM boleto_venta v
                                        JOIN boleto_asiento b ON b.boleto_venta_id = v.id
                                        JOIN salida s ON s.id = b.salida_id
                                        WHERE v.usuario_id = u.id AND s.empresa_id IS NOT NULL), '[]'::json) END,
                    CASE WHEN r.nombre IN ('ROLE_SUPER_ADMIN', 'ROLE_ADMIN') OR u.agencia_id IS NOT NULL THEN NULL
                         WHEN r.nombre IN (:porEstacion) THEN CASE WHEN u.estacion_id IS NULL THEN '[]'::json ELSE json_build_array(u.estacion_id) END
                         ELSE NULL END,
                    'Migrado del modelo anterior (ADR-039)', NOW(), NOW()
             FROM user_role ur
             JOIN role r ON r.id = ur.role_id
             JOIN usuario u ON u.id = ur.user_id
             WHERE r.nombre <> 'ROLE_USER'",
            ['porEstacion' => $porEstacion],
            ['porEstacion' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        // 4. Agencias: la empresa única pasa al conjunto.
        $this->addSql('INSERT INTO agencia_empresa (agencia_id, empresa_id) SELECT id, empresa_id FROM agencia WHERE empresa_id IS NOT NULL');

        // 5. Roles creados por el modelo anterior que nadie usa.
        $this->addSql(
            "DELETE FROM role r WHERE r.nombre IN ('ROLE_OPERADOR', 'ROLE_CONSULTA')
             AND NOT EXISTS (SELECT 1 FROM user_role x WHERE x.role_id = r.id)
             AND NOT EXISTS (SELECT 1 FROM menu_role x WHERE x.role_id = r.id)
             AND NOT EXISTS (SELECT 1 FROM seccion_administracion_role x WHERE x.role_id = r.id)
             AND NOT EXISTS (SELECT 1 FROM enlace_administracion_role x WHERE x.role_id = r.id)",
        );

        // 6. Pantallas que apuntaban a lo que se va.
        $this->addSql("DELETE FROM enlace_administracion WHERE ruta = 'entity-list' AND parametros->>'entity' IN ('action', 'permiso', 'api-token')");
        $this->addSql("DELETE FROM form_field_config WHERE entity_config_id IN (SELECT id FROM entity_configuration WHERE entity_class IN ('Action', 'Permiso'))");
        $this->addSql("DELETE FROM collection_field_config WHERE entity_config_id IN (SELECT id FROM entity_configuration WHERE entity_class IN ('Action', 'Permiso'))");
        $this->addSql("DELETE FROM entity_configuration WHERE entity_class IN ('Action', 'Permiso')");
        foreach (['Usuario' => ['userRoles', 'permisos', 'directActions', 'deniedActions', 'empresa'], 'Agencia' => ['empresa'], 'VueRoute' => ['roles', 'usuariosPermitidos', 'usuariosDenegados'], 'Role' => ['parents', 'children', 'permisos', 'actions']] as $entidad => $campos) {
            foreach (['form_field_config', 'collection_field_config'] as $tabla) {
                $this->addSql(
                    "DELETE FROM $tabla WHERE field IN (:campos) AND entity_config_id IN (SELECT id FROM entity_configuration WHERE entity_class = :entidad)",
                    ['campos' => $campos, 'entidad' => $entidad],
                    ['campos' => \Doctrine\DBAL\ArrayParameterType::STRING],
                );
            }
        }

        // 7. Lo que reemplaza el modelo nuevo.
        foreach (['user_role', 'usuario_permiso', 'user_direct_action', 'user_denied_action', 'role_role', 'role_permiso', 'role_action', 'permiso_action', 'permiso_permiso', 'vue_route_role', 'vue_route_usuario_denegados', 'vue_route_usuario_permitidos', 'permiso', 'action'] as $tabla) {
            $this->addSql("DROP TABLE $tabla");
        }
        $this->addSql('ALTER TABLE agencia DROP CONSTRAINT fk_eb6c2b99521e1991');
        $this->addSql('DROP INDEX idx_eb6c2b99521e1991');
        $this->addSql('ALTER TABLE agencia DROP empresa_id');
        $this->addSql('ALTER TABLE usuario DROP CONSTRAINT fk_2265b05d521e1991');
        $this->addSql('DROP INDEX idx_2265b05d521e1991');
        $this->addSql('ALTER TABLE usuario DROP empresa_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57698A6A3A909126 ON role (nombre)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Las acciones, permisos y la jerarquía de roles se borraron; restaure un respaldo para volver al modelo anterior (ADR-003).');
    }
}
