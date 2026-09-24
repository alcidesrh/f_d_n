# Íconos — Tabler + `Icon.vue`

## Repositorio de íconos: Tabler

**[Tabler Icons](https://tabler.io/icons)** es el repositorio de íconos del proyecto (~5.900 íconos SVG de trazo, licencia MIT). Todo ícono nuevo se busca ahí: el nombre que muestra el sitio (kebab-case, p. ej. `grip-vertical`, `rotate-ccw`, `eye-off`) es el que se pasa al componente.

El paquete instalado es `@iconify-json/tabler`. También están disponibles `@iconify-json/lucide` y `@iconify-json/material-symbols` para casos puntuales, pero **la colección por defecto y preferida es Tabler**: mezclarlas rompe la coherencia visual del trazo.

## Componente `Icon.vue`

`frontend/src/shared/ui/Icon.vue` es el único punto de uso de íconos. Se auto-registra (unplugin-vue-components sobre `src/shared/ui`), así que se usa sin importarlo:

```vue
<icon name="grip-vertical" />
<icon name="eye-off" lg />
<icon name="rotate-ccw" color="text-primary" @click="reset" />
```

Envuelve `<Icon>` de `@iconify/vue` y agrega:

- **Normalización del nombre.** Sin prefijo se asume Tabler: `name="database-cog"` → `tabler:database-cog`. Para otra colección se pasa el prefijo completo: `name="lucide:home"`.
- **Tamaños.** Props booleanas `xs` (.80rem), `sm` (.95rem), `md` (1rem), `lg` (1.5rem), `xl` (2rem); o `size` con cualquier valor CSS (default `1rem`). Se aplican a `width`/`height` **y** a `min-width`/`min-height`, para que el ícono no se deforme dentro de un flex.
- **Grosor de trazo.** `sw` (default `1.8`), heredado por `g`/`path` vía `:deep()` — necesario porque Iconify inyecta el SVG dinámicamente.
- **Color y clases.** `color` es una clase de color; la `class` del padre se suma sola. Sin ninguna de las dos el ícono queda `cursor-pointer text-surface-600`.

```vue
<!-- tirador de drag & drop del editor de configuración de entidades -->
<span data-drag-handle><icon name="grip-vertical" lg /></span>
```

### Nombres: una sola convención

`Icon.vue` acepta el nombre pelado (`database-cog`, se asume Tabler) o con colección (`tabler:database-cog`, `lucide:home`). **La convención del repo es el nombre pelado en kebab-case** tal como aparece en tabler.io/icons; es también lo que guarda `Icon.icon`.

## Consideraciones

- `@iconify/vue` resuelve el SVG **en runtime** contra la API pública de Iconify y lo cachea en el navegador. Al abrir el buscador (`IconPicker`), `shared/icons/tablerCatalog.ts` carga el set completo y lo registra con `addCollection`, así que desde ese momento los íconos pintan sin salida a internet.
- Los íconos guardados en base de datos (`Icon`, `Menu.icon`, `route.meta.icon`) son nombres de Tabler en kebab-case y se renderizan con el mismo componente: `<icon :name="route.meta.icon ?? 'link'" />`.
- `vite.config.ts` también configura `unplugin-icons` con prefijo `icon` (`<icon-tabler-home />`, `<icon-lucide-home />`), que compila el SVG en el bundle. Es una vía alternativa; **preferí `<icon name="..." />`**, que es la que entiende el resto del sistema (props de tamaño, nombres dinámicos desde la API).
- PrimeVue trae sus propias clases `pi pi-*` en props `icon` de sus componentes (`<Button icon="pi pi-save" />`). Eso es aceptable dentro de PrimeVue; fuera de sus props, Tabler.

## Elegir un ícono: `IconPicker`

- `shared/icons/IconPicker.vue`: buscador tipo tabler.io (nombre, tags o categoría; outline/filled; grilla con carga por tandas). Se usa con `v-model` (nombre del ícono).
- Input FormKit `IconPicker` (`shared/formkit/inputs/FkIconPicker.vue`): mismo buscador en popover o `inline`; su valor es el nombre.
- En el CRUD dinámico, las relaciones a-uno con `Icon` usan ese input automáticamente; al guardar, `features/entity-crud/form/iconRelation.ts` reutiliza el registro `Icon` con ese nombre o lo crea.
- Categorías y tags salen de `shared/icons/tablerMeta.ts`, generado con `npm run icons:meta` (volver a correrlo al actualizar `@iconify-json/tabler`).
