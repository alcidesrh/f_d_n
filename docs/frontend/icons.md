# Íconos — Tabler + `Icon.vue`

## Repositorio de íconos: Tabler

**[Tabler Icons](https://tabler.io/icons)** es el repositorio de íconos del proyecto (~5.900 íconos SVG de trazo, licencia MIT). Todo ícono nuevo se busca ahí: el nombre que muestra el sitio (kebab-case, p. ej. `grip-vertical`, `rotate-ccw`, `eye-off`) es el que se pasa al componente.

El paquete instalado es `@iconify-json/tabler`. También están disponibles `@iconify-json/lucide` y `@iconify-json/material-symbols` para casos puntuales, pero **la colección por defecto y preferida es Tabler**: mezclarlas rompe la coherencia visual del trazo.

## Componente `Icon.vue`

`frontend/src/components/common/Icon.vue` es el único punto de uso de íconos. Se auto-registra (unplugin-vue-components sobre `src/components`), así que se usa sin importarlo:

```vue
<icon name="grip-vertical" />
<icon name="eye-off" lg />
<icon name="rotate-ccw" color="text-primary" @click="reset" />
```

Envuelve `<Icon>` de `@iconify/vue` y agrega:

- **Normalización del nombre.** Sin prefijo se asume Tabler: `name="database-cog"` → `tabler:database-cog`. Para otra colección se pasa el prefijo completo: `name="lucide:home"`.
- **Tamaños.** Props booleanas `xs` (.80rem), `sm` (.95rem), `md` (1rem), `lg` (1.5rem), `xl` (2rem); o `size` con cualquier valor CSS (default `1rem`). Se aplican a `width`/`height` **y** a `min-width`/`min-height`, para que el ícono no se deforme dentro de un flex.
- **Grosor de trazo.** `sw` (default `1.8`), heredado por `g`/`path` vía `:deep()` — necesario porque Iconify inyecta el SVG dinámicamente.
- **Color y clases.** `color` y `class` se concatenan; sin ninguno de los dos el ícono queda `cursor-pointer text-surface-600`.

```vue
<!-- tirador de drag & drop del editor de configuración de entidades -->
<span data-drag-handle><icon name="grip-vertical" lg /></span>
```

### Nombres: una sola convención

`Icon.vue` acepta `tabler_database-cog` y `tabler-database-cog` además de `database-cog`, pero **la convención del repo es el nombre pelado en kebab-case** tal como aparece en tabler.io/icons.

## Consideraciones

- `@iconify/vue` resuelve el SVG **en runtime** contra la API pública de Iconify y lo cachea en el navegador. No hay colección registrada offline (`addCollection`), así que sin salida a internet los íconos no pintan. Si eso llega a ser un requisito, se registra la colección en el bootstrap y el resto del código no cambia.
- Los íconos guardados en base de datos (`Icon`, `Menu.icon`, `route.meta.icon`) son nombres de Tabler en kebab-case y se renderizan con el mismo componente: `<icon :name="route.meta.icon ?? 'link'" />`.
- `vite.config.ts` también configura `unplugin-icons` con prefijo `icon` (`<icon-tabler-home />`, `<icon-lucide-home />`), que compila el SVG en el bundle. Es una vía alternativa; **preferí `<icon name="..." />`**, que es la que entiende el resto del sistema (props de tamaño, nombres dinámicos desde la API).
- PrimeVue trae sus propias clases `pi pi-*` en props `icon` de sus componentes (`<Button icon="pi pi-save" />`). Eso es aceptable dentro de PrimeVue; fuera de sus props, Tabler.
