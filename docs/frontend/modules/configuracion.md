# Módulo Configuración

## Entidades

| Entidad | Icono | Descripción |
|---------|-------|-------------|
| `Icon` | — | Iconos Material Symbols |
| `IconCategory` | — | Categorías de iconos |
| `ApiToken` | `key` | Tokens de API |
| `Option` | `settings` | Opciones del sistema |

## Páginas

### Admin de entidades

Rutas bajo `/admin/entities`:

| Ruta | Componente | Propósito |
|------|-----------|-----------|
| `/admin/entities` | `EntityList.vue` | Listado de entidades configurables |
| `/admin/entities/config/:entity` | `EntityConfig.vue` | Editor de configuración visual |

### Dashboard admin

| Ruta | Componente | Propósito |
|------|-----------|-----------|
| `/admin/dashboard` | `DashboardPage.vue` | Dashboard administrativo |

### EntityConfig.vue (`src/pages/admin/EntityConfig.vue`)

Página de configuración visual de entidades. Integra los componentes:
- `EntityConfigurationEditor.vue`: Editor principal
- `CollectionFieldEditor.vue`: Editor de campos del listado
- `FormFieldsEditor.vue`: Editor de campos del formulario
- `DraggableFields.vue`: Ordenamiento drag & drop de campos

### EntityList.vue (`src/pages/admin/EntityList.vue`)

Listado de todas las entidades editables con acceso rápido a su configuración.

### DynamicCollectionAdmin.vue

Variante admin de DynamicCollection con funcionalidades adicionales de administración.

## Componentes de administración

Todos en `src/components/admin/`:

| Componente | Propósito |
|-----------|-----------|
| `EntityConfigurationEditor.vue` | Editor completo de configuración de entidad |
| `CollectionFieldEditor.vue` | Editor de campos de colección (visibilidad, orden, filtros) |
| `FormFieldsEditor.vue` | Editor de campos de formulario (visibilidad, grupos) |
| `DraggableFields.vue` | Lista drag & drop para reordenar campos |
| `EntityCard.vue` | Card de presentación de entidad |
| `StatsCard.vue` | Card de estadísticas (usado en Dashboard) |
| `EntityConfig.vue` | Página de configuración de entidad |

## Composable useEntityConfig

`src/composables/useEntityConfig.ts` proporciona operaciones GraphQL para CRUD de configuración:

- `fetchAllConfigs()`: Obtiene todas las configuraciones
- `fetchConfig(entityClass)`: Obtiene configuración de una entidad
- `fetchConfigAdmin(entityClass)`: Obtiene configuración con datos completos (clonado)
- `saveConfig(input)`: Guarda cambios en la configuración

## Flujo de edición

1. Admin navega a `/admin/entities`
2. Selecciona entidad → `/admin/entities/config/:entity`
3. `EntityConfig.vue` carga configuración via `fetchConfigAdmin()`
4. Admin edita campos del listado y formulario
5. Guarda cambios via `saveConfig()` (mutation GraphQL)
6. StaticDataGateway detecta cambios y reinicializa stores afectadas
