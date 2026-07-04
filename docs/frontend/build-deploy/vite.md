# Vite (via Quasar CLI)

La configuración de Vite se maneja indirectamente a través de `quasar.config.ts`. Quasar CLI internamente usa Vite como bundler.

## Configuración en quasar.config.ts

### Build target
```typescript
build: {
  target: {
    browser: ['es2022', 'firefox115', 'chrome115', 'safari14'],
    node: 'node20',
  },
  vueRouterMode: 'history',
  typescript: { strict: true, vueShim: true },
}
```

### Aliases
```typescript
extendViteConf(viteConf) {
  viteConf.resolve.alias = {
    ...(viteConf.resolve.alias || {}),
    '@': path.resolve(__dirname, './src'),
  }
}
```

- `@/` → `src/` (configurado también en `tsconfig.json`)

### Plugins Vite

```typescript
vitePlugins: [
  Components({ dirs: ['src/components'], deep: true, dts: 'src/components.d.ts' }),
  AutoImport({ dirs: ['src/composables', 'src/stores/autoimport/**/*', ...], imports: ['vue', 'vue-router', 'pinia', { quasar: ['useQuasar'] }], dts: 'src/auto-imports.d.ts' }),
]
```

En `extendViteConf`:
```typescript
viteConf.plugins.push(UnoCSS())
```

### SCSS global
```typescript
viteConf.css.preprocessorOptions.scss.additionalData = `
  @function -alpha($color, $alpha) { ... }
`
```

## Plugins instalados (package.json)

| Plugin | Versión | Propósito |
|--------|---------|-----------|
| `@quasar/app-vite` | ^2.1.0 | Quasar CLI con Vite |
| `unocss` | ^66.5.10 | Estilos utilitarios |
| `@unocss/preset-wind4` | ^66.5.10 | Preset Wind v4 |
| `unplugin-auto-import` | ^20.3.0 | Auto-import de APIs |
| `unplugin-vue-components` | ^30.0.0 | Auto-import de componentes |
| `@intlify/unplugin-vue-i18n` | ^4.0.0 | i18n (comentado) |

## PostCSS

Configuración en `postcss.config.js` (raíz del proyecto). Se integra con Vite via `viteConf.css.postcss.plugins`.
