# Componentes de Colección CRUD

## DynamicCollection.vue

**Archivo**: `src/components/crud/collection/DynamicCollection.vue`

Componente principal de listado dinámico. Renderiza un `q-table` de Quasar configurado desde la store dinámica de la entidad.

**Props**: Ninguna (usa `getStore()` para obtener la store del parámetro de ruta).

**Slots**: Ninguno directo. El slot `#actions_row` se pasa a `CollectionBody`.

**Eventos**: Ninguno (la navegación se maneja internamente).

**Comportamiento**:
- Obtiene la store vía `getStore()` en `onBeforeMount`
- Configura `q-table` con columnas desde `store.computedColumns`
- Paginación server-side sincronizada con `store.pagination`
- Filtros en línea usando `<FormKit v-model="store.filters">`
- Selección múltiple para operaciones masivas
- Modo grid en `$q.screen.xs`
- Altura máxima dinámica: `$q.screen.height - 270`
- Muestra `ListPreload` mientras la store no tiene columnas

**Métodos internos**:
- `onRequest({ pagination })`: maneja cambios de página, orden y tamaño
- `toggleSelectionMode()`: activa/desactiva selección múltiple
- `removeMultiple()`: elimina elementos seleccionados vía `store.removeMultiple()`
- `reset()`: limpia selección

## CollectionBody.vue

**Archivo**: `src/components/crud/collection/CollectionBody.vue`

Renderiza las filas del cuerpo de la tabla.

**Props**:
- `data`: datos de la fila (desde q-table slot)
- `selectionMode`: modo selección activo

**Slots**:
- `actions_row`: acciones por fila (editar, eliminar)

## CollectionCell.vue

**Archivo**: `src/components/crud/collection/CollectionCell.vue`

Renderiza celdas individuales con formato según tipo de campo. Maneja valores relacionados mostrando el label del objeto relacionado.

## CollectionHeader.vue

**Archivo**: `src/components/crud/collection/CollectionHeader.vue`

Renderiza el header de la tabla con opciones de ordenación y selección masiva.

**Props**:
- `selectionMode`: modo selección activo
- `selected`: elementos seleccionados
- `data`: datos del header
- `clear`: flag para limpiar selección

**Eventos**:
- `remove-multiple`: dispara eliminación masiva
- `order-columns`: reordenamiento de columnas

## CollectionTop.vue

**Archivo**: `src/components/crud/collection/CollectionTop.vue`

Barra superior del listado con acciones globales.

**Props**:
- `inFullscreen`: estado de pantalla completa

**Eventos**:
- `reload`: recarga la colección
- `toggle-fullscreen`: pantalla completa
- `toggle-selection-mode`: modo selección
- `reset`: reinicia filtros y selección
