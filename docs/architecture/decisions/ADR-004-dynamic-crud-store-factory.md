# ADR-004: Dynamic CRUD via runtime store factory

**Estado:** Aceptada

## Contexto

FDN Transportes maneja más de 20 entidades de negocio con operaciones CRUD. Crear stores, vistas y rutas manualmente para cada entidad resulta en una gran cantidad de código repetitivo (boilerplate). Cada nueva entidad requeriría un store dedicado, una vista de listado, un formulario de edición y rutas específicas.

El equipo necesita una solución que permita agregar nuevas funcionalidades CRUD con mínima intervención manual, idealmente declarando la entidad y teniendo automáticamente las interfaces de listado y formulario.

## Decisión

Se implementa un sistema de **CRUD dinámico mediante store factory runtime**:

1. **`storeFactory.ts`**: recibe el nombre de una entidad y genera en runtime un store de Pinia con operaciones CRUD basadas en GraphQL (collection, item, create, update, delete).
2. **`entityRegistry.ts`**: registro central de definiciones de entidad que incluye campos, queries y mutations GraphQL asociadas.
3. **Componentes dinámicos**: `DynamicCollection.vue` renderiza listas con tabla configurable; `DynamicForm.vue` renderiza formularios basados en metadatos.
4. **Rutas dinámicas**: `/lista/:entity` y `/form/:entity/:id?` resuelven automáticamente la entidad y cargan el store correspondiente.
5. **Archivos por entidad** (`src/router/user.ts`, `src/router/role.ts`): permiten sobreescribir comportamiento dinámico para entidades que requieren lógica específica.

El backend expone metadatos de configuración de entidades via `GET /api/entity_configuration_dtos` (REST) que el frontend consume al arrancar para saber qué campos mostrar en listas y formularios.

## Consecuencias

**Positivas:**

- Reducción drástica de código repetitivo: una nueva entidad solo necesita registrarse en el registry
- Consistencia visual: todas las listas y formularios comparten el mismo sistema de componentes
- Flexibilidad: entidades específicas pueden sobrescribir el comportamiento dinámico con archivos de ruta dedicados
- Los metadatos del backend controlan la UI (campos visibles, orden, tipos de formulario)

**Negativas:**

- Mayor complejidad inicial en la implementación del factory y los componentes genéricos
- El sistema dinámico es más difícil de depurar que stores explícitos
- Personalizaciones avanzadas (vistas especiales, lógica de negocio compleja) requieren salirse del patrón dinámico
- Dependencia fuerte del contrato de metadatos entre backend y frontend
