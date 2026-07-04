# Diagramas C4

> Los diagramas C4 (Context, Container, Component, Code) modelan la arquitectura de software en 4 niveles de abstracción, creados por Simon Brown.

## Niveles documentados

### Nivel 1 — Contexto

El [diagrama de contexto](../overview.md#vista-contexto-c4-nivel-1) muestra el sistema FDN Transportes como una caja negra, sus usuarios (empleados, clientes) y los sistemas externos con los que se relaciona (PostgreSQL, SQL Server legacy, Mercure).

**Audiencia:** stakeholders técnicos y no técnicos.

### Nivel 2 — Contenedores

El [diagrama de contenedores](../overview.md#vista-contenedores-c4-nivel-2) descompone el sistema en sus contenedores de alto nivel: Frontend SPA (Quasar), Backend API (FrankenPHP/Symfony) y Base de Datos (PostgreSQL). Muestra las responsabilidades de cada contenedor y los protocolos de comunicación.

**Audiencia:** desarrolladores, DevOps, arquitectos.

### Niveles 3 y 4 — Componentes y Código

Los diagramas de componentes y código se documentan por subdominio en las secciones específicas:

| Subdominio | Enlace |
|---|---|
| Backend — IAM | `../backend/iam/overview.md` |
| Backend — Base de Datos | `../backend/database/overview.md` |
| Backend — Subdominios | `../backend/subdomains/overview/index.md` |
| Frontend — Arquitectura | `../frontend/architecture/overview.md` |
| Frontend — Stores | `../frontend/stores/overview.md` |

## Convenciones

Los diagramas C4 en este proyecto siguen estas convenciones:

- **Personas**: actores humanos (usuarios, clientes)
- **Sistemas**: límites del sistema FDN
- **Contenedores**: aplicaciones ejecutables (SPA, API, BD)
- **Componentes**: módulos dentro de un contenedor (servicios, bundles, stores)
- Las flechas indican dirección de la comunicación
- El protocolo se etiqueta en la relación (GraphQL, REST, SSE, TCP)

## Herramientas

Los diagramas se escriben en sintaxis **Mermaid** dentro de archivos Markdown. Se renderizan automáticamente en la documentación mediante el plugin `mermaid2` de MkDocs.

```bash
# Servir documentación con diagramas
make docs-serve

# Validar estructura de documentación
make docs-validate
```
