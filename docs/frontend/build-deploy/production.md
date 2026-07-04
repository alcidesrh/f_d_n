# Producción

## Build de producción

```bash
npm run build
# → quasar build -m spa
# → Genera .output/ y dist/
```

El build produce:
- `.output/`: Artefacto SSR mínimo para servir la SPA
- `dist/`: Archivos estáticos (SPA pura)

## Docker producción

```bash
docker build --target prod -t fdn-frontend .
```

## Variables de entorno

En producción se configura:
```
NODE_ENV=production
HOSTNAME=0.0.0.0
PORT=9000
```

## Rendimiento

### Caché y carga

- Las queries GraphQL usan `cache-first` para queries repetidas
- Las watch queries usan `no-cache` para datos siempre frescos
- Persistencia de stores en localStorage (configuración, preferencias UI)
- Introspection GraphQL cacheada en localStorage

### Bundle

- Auto-import de componentes evita imports manuales pero no reduce bundle
- Las traducciones se cargan perezosamente (preparado para `@intlify/unplugin-vue-i18n`)
- Sin code splitting por ruta (todas las rutas dinámicas usan lazy loading con `() => import(...)`)
- Sin PWA service worker activo

### Optimizaciones

- TypeScript estricto para mejor calidad de código
- `assumeImmutableResults: true` en Apollo para evitar copias innecesarias
- `queryDeduplication: false` en Apollo (desactivado porque se maneja a nivel REST)
- Anti-flicker de 150ms en loadingLink

## Requisitos del servidor

- Node.js 20+ (runtime)
- PostgreSQL 16 (backend)
- Symfony 8 con API Platform (backend)
- Mercure Hub (para tiempo real)

## Endpoints requeridos

| Endpoint | Propósito |
|----------|-----------|
| `http://backend/api` | REST API |
| `http://backend/graphql` | GraphQL API |
| `http://backend/.well-known/mercure` | Mercure Hub |

## Sin tests

El proyecto no tiene framework de testing. `npm run test` es un no-op. No hay lint configurado (solo Prettier para formato).
