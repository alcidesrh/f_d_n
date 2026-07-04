# Migración IAM

Archivo: `src/Migration/MigradorIAM.php`

## Visión general

Migra el modelo de seguridad del legacy (roles planos, usuarios con roles en JSON) al nuevo modelo Flat Permission Set con jerarquía de roles, permisos y acciones.

## Modelo legacy vs. nuevo

| Aspecto | Legacy | Nuevo |
|---------|--------|-------|
| Roles | `custom_rol` (solo nombre) | `role` (jerárquico, con padres) |
| Usuario-Rol | Columna JSON `roles` en `custom_user` | Tabla `user_role` |
| Permisos | No existían como entidad | `permiso` con acciones agrupadas |
| Acciones | No existían | `action` con código, recurso, operación, grupo |

## Pasos de migración

### 1. Crear acciones base

25 acciones predefinidas que cubren todas las operaciones del sistema:

```php
private const BASE_ACTIONS = [
    ['boleto.crear', 'Boleto', 'create', 'Boletos'],
    ['boleto.ver', 'Boleto', 'read', 'Boletos'],
    ['boleto.editar', 'Boleto', 'update', 'Boletos'],
    ['boleto.anular', 'Boleto', 'delete', 'Boletos'],
    ['boleto.reasignar', 'Boleto', 'reassign', 'Boletos'],
    ['servicio.crear', 'Servicio', 'create', 'Servicios'],
    ['servicio.ver', 'Servicio', 'read', 'Servicios'],
    ['servicio.editar', 'Servicio', 'update', 'Servicios'],
    ['servicio.cancelar', 'Servicio', 'cancel', 'Servicios'],
    ['ruta.ver', 'Ruta', 'read', 'Rutas'],
    ['ruta.editar', 'Ruta', 'update', 'Rutas'],
    ['empresa.ver', 'Empresa', 'read', 'Empresas'],
    ['empresa.editar', 'Empresa', 'update', 'Empresas'],
    ['usuario.ver', 'Usuario', 'read', 'Usuarios'],
    ['usuario.editar', 'Usuario', 'update', 'Usuarios'],
    ['cliente.ver', 'Cliente', 'read', 'Clientes'],
    ['cliente.crear', 'Cliente', 'create', 'Clientes'],
    ['tarifa.ver', 'Tarifa', 'read', 'Tarifas'],
    ['tarifa.editar', 'Tarifa', 'update', 'Tarifas'],
    ['asiento.ver', 'Asiento', 'read', 'Asientos'],
    ['reporte.ventas', 'Reporte', 'read', 'Reportes'],
    ['config.ver', 'Config', 'read', 'Configuración'],
    ['config.editar', 'Config', 'update', 'Configuración'],
    ['iam.ver', 'IAM', 'read', 'IAM'],
    ['iam.editar', 'IAM', 'update', 'IAM'],
];
```

### 2. Crear permisos base

5 permisos que agrupan las acciones:

| Permiso | Acciones incluidas |
|---------|-------------------|
| Gestion Acciones | operaciones CRUD de boleto, servicio, ruta, empresa, cliente, tarifa |
| Gestion Usuarios | usuario.ver, usuario.editar, iam.ver, iam.editar |
| Gestion Reportes | reporte.ventas, config.ver |
| Gestion Config | config.ver, config.editar, empresa.editar, ruta.editar, tarifa.editar |
| Gestion Asientos | asiento.ver |

### 3. Crear roles base

| Rol | Permisos | Acciones directas |
|-----|----------|-------------------|
| ROLE_ADMIN | Todos los permisos base | Todas las 25 acciones |
| ROLE_OPERADOR | Gestion Acciones | — |
| ROLE_CONSULTA | — | — |

### 4. Migrar roles legacy

Lee `custom_rol` y crea entidades `role` para cada nombre de rol no existente.

### 5. Asignar usuarios a roles

Lee la columna `roles` (JSON) de `custom_user`:

```php
$roleNames = unserialize($row['roles']);
```

Para cada nombre de rol, busca el role en la nueva BD y crea la relación en `user_role`.

## Transaccionalidad

Toda la migración IAM se ejecuta en una sola transacción:

```php
$this->newConn->beginTransaction();
try {
    // ... 5 pasos ...
    $this->newConn->commit();
} catch (\Throwable $e) {
    $this->newConn->rollBack();
}
```

## Contadores

```php
$contadores = [
    'actions' => 25,     // Siempre 25 acciones base
    'permisos' => 5,     // 5 permisos base
    'roles' => 3,        // 3 roles base
    'rol_legacy' => ?,   // Roles legacy migrados
    'user_role' => ?,    // Asignaciones usuario-rol
];
```
