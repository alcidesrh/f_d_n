# AGENTS.md

Guía para asistentes IA que trabajan en este repo.

---

## Project identity

**Transporte Fuentes del Norte** — Sistema de venta de pasajes de buses.

**Descripcion**: Un grupo de empresas brindan un servicio de buses para el transporte de pasajeros en trayectos largos, medianos y cortos. También proveen un servicio secundario de envío de paquetes o encomiendas que son recibidas y entregadas siempre en las estaciones de los trayectos. Los trayectos pueden ser itinerarios exclusivos de una empresa o pueden estar compartido en cuyo caso las empresas involucradas se alternan los días para dar el servicio. Cada empresa gestiona un flujo de datos independiente propio de la empresa como las finanzas, su inventario de buses, estaciones y empleados. Sin embargo todas operan y gestionan el negocio de la misma manera y no hay procesos personalizados.

Monorepo con `backend/` (Symfony 8 + API Platform) y `frontend/` (Quasar + Vue 3).
Orquestación vía Docker Compose (`compose.yaml` + overrides).

### Domain terminology

| Concepto                                | Definición                                                                                                                                                                                                                                                                       | Evitar                  |
| --------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------- |
| **Enclave**                             | Ubicacion geografica de interes para el negocio. Inmuebles, sitios y emplazamientos geolocalizables relacionados con el negocio.                                                                                                                                                 | lugar, punto            |
| **Estacion**                            | Enclaves que cumplen diferentes funciones como venta de boletos en taquilla, salida o destino de los buses y demás.                                                                                                                                                              | terminal                |
| **Parada**                              | Enclave dentro de un trayecto donde temporalmente el bus detiene la marcha. Ya sea para bajar o abordar pasajeros o abastecer combustible.                                                                                                                                       | punto de parada         |
| **Trayecto**                            | Tramos delimitados por dos enclaves. Un trayecto puede estar contenido parcial o totalmente dentro de otro trayecto. Los trayectos son vectoriales en el sentido matematico, quiere decir que si se tiene dos enclave A y B los trayectos A->B y B->A son en escencia distintos. | ruta, recorrido         |
| **Subtrayecto**                         | Un segmento de un trayecto compuesto: un trayecto hijo con posición dentro de su trayecto padre.                                                                                                                                                                                 |                         |
| **Bus**                                 | Vehículo de la flota con disposición de asientos                                                                                                                                                                                                                                 | unidad, vehículo        |
| **Piloto**                              | Conductor asignado a un itinerario                                                                                                                                                                                                                                               | conductor, chofer       |
| **Itinerario**                          | Salida concreta: trayecto + fecha + bus + piloto                                                                                                                                                                                                                                 | salida, servicio, viaje |
| **Asiento**                             | Asiento de bus. Estan enumerados y tienen coordenadas con respecto al bus de su ubicacion. Hay dos clase A y B segun sus prestaciones siendo B la de mejor confort y mas cara.                                                                                                   | boleto, ticket          |
| **BoletoAsiento**                       | Asiento vendido para un trayecto.                                                                                                                                                                                                                                                | boleto, ticket          |
| **BoletoVenta**                         | Agrupa boletos, usuario quien lo emite, cliente, factura tributaria.                                                                                                                                                                                                             | venta, tiquetera        |
| **BoletoTarifa**                        | Precio del BoletoAsiento. La tarifa se decide por la que mayor cantidad de atributos iguales tenga una BoletoTarifa con el entorno de un BoletoAsiento: empresa, trayecto, hora, clase de asiento, bus.                                                                          | tarifa, precio          |
| **Factura**                             | Documento fiscal con snapshot inmutable de emisor/receptor                                                                                                                                                                                                                       | recibo, comprobante     |
| **Cliente**                             | Persona que compra un boleto. Son los pasajeros.                                                                                                                                                                                                                                 | pasajero, comprador     |
| **Empresa**                             | Línea transportista dueña de la operación                                                                                                                                                                                                                                        | compañía, operador      |
| **Encomienda**                          | se refiere a una solicitud aceptada y registrada del servicio de paqueteria                                                                                                                                                                                                      | paquete, envio          |
| **Voucher**                             | Es un boleto o encomienda que no se cobra. La razon puede ser desde desicion administrativa hasta restitucion de un por un viaje cancelado.                                                                                                                                      |                         |
| **Reasignacion**                        | Cambio de asiento                                                                                                                                                                                                                                                                |                         |
| **Anulacion**                           | Cuando se invalida la compra de un asiento asi como su registro en la oficina tributaria.                                                                                                                                                                                        |                         |
| **Usuario**                             | Empleado de alguna empresa. Usan el sistema para la venta de boletos o encomiendas, crean el calendarios de recorrido, generan reportes y demas procesos del negocio segun el rol asignado                                                                                       |                         |
| **Agencia**                             | Un tipo de usuario que representa una entidad externa asociada a una empresa. La diferencia es que solo estan limitado a la venta de boletos.                                                                                                                                    |                         |
| **Manifiesto<de pasajero, de venta, …** | son reportes que se generan en formato pdf                                                                                                                                                                                                                                       |                         |

---

Despliegue

| Servicio   | Tecnología                                                          | Puerto |
| ---------- | ------------------------------------------------------------------- | ------ |
| `backend`  | FrankenPHP/Caddy, PHP 8.4, Symfony 8, API Platform (REST + GraphQL) | 80/443 |
| `frontend` | Quasar, Vue 3, Vite 8, PrimeVue 4, FormKit 2                        | 9000   |
| `database` | PostgreSQL 16                                                       | 5432   |

- Dos entity managers: PostgreSQL (principal) + SQL Server (legacy).
- IAM: Flat Permission Set con Symfony Voters + PermissionManager.
- Mercure integrado en Caddy para real-time.

---

---

## Quick commands

### Full stack (desde raíz)

| Comando               | Descripción                     |
| --------------------- | ------------------------------- |
| `make dev`            | Levantar stack dev              |
| `make debug`          | Con Xdebug                      |
| `make b`              | Rebuild imágenes                |
| `make d`              | Parar y remover contenedores    |
| `make sh`             | Shell en backend                |
| `make logs`           | Logs en vivo                    |
| `make migrate`        | Ejecutar migraciones pendientes |
| `make migration`      | Crear migración desde diff      |
| `make test`           | PHPUnit                         |
| `make testf F="name"` | Test por filtro                 |

### Frontend (en `frontend/`)

| Comando             | Descripción              |
| ------------------- | ------------------------ |
| `npm run dev`       | Dev server (puerto 9000) |
| `npm run build`     | type-check + build       |
| `npm run lint`      | oxlint + eslint --fix    |
| `npm run test:unit` | Vitest                   |
| `npm run test:e2e`  | Playwright               |

### Documentación (desde raíz)

| Comando             | Descripción                                        |
| ------------------- | -------------------------------------------------- |
| `make docs-serve`   | MkDocs en localhost:8000                           |
| `make docs-build`   | Generar site estático                              |
| `make docs-gen-all` | Regenerar docs automáticas (ERD, entity-map, etc.) |

---

#### Architecture critical facts

- **GraphQL collections** son `PageConnection` hulls, no arrays: `{ collection { id } paginationInfo { totalCount } }`.
- **REST** requiere `Accept: application/ld+json` (sin header → 406).
- **CRUD dinámico**: backend expone metadatos → frontend genera forms/listas via introspection GraphQL.
- **Boot order frontend**: unocss → api-rest → apollo → introspection → middleware → i18n → gsap.
- **Stores frontend**: en getters **no usar** `state` como parámetro (collision con auto-import). Usar `st`.

---

## Agent behavior rules

1. **Leer antes de modificar.** Explorar código existente, patrones y ADRs antes de cambiar.
2. **Cambios mínimos.** Preferir ediciones quirúrgicas sobre rewrites. No refactorizar código funcional sin razón.
3. **Preservar compatibilidad.** No romper interfaces existentes.
4. **Seguridad.** No commitear secrets, `.env.local`, passwords o JWT secrets. Todo input externo es untrusted.
5. **Verificar.** Tests, GraphQL schema válido, PHPStan (backend), lint (frontend).
6. **Lenguaje del dominio.** Usar terminología exacta de `CONTEXT.md` en títulos, commits y propuestas.
7. **No inventar dependencias.** Evitar nuevas sin justificación explícita.
8. **Explicar tradeoffs** cuando una decisión tiene implicaciones arquitectónicas.

---

## Files requiring extra caution

- `compose.yaml` / `compose.prod.yaml` / `compose.override.yaml`
- `.env` / `.env.local`
- `config/packages/*` / `config/services.yaml`
- `migrations/*`
- GraphQL schema definitions
- `frontend/src/entities.ts` / `frontend/src/types/entities/*`
- `CONTEXT.md`

---

## Documentation map

| Área                | Ruta                                          |
| ------------------- | --------------------------------------------- |
| Contexto de dominio | `CONTEXT.md`                                  |
| Arquitectura        | `docs/docs/architecture/overview.md`          |
| ADRs                | `docs/docs/architecture/decisions/`           |
| Docker              | `docs/docs/docker/overview.md`                |
| Backend             | `docs/docs/backend/architecture/overview.md`  |
| IAM                 | `docs/docs/backend/iam/overview.md`           |
| Base de datos       | `docs/docs/backend/database/overview.md`      |
| Migración legacy    | `docs/docs/backend/migration/overview.md`     |
| Subdominios         | `docs/docs/backend/subdomains/overview.md`    |
| Frontend            | `docs/docs/frontend/architecture/overview.md` |
| Glosario            | `docs/docs/glossary.md`                       |
| Agent skills        | `docs/agents/`                                |

### Sub-AGENTS.md

Para reglas detalladas de cada capa:

- **Backend**: `backend/AGENTS.md` — Symfony, Doctrine, GraphQL, testing, security, checklist.
- **Frontend**: `frontend/AGENTS.md` — Stack, convenciones Vue, auto-imports, caveats.

---

## Practical caveats

- Los Makefiles (root + backend) incluyen targets legacy que pueden no estar disponibles.
- Docker usa **npm**, no pnpm ni bun (aunque existan lockfiles de ambos).
- Migración completa desde TerminalOmnibus toma horas (6679 salidas).
- Debug: `make debug` usa Caddyfile.dev sin workers.
- `node_modules/.vite` puede quedar root-owned tras build del contenedor → `rm -rf node_modules/.vite`.
- Auto-imports generan `auto-imports.d.ts` y `components.d.ts` — no editar a mano.
