# Sistema de Colores OKLCH

**Archivo**: `src/utils/colors.ts` — 296 líneas

## Espacio de color OKLCH

El proyecto usa **OKLCH** como espacio de color. OKLCH es perceptualmente uniforme, lo que significa que la distancia entre colores corresponde a la percepción humana.

Formato: `oklch(L C H)` donde:
- `L`: Luminosidad (0-100%)
- `C`: Croma (saturación)
- `H`: Hue (matiz en grados)

## 17 Matices × 11 Escalas

| Matiz | 1-11 (claro → oscuro) |
|-------|----------------------|
| `red` | L: 97.1% → 25.8%, C: 0.013 → 0.092 |
| `orange` | L: 98% → 26.6%, C: 0.016 → 0.079 |
| `amber` | L: 98.7% → 27.9%, C: 0.022 → 0.077 |
| `yellow` | L: 98.7% → 42.1%, C: 0.026 → 0.095 |
| `lime` | L: 98.6% → 27.4%, C: 0.031 → 0.072 |
| `green` | L: 98.2% → 26.6%, C: 0.018 → 0.065 |
| `emerald` | L: 97.9% → 26.2%, C: 0.021 → 0.051 |
| `teal` | L: 98.4% → 27.7%, C: 0.014 → 0.046 |
| `cyan` | L: 98.4% → 30.2%, C: 0.019 → 0.056 |
| `sky` | L: 97.7% → 29.3%, C: 0.013 → 0.066 |
| `blue` | L: 97% → 28.2%, C: 0.014 → 0.091 |
| `indigo` | L: 96.2% → 25.7%, C: 0.018 → 0.09 |
| `violet` | L: 96.9% → 28.3%, C: 0.016 → 0.141 |
| `purple` | L: 97.7% → 29.1%, C: 0.014 → 0.149 |
| `gray` | L: 98.5% → 13%, C: 0.002 → 0.028 |
| `zinc` | L: 98.5% → 14.1%, C: 0 → 0.005 |
| `neutral` | L: 98.5% → 14.5%, C: 0 |
| `stone` | L: 98.5% → 14.7%, C: 0.001 → 0.004 |
| `slate` | L: 98.4% → 12.9%, C: 0.003 → 0.042 |
| `surface` | L: 98.4% → 12.9%, C: 0.003 → 0.042 (alias de slate) |

## Inyección como variables CSS

En `uno.config.ts`, los colores se inyectan como variables CSS:

```typescript
preflights: [{
  getCSS: () => {
    const vars = Object.entries(colors.surface)
      .map(([k, v]) => `--colors-surface-${k}: ${v};`)
      .join('\n')
    return `:root { ${vars} }`
  },
}]
```

## Uso en UnoCSS

Los colores están disponibles en el theme:

```html
<div class="text-blue-5 bg-red-1 border-surface-4">
<div class="hover:text-green-6">
<div class="bg-purple-8 text-white">
```

## Regla personalizada de color

En `src/utils/unocss_rules.ts`:

```typescript
[/^(slate|red|...|surface)-(\d+)$/, (match) => {
  return { color: `var(--${match[1]}-${match[2]})` }
}]
```

## Función SCSS `-alpha`

Para opacidad sobre colores OKLCH:

```scss
background: -alpha($blue-5, 30%);
// → oklch(70.7% 0.165 254.624 / 30%)
```
