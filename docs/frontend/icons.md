# Íconos — Material Symbols + `Icon.vue`

## Repositorio de íconos: Google Material Symbols

**[Material Symbols](https://fonts.google.com/icons)** (Google, licencia Apache 2.0, ~3.900 símbolos con hasta seis variantes cada uno) es el repositorio de íconos del proyecto. Todo ícono nuevo se busca ahí; el nombre que muestra el sitio (`snake_case`: `drag_indicator`, `directions_bus`) se pasa al componente en **kebab-case** (`drag-indicator`, `directions-bus`), que es el formato de la colección Iconify `material-symbols`.

El paquete instalado es `@iconify-json/material-symbols`. También está `@iconify-json/lucide` (solo lo usa `ThemeEditor.vue` con `unplugin-icons`); **la colección por defecto es Material Symbols**: mezclarlas rompe la coherencia visual.

### Variantes de estilo

Cada símbolo se nombra con un sufijo según su estilo:

| Sufijo | Estilo | Ejemplo |
| --- | --- | --- |
| *(ninguno)* | Relleno | `home` |
| `-outline` | Contorno | `home-outline` |
| `-rounded` | Relleno redondeado | `home-rounded` |
| `-outline-rounded` | Contorno redondeado | `home-outline-rounded` |
| `-sharp`, `-outline-sharp` | Afilado | `home-sharp` |

**Convención del repo: la variante de contorno (`-outline`) cuando existe**, por ser la más cercana al trazo ligero que tenía Tabler; si el símbolo no tiene contorno (`add`, `close`, `search`, `menu`…) se usa el nombre pelado. No todo símbolo tiene todas las variantes: confirmá el nombre en el buscador (`IconPicker`) o en fonts.google.com/icons.

## Componente `Icon.vue`

`frontend/src/shared/ui/Icon.vue` es el único punto de uso de íconos. Se auto-registra (unplugin-vue-components sobre `src/shared/ui`), así que se usa sin importarlo:

```vue
<icon name="drag-indicator" />
<icon name="visibility-off-outline" lg />
<icon name="restart-alt" color="text-primary" @click="reset" />
```

Envuelve `<Icon>` de `@iconify/vue` y agrega:

- **Normalización del nombre.** Sin prefijo se asume Material Symbols: `name="database-outline"` → `material-symbols:database-outline`. Para otra colección se pasa el prefijo completo: `name="lucide:home"`.
- **Tamaños.** Props booleanas `xs` (.80rem), `sm` (.95rem), `md` (1rem), `lg` (1.5rem), `xl` (2rem); o `size` con cualquier valor CSS (default `1.3rem`). Se aplican a `width`/`height` **y** a `min-width`/`min-height`, para que el ícono no se deforme dentro de un flex.
- **Color y clases.** `color` es una clase de color; la `class` del padre se suma sola. Sin ninguna de las dos el ícono queda `cursor-pointer text-surface-600`.

Los Material Symbols son glifos rellenos (`fill: currentColor`): **no existe grosor de trazo** (el `sw` de la época de Tabler se eliminó). El peso visual lo da la variante (`-outline`, `-rounded`…) y el tamaño.

```vue
<!-- tirador de drag & drop del editor de configuración de entidades -->
<span data-drag-handle><icon name="drag-indicator" lg /></span>
```

### Nombres: una sola convención

`Icon.vue` acepta el nombre pelado (`database-outline`, se asume Material Symbols) o con colección (`material-symbols:database-outline`, `lucide:home`). **La convención del repo es el nombre pelado en kebab-case**; es también lo que guarda `Icon.icon`.

## Consideraciones

- `@iconify/vue` resuelve el SVG **en runtime** contra la API pública de Iconify y lo cachea en el navegador. Al abrir el buscador (`IconPicker`), `shared/icons/iconCatalog.ts` carga el set completo (~8 MB, solo entonces) y lo registra con `addCollection`, así que desde ese momento los íconos pintan sin salida a internet.
- Los íconos guardados en base de datos (`Icon`, `Menu.icon`, `route.meta.icon`) son nombres de Material Symbols en kebab-case y se renderizan con el mismo componente: `<icon :name="route.meta.icon ?? 'link'" />`. `Icon.icon` admite hasta 100 caracteres (el nombre más largo del set tiene 51). La migración `Version20261005120000` convirtió los nombres de Tabler que ya existían.
- `vite.config.ts` también configura `unplugin-icons` con prefijo `icon` (`<icon-material-symbols-home />`, `<icon-lucide-home />`), que compila el SVG en el bundle. Es una vía alternativa; **preferí `<icon name="..." />`**, que es la que entiende el resto del sistema (props de tamaño, nombres dinámicos desde la API).
- PrimeVue trae sus propias clases `pi pi-*` en props `icon` de sus componentes (`<Button icon="pi pi-save" />`). Eso es aceptable dentro de PrimeVue; fuera de sus props, Material Symbols.
- La página pública (`pagina/`) es otra app con su propio `Icono.vue` y compila Material Symbols (`~icons/material-symbols/*`); no usa este componente. No hay logos de marcas: WhatsApp y Facebook usan `chat-outline` y `thumb-up-outline`.

## Elegir un ícono: `IconPicker`

- `shared/icons/IconPicker.vue`: buscador (nombre, tags o categoría; selector de estilo — por defecto contorno —; grilla con carga por tandas). Cada tile es un símbolo; al elegirlo se emite el nombre de la variante del estilo activo (o el relleno si no la tiene). Se usa con `v-model` (nombre del ícono).
- Input FormKit `IconPicker` (`shared/formkit/inputs/FkIconPicker.vue`): mismo buscador en popover o `inline`; su valor es el nombre.
- En el CRUD dinámico, las relaciones a-uno con `Icon` usan ese input automáticamente; al guardar, `features/entity-crud/form/iconRelation.ts` reutiliza el registro `Icon` con ese nombre o lo crea.
- Categorías (las de Iconify) y tags (los de Google Fonts) salen de `shared/icons/iconMeta.ts`, generado con `npm run icons:meta` (volver a correrlo al actualizar `@iconify-json/material-symbols`; necesita red).
