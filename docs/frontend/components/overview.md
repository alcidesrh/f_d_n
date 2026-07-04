# Componentes

## Arquitectura

Los componentes Vue se organizan en `src/components/` por módulo funcional. Son **auto-importados globalmente** por `unplugin-vue-components`, que escanea `src/components/` en profundidad y genera `src/components.d.ts`.

```
src/components/
├── admin/             # Componentes de administración
│   ├── CollectionFieldEditor.vue
│   ├── DraggableFields.vue
│   ├── EntityCard.vue
│   ├── EntityConfig.vue
│   ├── EntityConfigurationEditor.vue
│   ├── FormFieldsEditor.vue
│   └── StatsCard.vue
├── common/            # Componentes comunes
│   └── CommonBreadcrumb.vue
├── crud/              # CRUD dinámico
│   ├── collection/
│   │   ├── CollectionBody.vue
│   │   ├── CollectionCell.vue
│   │   ├── CollectionHeader.vue
│   │   ├── CollectionTop.vue
│   │   └── DynamicCollection.vue
│   └── form/
│       ├── CrudButton.vue
│       └── DynamicForm.vue
├── dynamic/           # Navegación dinámica
│   ├── MenuLarge.vue
│   └── MenuMini.vue
├── permiso/           # CRUD Permiso
├── preload/           # Skeletons de carga
├── role/              # CRUD Role
├── sidebar/           # Sidebar
│   ├── SectionMini.vue
│   └── SectionTree.vue
├── user/              # CRUD Usuario
├── Breadcrumbs.vue
├── ChangePasswordModal.vue
├── Clock.vue
├── Icon.vue
├── IconPicker.vue
├── Notify.vue
├── ProfilerFooter.vue
├── ResponsiveComponent.vue
├── ResponsiveLayout.vue
├── SidebarDrawer.vue
├── SidebarLeft.vue
├── SidebarRight.vue
├── SubMenuMini.vue
└── Topbar.vue
```

## Convenciones de nomenclatura

- **PascalCase** para nombres de archivo y componente
- Prefijo descriptivo: `Collection*`, `Dynamic*`, `Menu*`, `Section*`
- Componentes de CRUD específico: `{Entity}{Action}` (ej: `UserCreate`, `RoleForm`, `PermisoList`)
- Sin prefijo de módulo para componentes compartidos (raíz de `components/`)

## Auto-import

Configuración en `quasar.config.ts`:

```typescript
Components({
  dirs: ['src/components'],
  extensions: ['vue'],
  deep: true,
  dts: 'src/components.d.ts',
})
```

Esto permite usar `<DynamicCollection />` sin importación explícita.

## Preload components

En `src/components/preload/`:
- `ButtonPreload.vue`: Skeleton para botones
- `FormPreload.vue`: Skeleton para formularios (acepta prop `cols`)
- `ListPreload.vue`: Skeleton para listados (acepta prop `cols`)
