# Documentación del Frontend

La documentación técnica del frontend (Quasar + Vue 3 + Pinia) está centralizada en el directorio `docs/` de la raíz del monorepo:

```
docs/docs/frontend/
├── architecture/     # Arquitectura, boot sequence, dynamic CRUD, data layer, routing
├── patterns/         # Patrones de diseño: composables, formkit, unocss, i18n
├── modules/          # Subdominios: transporte, flota, venta, personal, etc.
├── components/       # Catálogo de componentes con props, slots, eventos
├── stores/           # Store factory, autoimport, session, schema
├── graphql/          # Apollo Client, links, queries
├── styling/          # UnoCSS, colores, reglas custom
└── build-deploy/     # Docker, Vite, producción
```

Para servir la documentación:

```bash
# Desde la raíz del monorepo
make docs-serve

# O directamente
mkdocs serve -f docs/mkdocs.yml
```

## Enlaces Rápidos

- [Arquitectura del Frontend](../../docs/docs/frontend/architecture/overview.md)
- [Dynamic CRUD](../../docs/docs/frontend/architecture/dynamic-crud.md)
- [Patrones de Diseño](../../docs/docs/frontend/patterns/overview.md)
- [Subdominios/Módulos](../../docs/docs/frontend/modules/overview.md)
