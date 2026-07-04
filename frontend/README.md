# Frontend — FDN Transportes

SPA construida con **Quasar 2 + Vue 3 + Pinia + Apollo Client 4**.

## Stack

- Vue 3 (Composition API, `<script setup>`)
- Quasar 2 (UI framework)
- Pinia (state management)
- Apollo Client 4 (GraphQL)
- FormKit (form builder)
- UnoCSS (utility-first styling)
- TypeScript (strict mode)

## Documentación

La documentación completa del frontend está en `docs/docs/frontend/` (raíz del repo):

```bash
make docs-serve  # desde la raíz del repo
```

## Inicio rápido

```bash
npm install
npm run dev      # servidor de desarrollo en :9000
npm run build    # build producción → .output/
npm run format   # Prettier
```

## Arquitectura

Esta app utiliza un sistema **CRUD dinámico** impulsado por metadatos del backend:

- Las rutas `/lista/:entity` y `/form/:entity/:id?` renderizan componentes genéricos
- Las stores se crean en tiempo de ejecución via `storeFactory()`
- Las definiciones de entidades se registran en `entityRegistry.ts`

## Boot order

unocss → api-rest → apollo → server-response-listener → formkit → introspection → middleware → i18n → responsive → gsap
