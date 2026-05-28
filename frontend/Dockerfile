#syntax=docker/dockerfile:1.4

# Versions
FROM node:24-alpine AS node_upstream

# Base stage
FROM node_upstream AS base

RUN apk add --no-cache libc6-compat

WORKDIR /frontend

# 🔴 Eliminamos corepack + pnpm
# (npm ya viene con Node)

# Development image
FROM base as dev

EXPOSE 9000
ENV PORT 9000
ENV HOSTNAME localhost

CMD ["sh", "-c", "npm install && npm run dev"]

# Build stage
FROM base AS builder

# Copiar package.json y lock
COPY package*.json ./

# Instalar dependencias
RUN npm install --omit=dev

# Copiar el resto del proyecto
COPY . .

# Build
RUN npm run build

# Production image
FROM node_upstream AS prod

WORKDIR /frontend

ENV NODE_ENV production

COPY --from=builder /frontend/.output ./.output
COPY --from=builder /frontend/public ./public

EXPOSE 9000
ENV PORT 9000
ENV HOSTNAME "0.0.0.0"

CMD ["node", ".output/server/index.mjs"]
