# Módulo Seguridad (IAM)

## Entidades

| Entidad | Icono | Permiso base | Descripción |
|---------|-------|-------------|-------------|
| `Usuario` | `person` | `usuario.*` | Usuarios del sistema |
| `User` | `person` | — | Alias de Usuario (GraphQL) |
| `Role` | `badge` | `admin.rol.*` | Roles |
| `Permiso` | `policy` | `admin.permiso.*` | Permisos individuales |
| `Action` | `lock` | `admin.accion.*` | Acciones del sistema |

## Páginas

### Usuarios

Rutas estáticas (definidas en `src/router/user.ts`):
```
/usuarios             → UserCollection.vue
/usuarios/crear       → UserForm.vue
/usuarios/edit/:id    → UserForm.vue
/usuarios/cuenta/:id  → UserAccount.vue
```

Componentes: `src/components/user/` — UserCreate, UserForm, UserList, UserShow, UserUpdate.

### Roles

Rutas estáticas (definidas en `src/router/role.ts`):
```
/crear/role           → RoleForm.vue
/roles/edit/:id       → PageUpdate.vue
/roles/show/:id       → PageShow.vue
```

Componentes: `src/components/role/` — RoleCreate, RoleForm, RoleList, RoleShow, RoleUpdate.

### Permisos

Rutas estáticas (definidas en `src/router/permiso.ts`):
```
/permisos/            → PageList.vue
/permisos/create      → PageCreate.vue
/permisos/edit/:id    → PageUpdate.vue
/permisos/show/:id    → PageShow.vue
```

Componentes: `src/components/permiso/` — PermisoCreate, PermisoForm, PermisoList, PermisoShow, PermisoUpdate.

### Acciones

Rutas con verificación de permisos (definidas en `src/router/action.ts`):
```
/actions/             → PageList.vue   (permiso: admin.accion)
/actions/create       → PageCreate.vue (permiso: admin.accion.crear)
/actions/edit/:id     → PageUpdate.vue (permiso: admin.accion.editar)
/actions/show/:id     → PageShow.vue   (permiso: admin.accion.ver)
```

## Stores específicas

- `src/stores/user/`: Store de usuario con métodos de autenticación
- `src/stores/role/`: Store de rol
- `src/stores/permiso/`: Store de permiso
- `src/stores/action/`: Store de acción

## Session Store

`src/stores/autoimport/session.ts` maneja:

- `token`: JWT de autenticación (persistido)
- `user`: datos del usuario (persistido)
- `permissions`: array plano de permisos (persistido)
- `isAuthenticated`: computed basado en user + token
- `isAdmin`: computed que verifica `admin.*` o `ROLE_ADMIN`

**Métodos:**
- `login(username, password)`: autenticación vía fetch a `/login`
- `fetchSession()`: obtiene permisos de `/me/permissions`
- `can(code)`: verifica permiso individual
- `canAny(codes)`: verifica si tiene al menos uno
- `canAll(codes)`: verifica si tiene todos
- `clear()`: cierra sesión

## Permisos

Sistema de **Flat Permission Set**: los permisos son strings planos, no jerárquicos. Ejemplos:
```
usuario.ver, usuario.crear, usuario.editar, usuario.eliminar
admin.rol, admin.permiso, admin.accion, admin.entidad
admin.dashboard
```

La verificación se hace con `session.can(permissionCode)` en componentes y rutas.
