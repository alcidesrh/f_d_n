# Estrategia de Estilos

La aplicación usa una combinación de tres tecnologías de estilos:

1. **UnoCSS** (utilitario) — Estilos en línea via clases utilitarias
2. **SCSS** (componentes) — Estilos encapsulados en archivos `.scss` y `<style lang="scss">`
3. **Utopia** (fluido) — Escalas tipográficas y de espaciado fluidas

## Capas de estilos

```
UnoCSS (virtual:uno.css)
  └─ presetWind4 (reset + theme Tailwind)
  └─ presetAttributify (atributos como clases)
  └─ rules personalizadas (Utopia, colores OKLCH)
SCSS (app.scss)
  ├─ _fonts.scss
  ├─ _helpers.scss
  ├─ color.scss
  ├─ layout.scss
  ├─ root.scss
  ├─ utopia.scss
  ├─ quasar.variables.scss
  └─ media-queries.scss
```

## Principios

- **Utility-first**: La mayoría de estilos se aplican con clases UnoCSS directamente en templates
- **Variables CSS**: Colores, espaciado y tipografía usan variables CSS definidas en `:root`
- **OKLCH**: Sistema de color perceptualmente uniforme
- **Responsive**: Breakpoints Quasar + servicio ResponsiveService + clases UnoCSS responsivas

## Archivos principales

| Archivo | Propósito |
|---------|-----------|
| `src/css/app.scss` | Punto de entrada de estilos SCSS |
| `src/css/root.scss` | Variables CSS raíz |
| `src/css/utopia.scss` | Escala Utopia (tipografía fluida) |
| `src/css/layout.scss` | Estilos de layout (sidebar, topbar) |
| `src/css/color.scss` | Utilidades de color |
| `src/css/media-queries.scss` | Media queries manuales |
| `src/css/_fonts.scss` | Definiciones de fuentes |
| `src/css/_helpers.scss` | Clases helper SCSS |
| `src/css/theme/` | Estilos de tema |
| `src/css/variables/` | Variables adicionales |
| `src/css/components/` | Estilos de componentes específicos |

## Función SCSS `-alpha`

Inyectada globalmente via Vite, permite aplicar opacidad a colores OKLCH:

```scss
.element {
  background-color: -alpha($surface-5, 50%);
  // → oklch(70.4% 0.04 256.788 / 50%)
}
```
