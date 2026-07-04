# Matriz de Permisos

## Roles del sistema

| Role | Descripción | Nivel de acceso |
|------|-------------|-----------------|
| `ROLE_SUPER_ADMIN` | Super Administrador | Acceso total a todas las acciones |
| `ROLE_ADMIN` | Administrador | Acceso completo a gestión operativa |
| `ROLE_OPERADOR` | Operador | Acceso a operaciones de boletos y servicios |
| `ROLE_CONSULTA` | Consulta | Acceso mínimo, solo lectura básica |

## Matriz de acciones por rol

La siguiente matriz muestra qué acciones están disponibles para cada rol, basada en los fixtures de desarrollo.

### Usuarios

| Código | SUPER_ADMIN | ADMIN | OPERADOR | CONSULTA |
|--------|:-----------:|:-----:|:--------:|:--------:|
| `usuario.listar` | ✓ | ✓ | — | — |
| `usuario.crear` | ✓ | ✓ | — | — |
| `usuario.editar` | ✓ | ✓ | — | — |
| `usuario.eliminar` | ✓ | ✓ | — | — |
| `usuario.ver` | ✓ | ✓ | ✓ (directa) | — |

### Roles

| Código | SUPER_ADMIN | ADMIN | OPERADOR | CONSULTA |
|--------|:-----------:|:-----:|:--------:|:--------:|
| `rol.listar` | ✓ | ✓ | — | — |
| `rol.crear` | ✓ | ✓ | — | — |
| `rol.editar` | ✓ | ✓ | — | — |
| `rol.eliminar` | ✓ | ✓ | — | — |
| `rol.ver` | ✓ | ✓ | — | — |

### Permisos

| Código | SUPER_ADMIN | ADMIN | OPERADOR | CONSULTA |
|--------|:-----------:|:-----:|:--------:|:--------:|
| `permiso.listar` | ✓ | — | — | — |
| `permiso.crear` | ✓ | — | — | — |
| `permiso.editar` | ✓ | — | — | — |
| `permiso.eliminar` | ✓ | — | — | — |
| `permiso.ver` | ✓ | — | — | — |

### Acciones

| Código | SUPER_ADMIN | ADMIN | OPERADOR | CONSULTA |
|--------|:-----------:|:-----:|:--------:|:--------:|
| `action.listar` | ✓ | — | — | — |
| `action.crear` | ✓ | — | — | — |
| `action.editar` | ✓ | — | — | — |
| `action.eliminar` | ✓ | — | — | — |
| `action.ver` | ✓ | — | — | — |

### Boletos

| Código | SUPER_ADMIN | ADMIN | OPERADOR | CONSULTA |
|--------|:-----------:|:-----:|:--------:|:--------:|
| `boleto.listar` | ✓ | ✓ | ✓ | ✓ (directo permiso) |
| `boleto.crear` | ✓ | ✓ | ✓ | ✓ |
| `boleto.editar` | ✓ | ✓ | ✓ | ✓ |
| `boleto.eliminar` | ✓ | ✓ | ✓ | ✓ |
| `boleto.anular` | ✓ | ✓ | ✓ | ✗ (denegada) |
| `boleto.ver` | ✓ | ✓ | ✓ | ✓ |

### Rutas

| Código | SUPER_ADMIN | ADMIN | OPERADOR | CONSULTA |
|--------|:-----------:|:-----:|:--------:|:--------:|
| `ruta.listar` | ✓ | ✓ | — | — |
| `ruta.crear` | ✓ | ✓ | — | — |
| `ruta.editar` | ✓ | ✓ | — | — |
| `ruta.eliminar` | ✓ | ✓ | — | — |
| `ruta.ver` | ✓ | ✓ | — | — |

## Fixtures de usuarios de prueba

Definidos en `src/DataFixtures/UserFixtures.php`:

| Usuario | Password | Roles | Permisos directos | Acciones directas | Acciones denegadas | Acciones efectivas |
|---------|----------|-------|-------------------|-------------------|-------------------|-------------------|
| `superadmin` | `super123` | ROLE_SUPER_ADMIN | — | — | — | Todas (31) |
| `admin` | `admin123` | ROLE_ADMIN | Gestion Acciones | — | — | 26 |
| `operador` | `operador123` | ROLE_OPERADOR | — | usuario.ver | — | 7 |
| `consulta` | `consulta123` | ROLE_CONSULTA | Gestion Boletos | — | boleto.anular | 5 |

## Fixtures de roles

Definidos en `src/DataFixtures/RoleFixtures.php`:

| Role | Permisos asignados |
|------|-------------------|
| ROLE_SUPER_ADMIN | Gestion Usuarios, Gestion Roles, Gestion Permisos, Gestion Acciones, Gestion Boletos, Gestion Rutas |
| ROLE_ADMIN | Gestion Usuarios, Gestion Roles, Gestion Boletos, Gestion Rutas |
| ROLE_OPERADOR | Gestion Boletos |
| ROLE_CONSULTA | (ninguno) |

## Permisos definidos en fixtures

Definidos en `src/DataFixtures/PermisoFixtures.php`:

| Permiso | Acciones incluidas |
|---------|-------------------|
| Gestion Usuarios | usuario.listar, usuario.crear, usuario.editar, usuario.eliminar, usuario.ver |
| Gestion Roles | rol.listar, rol.crear, rol.editar, rol.eliminar, rol.ver |
| Gestion Permisos | permiso.listar, permiso.crear, permiso.editar, permiso.eliminar, permiso.ver |
| Gestion Acciones | action.listar, action.crear, action.editar, action.eliminar, action.ver |
| Gestion Boletos | boleto.listar, boleto.crear, boleto.editar, boleto.eliminar, boleto.anular, boleto.ver |
| Gestion Rutas | ruta.listar, ruta.crear, ruta.editar, ruta.eliminar, ruta.ver |

## Notas importantes

1. **ROLE_SUPER_ADMIN** tiene bypass total via `in_array('ROLE_ADMIN', $user->getRoles(), true)` en ambos voters. No necesita permisos ni acciones asignadas.
2. Las **acciones directas** y **denegadas** son overrides por usuario que permiten ajustes finos sin modificar roles.
3. Todas las asignaciones se resuelven en el PermissionManager, que **no consulta la base de datos** en cada llamada (usa cache en memoria).
4. El frontend recibe la lista plana de acciones via `/api/me/permissions` y puede ocultar/mostrar elementos de UI según permisos.
