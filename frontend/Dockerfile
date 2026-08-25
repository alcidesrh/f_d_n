#syntax=docker/dockerfile:1.4

# Versions
FROM oven/bun:1-alpine AS bun_upstream

# Base stage
FROM bun_upstream AS base

WORKDIR /frontend

# Development image
FROM base AS dev

# curl para el healthcheck de compose
RUN apk add --no-cache curl

EXPOSE 9000
ENV PORT 9000
ENV HOSTNAME 0.0.0.0

# node_modules se resuelve vía bind-mount de compose.override.yaml
CMD ["sh", "-c", "bun install && bun run dev"]

# Build stage
FROM base AS builder

# Copiar lockfile y manifest antes que el código para cachear capas
COPY bun.lock package.json ./

# Instalar dependencias (incluye dev: vue-tsc, vite)
RUN bun install --frozen-lockfile

# Copiar el resto del proyecto
COPY . .

# Build (vue-tsc --noEmit && vite build) -> dist/
RUN bun run build

# Production image
FROM bun_upstream AS prod

WORKDIR /frontend

ENV NODE_ENV production
ENV PORT 9000
ENV HOSTNAME 0.0.0.0

COPY --from=builder /frontend/dist ./dist
COPY server.ts ./

# curl para el healthcheck de compose (antes de bajar a USER bun)
RUN apk add --no-cache curl

EXPOSE 9000

# Ejecutar como usuario no root (ya definido en la imagen de bun)
USER bun

CMD ["bun", "run", "server.ts"]
