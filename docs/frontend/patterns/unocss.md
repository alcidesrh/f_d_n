# UnoCSS — Configuración y Estilos Utilitarios

## Configuración general

El archivo `uno.config.ts` en la raíz del proyecto configura UnoCSS con:

```typescript
export default defineConfig({
  rules: [...utopia_rules, ...color_rules],
  presets: [presetAttributify(), presetWind4({ preflights: { reset: true, theme: true } })],
  theme: { colors: { ...colors } },
  transformers: [transformerDirectives(), transformerVariantGroup()],
  content: { pipeline: { include: [...] } },
})
```

### Presets

- **presetWind4**: Versión 4 del preset Wind de UnoCSS. Incluye reset CSS y clases de tema Tailwind.
- **presetAttributify**: Permite usar clases como atributos HTML: `<div text-center p-4>`.

### Transformers

- **transformerDirectives**: Permite usar `@apply` en CSS.
- **transformerVariantGroup**: Agrupa variantes: `hover:(text-center p-4)`.

### Pipeline de contenido

UnoCSS escanea todos los archivos relevantes:
```
src/form/formkit.theme.ts
src/form/inputs/**/*.{ts,js,vue}
src/utils/**/*, src/composables/**/*
src/stores/**/*, src/pages/**/*, src/layouts/**/*
src/components/**/*
```

## Reglas personalizadas

Definidas en `src/utils/unocss_rules.ts` (90 líneas).

### Reglas Utopia (`u-`)

El sistema de espaciado y tipografía Utopia usa variables CSS fluidas:

```typescript
[/^u-(-)?([mpg])([a-z]?)-(\d{0,2}[a-z]{0,2})$/, ...]
```

| Patrón | CSS resultante |
|--------|---------------|
| `u-p-xs` | `padding: var(--space-xs)` |
| `u-mx-l` | `margin-left: var(--space-l); margin-right: var(--space-l)` |
| `u--mt-s` | `margin-top: var(--space--s)` (negativo) |
| `u-text-3` | `font-size: var(--step-3)` |
| `u--text-1` | `font-size: var(--step--1)` |
| `font-7` | `font-weight: 700` |

Propiedades soportadas: `m` (margin), `p` (padding), `g` (gap). Direcciones: `t`, `b`, `l`, `r`, `x`, `y`.

### Reglas de color

```typescript
[/^(slate|...|surface|surface-contrast)-([1-9]|...|950)$/, ...]
```

Genera reglas de color usando variables CSS: `surface-5` → `color: var(--surface-5)`.

### Shortcuts

`text-base` → `{ line-height: 'normal' }`

## Sistema de colores OKLCH

Definido en `src/utils/colors.ts` (296 líneas). 17 matices con 11 escalas cada uno:

| Matices | Escalas |
|---------|---------|
| red, orange, amber, yellow, lime, green, emerald, teal, cyan, sky, blue, indigo, violet, purple | 1-11 (claro a oscuro) |
| gray, zinc, neutral, stone, slate, surface | 1-11 (grises) |

Los colores usan el espacio de color **OKLCH** (perceptualmente uniforme):
```typescript
red: {
  "1": "oklch(97.1 % 0.013 17.38)",
  "5": "oklch(70.4 % 0.191 22.216)",
  "8": "oklch(50.5 % 0.213 27.518)",
  "11": "oklch(25.8 % 0.092 26.042)",
}
```

Se inyectan como variables CSS vía `preflights` en `uno.config.ts`:
```css
:root { --colors-surface-1: oklch(...); }
```

Los colores están disponibles en el theme de UnoCSS como clases utilitarias (ej: `text-red-5`, `bg-blue-8`, `border-surface-4`).

## Función SCSS `-alpha`

Inyectada globalmente via Vite config en `quasar.config.ts`:

```scss
@function -alpha($color, $alpha) {
  // Convierte OKLCH a OKLCH con transparencia
  @return unquote(str-replace($color-str, ')', ' / ' + $alpha + ')'));
}
```

Uso: `background-color: -alpha($surface-5, 50%)` → `oklch(70.4% 0.04 256.788 / 50%)`.

## Archivos SCSS globales

En `src/css/`:
- `app.scss`: Punto de entrada principal
- `_fonts.scss`: Definiciones de fuentes
- `_helpers.scss`: Clases helper
- `color.scss`: Utilidades de color
- `layout.scss`: Estilos de layout
- `media-queries.scss`: Media queries
- `root.scss`: Variables CSS raíz
- `utopia.scss`: Escala Utopia CSS
- `quasar.variables.scss`: Variables Quasar
- `theme/`: Estilos de tema
- `variables/`: Variables adicionales
- `components/`: Estilos de componentes específicos
