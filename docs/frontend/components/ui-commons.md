# Componentes de UI Comunes

## Breadcrumbs.vue

**Archivo**: `src/components/Breadcrumbs.vue`

Barra de navegación secundaria (migas de pan). Usa el composable `useBreadcrumbs` para obtener la ruta actual.

**Props**: Ninguna.

**Comportamiento**:
- Renderiza breadcrumbs desde `route.matched` y `meta.breadcrumb`
- Soporta breadcrumbs estáticos, arrays y funciones dinámicas
- Cada breadcrumb muestra icono y enlace (excepto el último)

## Clock.vue

**Archivo**: `src/components/Clock.vue`

Reloj digital en tiempo real.

**Props**: Ninguna.

**Comportamiento**:
- Muestra hora actual actualizada cada segundo
- Formato configurable (HH:MM:SS por defecto)

## Icon.vue

**Archivo**: `src/components/Icon.vue`

Componente para renderizar iconos Material Symbols.

**Props**:
- `name`: nombre del icono (ej: `'person'`, `'sym_o_airplane_ticket'`)
- `size`: tamaño (opcional)
- `color`: color (opcional)
- `round`: si es circular (opcional)

**Eventos**:
- `click`: clic en el icono

## IconPicker.vue

**Archivo**: `src/components/IconPicker.vue`

Selector de iconos con búsqueda y categorías. Usa el composable `useIcons`.

**Props**:
- `modelValue`: icono seleccionado

**Eventos**:
- `update:modelValue`: cambio de selección

**Comportamiento**:
- Carga todos los iconos vía GraphQL
- Permite búsqueda por nombre/código/tags
- Filtra por categoría
- Grid visual de iconos con paginación

## Notify.vue

**Archivo**: `src/components/Notify.vue`

Componente de notificaciones conectado al EventBus.

**Comportamiento**:
- Escucha eventos del bus (`positive`, `error`, `info`)
- Muestra notificaciones Quasar con configuración personalizada
- Posición: top, multi-line, 4s timeout, clase `fdn-notify`

## ProfilerFooter.vue

**Archivo**: `src/components/ProfilerFooter.vue`

Footer de depuración que muestra el token del profiler Symfony.

**Comportamiento**:
- Muestra el `X-Debug-Token` capturado por `ProfilerFetch`
- Enlace al profiler de Symfony para depuración
- Solo visible en desarrollo (o configurable)

## ResponsiveLayout.vue

**Archivo**: `src/components/ResponsiveLayout.vue`

Layout responsivo que adapta su estructura según el breakpoint actual.

**Props**:
- `breakpoint`: breakpoint mínimo para mostrar (ej: `'md'`)

## ResponsiveComponent.vue

**Archivo**: `src/components/ResponsiveComponent.vue`

Wrapper que muestra/oculta contenido según breakpoint usando la directiva `v-responsive`.

## SidebarDrawer.vue

**Archivo**: `src/components/SidebarDrawer.vue`

Drawer base para sidebars. Contiene la lógica compartida de animación y transición.

## SidebarLeft.vue

**Archivo**: `src/components/SidebarLeft.vue`

Sidebar izquierdo principal. Integra `MenuLarge` (modo large) y `MenuMini` (modo mini).

## SidebarRight.vue

**Archivo**: `src/components/SidebarRight.vue`

Sidebar derecho (para paneles contextuales).

## Topbar.vue

**Archivo**: `src/components/Topbar.vue`

Barra superior de la aplicación.

**Contenido**:
- Menú hamburguesa (toggle sidebar)
- Breadcrumbs
- Reloj
- Menú de usuario (cuenta, cerrar sesión)
- Iconos de notificaciones

## SubMenuMini.vue

**Archivo**: `src/components/SubMenuMini.vue`

Submenú que aparece al hover sobre items del sidebar en modo mini.

## CommonBreadcrumb.vue

**Archivo**: `src/components/common/CommonBreadcrumb.vue`

Variante estilizada de Breadcrumbs para uso en páginas específicas.

## Preload components

**Archivo**: `src/components/preload/`

| Componente | Propósito |
|-----------|-----------|
| `ButtonPreload.vue` | Skeleton para botón |
| `FormPreload.vue` | Skeleton para formulario (prop: `cols`) |
| `ListPreload.vue` | Skeleton para listado (prop: `cols`) |
