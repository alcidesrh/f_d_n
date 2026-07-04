# Enrutamiento

## Configuración

El router se configura en `src/router/index.ts` usando `createRouter` de Vue Router 4 con `createWebHistory` (history mode). El archivo usa `defineRouter` wrapper de Quasar.

```typescript
createRouter({
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    else return { top: 0 }
  },
  routes,
  history: createWebHistory(process.env.VUE_ROUTER_BASE),
})
```

**Scroll behavior**: Restaura posición guardada al navegar atrás; en otro caso, scroll al inicio.

## Estructura de rutas

El archivo principal `src/router/routes.ts` organiza rutas en categorías:

### 1. Rutas públicas
Sin autenticación:

| Ruta | Nombre | Componente |
|------|--------|-----------|
| `/login` | `login` | `LoginPage.vue` |
| `/forbidden` | `forbidden` | `Forbidden.vue` |

### 2. Rutas MainLayout (protegidas)
Todas anidadas bajo `MainLayout2.vue` (path `/`):

| Ruta | Nombre | Componente | Meta |
|------|--------|-----------|------|
| `/` | `home` | `DashboardPage.vue` | Dashboard principal |
| `/lista/:entity` | `list` | `DynamicCollection.vue` | Listado dinámico |
| `/form/:entity/:id?` | `form` | `DynamicForm.vue` | Formulario dinámico |
| `/venta/boleto` | `venta_boleto` | `BoletoVentaPage.vue` | Venta de boletos |
| `/test` | `test` | `Test.vue` | Testing |

### 3. Módulos de rutas estáticas

Importadas como arrays desde archivos separados:

**admin.ts** — Rutas de administración:
```
/admin/dashboard                   → DashboardPage.vue
/admin/entities                    → EntityList.vue
/admin/entities/:action/:entity    → EntityConfig.vue
```

**user.ts** — Gestión de usuarios:
```
/usuarios                          → UserCollection.vue
/usuarios/crear                    → UserForm.vue
/usuarios/edit/:id                 → UserForm.vue
/usuarios/cuenta/:id               → UserAccount.vue
```

**role.ts** — Roles:
```
/crear/role                        → RoleForm.vue
/roles/edit/:id                    → PageUpdate.vue
/roles/show/:id                    → PageShow.vue
```

**action.ts** — Acciones (con permisos):
```
/actions/                          → PageList.vue    (permiso: admin.accion)
/actions/create                    → PageCreate.vue  (permiso: admin.accion.crear)
/actions/edit/:id                  → PageUpdate.vue  (permiso: admin.accion.editar)
/actions/show/:id                  → PageShow.vue    (permiso: admin.accion.ver)
```

**permiso.ts** — Permisos:
```
/permisos/                         → PageList.vue
/permisos/create                   → PageCreate.vue
/permisos/edit/:id                 → PageUpdate.vue
/permisos/show/:id                 → PageShow.vue
```

**boleto.ts** — Boletos:
```
/boletos                           → UserCollection.vue
/boletos/emitir                    → BoletoVentaPage.vue
```

### 4. Catch-all
```
/:action/:entity                   → ErrorNotFound.vue
/:catchAll(.*)*                    → ErrorNotFound.vue
```

## Meta propiedades

| Propiedad | Tipo | Descripción |
|-----------|------|-------------|
| `breadcrumb` | `string \| function \| array` | Genera breadcrumbs de navegación |
| `icon` | `string` | Icono Material Symbols |
| `action` | `'listar' \| 'form'` | Activa prefetch en middleware |
| `public` | `boolean` | Ruta sin autenticación |
| `requiresPermission` | `string` | Permiso específico requerido |
| `transition` | `string` | Nombre de transición Vue |

## Middleware de navegación

El archivo `src/boot/middleware.ts` registra un `router.beforeEach` que:

1. Permite pasar rutas públicas (`login`)
2. Si hay sesión y va a login, redirige a `/`
3. Si no hay sesión, redirige a `/login` guardando `redirectTo`
4. Si la ruta requiere permiso específico, verifica con `session.can()`
5. **Prefetch dinámico**: si `params.entity` existe:
   - `action === 'listar'`: `getStore(entity).then(s => s.collection())`
   - `action === 'form'`: `getStore(entity).then(s => { s.getFormSchema(); if (id) s.getItem(id) })`

## Breadcrumbs

El composable `useBreadcrumbs` (`src/composables/breadcrumb.ts`) recorre `route.matched` y acumula breadcrumbs desde `meta.breadcrumb`. Soporta:

- **String**: breadcrumb estático
- **Array**: múltiples breadcrumbs con label, to, icon
- **Función**: recibe la ruta actual, retorna string o array
