# Configuración de UnoCSS

**Archivo**: `uno.config.ts` — 79 líneas

## Configuración

```typescript
export default defineConfig({
  rules: [...utopia_rules, ...color_rules],
  presets: [presetAttributify(), presetWind4({ preflights: { reset: true, theme: true } })],
  theme: { colors: { ...colors } },
  transformers: [transformerDirectives(), transformerVariantGroup()],
  content: { pipeline: { include: [...] } },
})
```

## Presets

### presetWind4
Versión 4 del preset Wind, sucesor de Tailwind. Incluye:
- **Reset CSS**: normalize/reset incluido
- **Theme**: colores, spacings, fuentes base de Tailwind
- Soporte para variantes: `hover:`, `focus:`, `dark:`, `sm:`, `md:`, etc.

### presetAttributify
Permite usar clases como atributos HTML:

```html
<!-- En lugar de: -->
<div class="text-center p-4 bg-blue-5">
<!-- Se puede: -->
<div text-center p-4 bg-blue-5>
```

## Transformers

### transformerDirectives
Permite usar `@apply` en CSS:

```css
.btn {
  @apply px-4 py-2 bg-blue-5 text-white rounded;
}
```

### transformerVariantGroup
Agrupa variantes para reducir repetición:

```html
<div hover:(text-blue-5 bg-white) sm:(p-4 text-lg)>
```

## Content Pipeline

UnoCSS escanea estos archivos para detectar clases usadas:

```
src/form/formkit.theme.ts
src/form/inputs/**/*
src/utils/**/*
src/composables/**/*
src/stores/**/*
src/pages/**/*
src/layouts/**/*
src/components/**/*
```

## Instalación en Vite

En `quasar.config.ts`:

```typescript
viteConf.plugins.push(UnoCSS())
```

## Integración con Quasar

```typescript
// quasar.config.ts
css: ['app.scss']  // Solo app.scss, no Quasar CSS por defecto
framework: {
  config: {
    screen: { bodyClasses: true },  // bodyClasses para responsive
  },
}
```

Las clases `screen-*` se agregan al body automáticamente para estilos condicionales por breakpoint.
