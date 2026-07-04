# Componentes de Administración

## EntityConfigurationEditor.vue

**Archivo**: `src/components/admin/EntityConfigurationEditor.vue`

Editor principal de configuración de entidad. Integra los subcomponentes de edición.

**Props**:
- `entityClass`: nombre de la entidad a configurar

**Comportamiento**:
- Carga la configuración completa vía `useEntityConfig().fetchConfigAdmin(entityClass)`
- Provee pestañas para editar colección y formulario
- Guarda cambios via `saveConfig(input)` mutation

## CollectionFieldEditor.vue

**Archivo**: `src/components/admin/CollectionFieldEditor.vue`

Editor de campos de la colección (listado).

**Props**:
- `fields`: array de `CollectionFieldConfig`

**Eventos**:
- `update`: emite cambios en la configuración de campos

**Funcionalidad**:
- Toggle de visibilidad por campo
- Toggle de filtrable/ordenable
- Reordenamiento drag & drop (integra `DraggableFields`)
- Edición de label

## FormFieldsEditor.vue

**Archivo**: `src/components/admin/FormFieldsEditor.vue`

Editor de campos del formulario.

**Props**:
- `fields`: array de `FormField`

**Eventos**:
- `update`: emite cambios

**Funcionalidad**:
- Toggle de visibilidad por campo
- Asignación a grupos
- Reordenamiento drag & drop
- Edición de label

## DraggableFields.vue

**Archivo**: `src/components/admin/DraggableFields.vue`

Lista reordenable mediante drag & drop.

**Props**:
- `items`: array de items a ordenar
- `itemKey`: key única para identificar items

**Eventos**:
- `reorder`: emite el array reordenado

**Comportamiento**:
- Usa eventos nativos de drag & drop HTML5
- No depende de librerías externas

## EntityCard.vue

**Archivo**: `src/components/admin/EntityCard.vue`

Card de presentación de entidad en el listado de administración.

**Props**:
- `entity`: nombre de la entidad
- `icon`: icono Material Symbols
- `description`: descripción opcional

**Eventos**:
- `configure`: navega al editor de configuración
- `view`: navega al listado de la entidad

## StatsCard.vue

**Archivo**: `src/components/admin/StatsCard.vue`

Card de indicador/KPI usado en el Dashboard.

**Props**:
- `title`: título del indicador
- `value`: valor numérico
- `icon`: icono Material Symbols
- `color`: clase de color de fondo
- `trend`: tendencia (opcional, ej: "+12%")
