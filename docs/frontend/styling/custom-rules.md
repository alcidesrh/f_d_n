# Reglas Personalizadas de UnoCSS

**Archivo**: `src/utils/unocss_rules.ts` — 90 líneas

## Reglas Utopia (espaciado fluido)

Patrón: `u-(-)?([mpg])([a-z]?)-(\d{0,2}[a-z]{0,2})`

| Clase | CSS | Descripción |
|-------|-----|-------------|
| `u-p-xs` | `padding: var(--space-xs)` | Padding todos los lados |
| `u-px-l` | `padding-left: var(--space-l); padding-right: var(--space-l)` | Padding horizontal |
| `u-mt-s` | `margin-top: var(--space-s)` | Margin top |
| `u--ml-3` | `margin-left: var(--space--3)` | Margin negativo |
| `u-g-m` | `gap: var(--space-m)` | Gap (grid/flex) |
| `u-gx-l` | `column-gap: var(--space-l)` | Gap horizontal |

**Propiedades**: `m` (margin), `p` (padding), `g` (gap)
**Direcciones**: `t` (top), `b` (bottom), `l` (left), `r` (right), `x` (horizontal), `y` (vertical)

## Reglas Utopia (tipografía fluida)

Patrón: `u-(-)?([a-z]+)-(\d{1,2})`

| Clase | CSS | Descripción |
|-------|-----|-------------|
| `u-text-3` | `font-size: var(--step-3)` | Texto grande |
| `u--text-1` | `font-size: var(--step--1)` | Texto pequeño |
| `u-leading-5` | `line-height: var(--step-5)` | Altura de línea |

## Reglas de peso de fuente

`font-{n}` → `font-weight: {n}`

| Clase | CSS |
|-------|-----|
| `font-4` | `font-weight: 400` |
| `font-7` | `font-weight: 700` |
| `font-9` | `font-weight: 900` |

## Reglas de color

Patrón: `{color}-{shade}`

| Clase | CSS |
|-------|-----|
| `red-5` | `color: var(--red-5)` |
| `surface-3` | `color: var(--surface-3)` |

## Shortcuts

| Clase | CSS |
|-------|-----|
| `text-base` | `line-height: normal` |

## Variables CSS Utopia

Las variables son generadas por `utopia-core` e incluidas en SCSS:

```css
:root {
  --space-3xs: clamp(0.25rem, 0.23rem + 0.1vw, 0.3125rem);
  --space-2xs: clamp(0.5rem, 0.46rem + 0.2vw, 0.625rem);
  --space-xs: clamp(0.75rem, 0.69rem + 0.3vw, 0.9375rem);
  --space-s: clamp(1rem, 0.92rem + 0.4vw, 1.25rem);
  --space-m: clamp(1.5rem, 1.38rem + 0.6vw, 1.875rem);
  --space-l: clamp(2rem, 1.84rem + 0.8vw, 2.5rem);
  --space-xl: clamp(3rem, 2.76rem + 1.2vw, 3.75rem);
  --space-2xl: clamp(4rem, 3.68rem + 1.6vw, 5rem);
  --space-3xl: clamp(6rem, 5.52rem + 2.4vw, 7.5rem);
  --step--2: clamp(0.6944rem, 0.6711rem + 0.1167vw, 0.7813rem);
  --step--1: clamp(0.8333rem, 0.7986rem + 0.1736vw, 0.9375rem);
  --step-0: clamp(1rem, 0.95rem + 0.25vw, 1.125rem);
  --step-1: clamp(1.2rem, 1.13rem + 0.35vw, 1.424rem);
  --step-2: clamp(1.44rem, 1.344rem + 0.48vw, 1.8019rem);
  --step-3: clamp(1.728rem, 1.5989rem + 0.6456vw, 2.2795rem);
  --step-4: clamp(2.0736rem, 1.9014rem + 0.8611vw, 2.8837rem);
  --step-5: clamp(2.4883rem, 2.2612rem + 1.1358vw, 3.648rem);
}
```
