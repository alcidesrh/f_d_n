# Componentes Dinámicos de Navegación

## MenuLarge.vue

**Archivo**: `src/components/dynamic/MenuLarge.vue`

Menú de navegación principal en modo grande (sidebar expandido). Muestra la estructura jerárquica de navegación con iconos, labels y submenús.

## MenuMini.vue

**Archivo**: `src/components/dynamic/MenuMini.vue`

Menú de navegación en modo mini (sidebar contraído). Muestra solo iconos, con tooltip al hover.

## SectionTree.vue

**Archivo**: `src/components/sidebar/SectionTree.vue`

Componente de árbol de secciones para el sidebar izquierdo. Renderiza la estructura jerárquica del menú con capacidad de expandir/contraer secciones.

**Props**:
- `menu`: array de items del menú (label, icon, children, name, etc.)
- `depth`: nivel de profundidad actual

**Slots**: Ninguno.

**Comportamiento**:
- Renderiza recursivamente la estructura del menú
- Soporta items con `children` (submenús expandibles)
- Soporta items con `name` (navegación) y `type: 'action'` (comandos)
- Verifica permisos: items con `perm` se filtran según `can(perm)`

## SectionMini.vue

**Archivo**: `src/components/sidebar/SectionMini.vue`

Versión reducida de SectionTree para modo mini del sidebar. Muestra solo iconos y primer nivel.

## SubMenuMini.vue

**Archivo**: `src/components/SubMenuMini.vue`

Submenú desplegable para el modo mini. Se muestra al hacer hover sobre un icono en el sidebar contraído.

## Menu Stores

Las stores de menú se crean dinámicamente con `useMenuStateStore(storeId, menu)`. Cada instancia persistió el estado `toggle` y `menu`:

```typescript
export function useMenuStateStore(storeId: string, menu: Array<any>) {
  return storeFactory(storeId, menu)()
}
```

## Sidebar Stores

Las stores de sidebar se crean con `useSidebarStore(storeId, position)`. Soportan modos:
- `large`: sidebar expandido (184px)
- `mini`: sidebar contraído (57px)
- `onhover`: aparece al hover
- `close`: oculto

El estado se persiste con `pinia-plugin-persistedstate`.
