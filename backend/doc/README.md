# Documentación del Backend

La documentación técnica del backend (Symfony 8 + API Platform) está centralizada en el directorio `docs/` de la raíz del monorepo:

```
docs/docs/backend/
├── architecture/     # API Platform, Doctrine Dual EM, Messenger, Mercure
├── iam/              # Voters, PermissionManager, API Tokens, Matriz
├── database/         # Entity map, ERD, migraciones, legacy SQL Server, modelado
├── migration/        # Pipeline completo desde TerminalOmnibus
├── subdomains/       # Bounded contexts: transporte, flota, venta, seguridad, etc.
├── commands/         # Comandos Symfony: migración, sincronización, fixtures
├── graphql/          # Resolvers, schema, N+1 prevention
├── testing/          # PHPUnit, fixtures, integración
└── performance/      # Caching, queries, profiling
```

Para servir la documentación:

```bash
# Desde la raíz del monorepo
make docs-serve

# O directamente
mkdocs serve -f docs/mkdocs.yml
```

## Enlaces Rápidos

- [Arquitectura del Backend](../../docs/docs/backend/architecture/overview.md)
- [IAM - Sistema de Permisos](../../docs/docs/backend/iam/overview.md)
- [Base de Datos y ERD](../../docs/docs/backend/database/overview.md)
- [Subdominios](../../docs/docs/backend/subdomains/overview.md)
