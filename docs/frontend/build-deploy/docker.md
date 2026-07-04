# Docker

**Archivo**: `frontend/Dockerfile` — 54 líneas

## Multi-stage build

```dockerfile
FROM node:24-alpine AS node_upstream

FROM node_upstream AS base
RUN apk add --no-cache libc6-compat
WORKDIR /frontend
```

### Stage dev
```dockerfile
FROM base as dev
EXPOSE 9000
ENV PORT 9000
ENV HOSTNAME localhost
CMD ["sh", "-c", "npm install && npm run dev"]
```

- Usa `npm install` en cada inicio (no pre-build layer)
- Puerto 9000, hostname localhost
- No usa pnpm (corepack eliminado)

### Stage builder
```dockerfile
FROM base AS builder
COPY package*.json ./
RUN npm install --omit=dev
COPY . .
RUN npm run build
```

- Instala solo dependencias de producción
- Copia todo el código fuente
- Ejecuta `quasar build -m spa`

### Stage prod
```dockerfile
FROM node_upstream AS prod
WORKDIR /frontend
ENV NODE_ENV production
COPY --from=builder /frontend/.output ./.output
COPY --from=builder /frontend/public ./public
EXPOSE 9000
ENV PORT 9000
ENV HOSTNAME "0.0.0.0"
CMD ["node", ".output/server/index.mjs"]
```

- Imagen final liviana (solo Node.js, sin dependencias de build)
- Sirve `.output/server/index.mjs` generado por Quasar
- Escucha en todas las interfaces (`0.0.0.0`)

## Docker Compose

El proyecto usa `compose.yaml` desde la raíz del monorepo. Overrides para desarrollo y producción.

## Notas

- **npm vs pnpm**: El package manager declarado es `pnpm@10.33.0` pero Docker usa `npm` porque el `.npmrc` tiene `shamefully-hoist=true` y `package-lock.json` existe junto a `pnpm-lock.yaml`.
- El build de Quasar genera una salida SSR (`node .output/server/index.mjs`) aunque la app es SPA — Quasar genera un servidor Node mínimo para servir la SPA.
