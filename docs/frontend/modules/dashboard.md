# Módulo Dashboard

## Páginas

### DashboardPage (`src/pages/admin/DashboardPage.vue`)

Página principal de la aplicación (ruta `/`). Muestra un resumen ejecutivo con:
- KPIs clave del negocio
- Gráficos y estadísticas
- Accesos directos a funciones principales

Es la página por defecto después del inicio de sesión.

## Componentes

### StatsCard (`src/components/admin/StatsCard.vue`)

Componente para mostrar indicadores. Props:
- `title`: título del indicador
- `value`: valor numérico
- `icon`: icono Material Symbols
- `color`: color de fondo/accento
- `trend`: tendencia (opcional)

## Navegación desde Dashboard

El dashboard ofrece acceso directo a:
- **Administración**: usuarios, roles, permisos, acciones, entidades
- **Operaciones**: venta de boletos, flota, transporte
- **Configuración**: entidades, iconos, tokens

## Menú de navegación

Definido en `MainLayout.vue`, el menú de administración se filtra por permisos:

```typescript
const adminMenu = {
  label: 'Administración',
  icon: 'security',
  children: [
    { label: 'Dashboard', icon: 'dashboard', name: 'home', perm: 'admin.dashboard' },
    { label: 'Usuarios', icon: 'people', name: 'users', perm: 'usuario.ver' },
    { label: 'Roles', icon: 'badge', name: 'RoleList', perm: 'admin.rol' },
    { label: 'Permisos', icon: 'policy', name: 'PermisoList', perm: 'admin.permiso' },
    { label: 'Acciones', icon: 'lock', name: 'ActionList', perm: 'admin.accion' },
    { label: 'Entidades', icon: 'format_list_bulleted', name: 'entity_list', perm: 'admin.entidad' },
  ],
}
```

Solo se muestran las opciones para las cuales el usuario tiene permiso (`can(item.perm)`).

## Stores

No tiene stores específicas. Usa `loadingStore` para indicadores de carga global y `sessionStore` para datos del usuario.
