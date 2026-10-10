# ADR-029: Listado genérico sobre CSS Grid (sin el DataTable de PrimeVue)

**Estado:** Aceptada

## Contexto

El listado genérico (`features/entity-crud/ListPage.vue`) se armaba con el `DataTable` de PrimeVue, que pinta un `<table>`. Las reglas de la tabla HTML chocaban con lo que se le pedía:

- **Alto de fila y anchos:** el ancho de cada columna lo decide el contenido de todas las celdas; un texto largo agranda la columna o la fila. No se puede fijar el alto ni configurar el ancho por columna de forma confiable.
- **Columnas fijas:** la de acciones necesitaba trucos de `frozen` y estilos `:deep` que peleaban con el tema.
- **Responsive:** una tabla solo puede hacer scroll horizontal; el listado vive en páginas anchas y en el panel angosto del chat.
- **Filtros en la cabecera:** PrimeVue remonta la cabecera en cada fetch; los inputs se construían con FormKit y se reconstruían a mano para no perder el foco.

Además, la configuración de columnas (`CollectionFieldConfig`) no funcionaba como tal: `EntityConfigSynchronizer` llamaba `setData()` sobre registros existentes y ponía `sortable`/`filterable` en `false` en cada corrida. En la base, 412 de 417 columnas estaban así: casi ningún listado ordenaba ni filtraba. Y `ColumnaFilter` solo combinaba con `AND` y descartaba los valores en lista.

## Decisión

**Presentación: `shared/data-grid/DataGrid.vue`**, propio, sin dependencia de entidades ni de la API.

- **CSS Grid con una plantilla compartida.** Cada fila es un `display: grid` con la misma `grid-template-columns` (`--dg-cols`) y el mismo ancho (el del cuerpo, con `min-width` = suma de los mínimos de cada pista). Las columnas se alinean sin medir contenido; lo que no cabe se corta con elipsis. Las pistas: preset (`xs`…`xl`), longitud (`12rem`, `160px`) o fracción (`2fr`, con mínimo `10rem`); sin ancho, `minmax(10rem, 1fr)`. Sin columnas flexibles se agrega un relleno para que las acciones queden a la derecha. Cálculo puro en `layout.ts` (con tests).
- **Alto de fila fijo** por densidad (`compact`, `normal`, `comfortable`).
- **Fijas con `position: sticky`:** cabecera (y fila de filtros) arriba, selección a la izquierda, acciones a la derecha.
- **Columnas:** ordenar (clic en el título), filtrar (embudo: abre la fila de filtros), ocultar, reordenar con GSAP Draggable desde un tirador (las vecinas se corren mientras se arrastra; la soltada aterriza con FLIP) y redimensionar desde el borde (doble clic: vuelve al ancho configurado).
- **Tarjetas** por debajo de `md` (48rem) **de ancho del contenedor** (`ResizeObserver`, no media query: el mismo listado está en la página y en el chat), o forzadas desde la vista. Todas del mismo alto, con las primeras 4 columnas.
- Resaltado de coincidencias con `<mark>` (`highlight.ts`, sin distinguir mayúsculas ni acentos). Reemplaza a la CSS Custom Highlight API y su registro global.
- Slots `cell`, `editor`, `filter`, `actions`, `empty`; emite `sort`, `filter`, `hide`, `reorder`, `resize`, `row-click`, `cell-click`, `toggle-row`, `toggle-page`, `mode`.

**Adaptador: `ListPage.vue`** conserva su API (`entity`, `configurable`, `v-model:seleccion`, `@configure`) y suma `features` (`list/listFeatures.ts`): cada vista apaga lo que no quiere (barra, acciones, filtros, edición, selección…). Precedencia: lo que la entidad no permite nunca se enciende; después la prop, después `listOptions`, después el modo (el selector del chat no edita ni opera y siempre selecciona).

- **Filtros** en la base de datos, con inputs de PrimeVue (sin FormKit): texto, número, sí/no, relación a uno (select), relación a muchos (multiselect) y rango de fechas. Varias columnas se combinan con **OR por defecto** ("Cualquiera") o AND ("Todos"); los aplicados se ven como chips.
- **Selección** opcional (apagada al abrir), por clave: sobrevive a páginas y filtros. Acción por defecto: **eliminar los seleccionados** (uno a uno: un registro con dependencias no frena al resto), más las de la entidad (`entityBulkActions`) y "Enviar por chat".
- **Edición en línea:** clic en una celda editable; Enter, elegir una opción o tocar fuera guarda; Escape cancela. Solo campos de la mutación de update; las relaciones a muchos se editan en el formulario.
- **Vista:** densidad, disposición (auto/tabla/tarjetas), maximizar (Escape restaura) y restablecer. Todo persiste en el store de la entidad (`view`, `columns` con su `width`).

**Configuración (`EntityConfiguration`)**

- `CollectionFieldConfig.width` (longitud CSS o preset).
- `EntityConfiguration.listOptions` (JSON): `pageSize`, `pageSizes`, `density`, `filterMode`, `selectable`, `inlineEdit`. `setListOptions` descarta claves y valores no válidos. Se edita en "Configuración de entidades", pestaña Listado.
- `sortable`/`filterable` en `null` significan **"lo que permita la API"**; `false` lo apaga. Sincronizar con el mapeo de Doctrine ya no pisa lo configurado (solo actualiza `kind`). La migración pasa a `null` los `false` de las entidades donde ninguna columna se configuró a mano.

**Filtro (`App\Filter\ColumnaFilter`)**

- Relaciones (a uno y a muchos): además de `campo`, `campo_list: [String]` (cualquiera de los valores).
- `_combinar: "or"`: basta con que se cumpla un filtro. Sin él, AND como hasta ahora (otros consumidores no cambian). El rango de una fecha cuenta como un solo filtro.

## Consecuencias

- Las columnas, alto de fila y acciones fijas dejan de depender del contenido y de los estilos internos de PrimeVue.
- Un listado en el chat (angosto) se ve como tarjetas sin código extra.
- `cellHighlight.ts` y la regla `::highlight(list-filter-match)` se eliminan.
- Las tarjetas muestran solo las primeras columnas: el orden de las columnas decide qué se ve en móvil.
- Sin virtualización: el listado pinta la página actual (hasta 500 filas por `listOptions`). Si alguna vez hace falta más, `DataGrid` es el lugar para agregarla sin tocar `ListPage`.
