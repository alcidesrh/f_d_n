# AGENTS.md

Guía para asistentes IA que trabajan en este repo.

---

## Project identity

**Transporte Fuentes del Norte** — Sistema de venta de pasajes de buses.

**Descripcion**: Un grupo de empresas brindan un servicio de buses para el transporte de pasajeros en trayectos largos, medianos y cortos. También proveen un servicio secundario de envío de paquetes o encomiendas que son recibidas y entregadas siempre en las estaciones de los trayectos. Los trayectos pueden ser itinerarios exclusivos de una empresa o pueden estar compartido en cuyo caso las empresas involucradas se alternan los días para dar el servicio. Cada empresa gestiona un flujo de datos independiente propio de la empresa como las finanzas, su inventario de buses, estaciones y empleados. Sin embargo todas operan y gestionan el negocio de la misma manera y no hay procesos personalizados.

Monorepo con `backend/` (Symfony 8 + API Platform) y `frontend/` (Quasar + Vue 3).
Orquestación vía Docker Compose (`compose.yaml` + overrides).

### Domain terminology

Columna **Modelo**: `nuevo` = entidad viva en `backend/src/Entity/`; `legacy` = solo existe en `backend/src/EntitySistemaFdn/` (sistema TerminalOmnibus), sin equivalente todavía en el modelo nuevo; `concepto` = término de negocio vigente sin entidad dedicada (absorbido por otra entidad o pendiente de modelar).

| Concepto                                | Definición                                                                                                                                                                                                                                                                       | Evitar                  | Modelo |
| --------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------- | ------ |
| **Enclave**                             | Ubicacion geografica de interes para el negocio. Inmuebles, sitios y emplazamientos geolocalizables relacionados con el negocio.                                                                                                                                                 | lugar, punto            | nuevo |
| **Estacion**                            | Enclaves que cumplen diferentes funciones como venta de boletos en taquilla, salida o destino de los buses y demás.                                                                                                                                                              | terminal                | nuevo |
| **Parada**                              | Enclave dentro de un trayecto donde temporalmente el bus detiene la marcha. Ya sea para bajar o abordar pasajeros o abastecer combustible.                                                                                                                                       | punto de parada         | concepto — hoy `Enclave` solo distingue `Enclave`/`Estacion` (discriminator map), sin subtipo `Parada` |
| **Trayecto**                            | Tramos delimitados por dos enclaves. Un trayecto puede estar contenido parcial o totalmente dentro de otro trayecto. Los trayectos son vectoriales en el sentido matematico, quiere decir que si se tiene dos enclave A y B los trayectos A->B y B->A son en escencia distintos. Único por par `(origen, destino)`. | ruta, recorrido         | nuevo |
| **Subtrayecto**                         | Un segmento de un trayecto compuesto: un trayecto hijo (`Trayecto`) con posición dentro de su trayecto padre (`belowTo`).                                                                                                                                                        |                         | nuevo |
| **Bus**                                 | Vehículo de la flota con disposición de asientos. Tiene `piloto` y `copiloto` (tripulación habitual asignada al bus, ambos `ManyToOne` a `Piloto`; `copiloto` se llamó `pilotoAux` hasta ADR-016).                                                                              | unidad, vehículo, pilotoAux | nuevo |
| **Piloto**                              | Conductor (o copiloto) asignado a un bus. No tiene relación con `Recorrido` (ver ADR-016).                                                                                                                                                                                       | conductor, chofer       | nuevo |
| **Recorrido**                           | Salida concreta: trayecto + fecha + bus + lista de BoletoAsiento vendidos. Es el "recorrido" del glosario de negocio. Nombre de clase renombrado desde `Servicio` (ver ADR-013); no confundir con el `Recorrido`-plantilla, ya eliminado (ver ADR-011). Sin relación con `Piloto` — la tripulación es del `Bus` (ver ADR-016). Estado tipado con enum `EstadoRecorrido`: `programada → abordando → iniciada → finalizada` (lineal), `cancelada` solo desde `programada` (ver ADR-014).                                                                        | servicio, itinerario, salida | nuevo |
| **Asiento**                             | Asiento de bus. Estan enumerados y tienen coordenadas con respecto al bus de su ubicacion. Hay dos clase A y B segun sus prestaciones siendo B la de mejor confort y mas cara.                                                                                                   | boleto, ticket          | nuevo |
| **BoletoAsiento**                       | Asiento vendido para un trayecto (puede ser un subtramo del trayecto completo del recorrido) dentro de un recorrido determinado. Constraint única `(asiento_id, trayecto_id, recorrido_id)`: el mismo asiento puede venderse dos veces en el mismo recorrido solo si es para trayectos (subtramos) distintos. Estado tipado con enum `EstadoBoletoAsiento`: `emitido → chequeado → transito → finalizado` (lineal); `anulado`/`reasignado` solo alcanzables desde `emitido` (ver ADR-014).           | boleto, ticket          | nuevo |
| **BoletoVenta**                         | Agrupa boletos, usuario quien lo emite, cliente, factura tributaria.                                                                                                                                                                                                             | venta, tiquetera        | nuevo |
| **BoletoTarifa**                        | Precio de referencia de un BoletoAsiento. La tarifa se decide por la que mayor cantidad de atributos iguales tenga una BoletoTarifa con el entorno de un BoletoAsiento: empresa, trayecto, hora, clase de asiento, bus. **El resolver de especificidad aún no está implementado en código** — hoy `BoletoTarifa` solo se usa desde el migrador legacy. | tarifa, precio          | nuevo (sin lógica de resolución) |
| **Factura**                             | Documento fiscal con snapshot inmutable de emisor/receptor                                                                                                                                                                                                                       | recibo, comprobante     | nuevo |
| **Cliente**                             | Persona que compra un boleto. Son los pasajeros.                                                                                                                                                                                                                                 | pasajero, comprador     | nuevo |
| **Empresa**                             | Línea transportista dueña de la operación                                                                                                                                                                                                                                        | compañía, operador      | nuevo |
| **Encomienda**                          | se refiere a una solicitud aceptada y registrada del servicio de paqueteria                                                                                                                                                                                                      | paquete, envio          | legacy |
| **Voucher**                             | Es un boleto o encomienda que no se cobra. La razon puede ser desde desicion administrativa hasta restitucion de un por un viaje cancelado.                                                                                                                                      |                         | legacy |
| **Reasignacion**                        | Cambio de asiento                                                                                                                                                                                                                                                                |                         | legacy (sin operación de dominio equivalente hoy) |
| **Anulacion**                           | Cuando se invalida la compra de un asiento asi como su registro en la oficina tributaria.                                                                                                                                                                                        |                         | legacy (sin operación de dominio equivalente hoy) |
| **Usuario**                             | Empleado de alguna empresa. Usan el sistema para la venta de boletos o encomiendas, crean el calendarios de recorrido, generan reportes y demas procesos del negocio segun el rol asignado                                                                                       |                         | nuevo |
| **Agencia**                             | Un tipo de usuario que representa una entidad externa asociada a una empresa. La diferencia es que solo estan limitado a la venta de boletos.                                                                                                                                    |                         | legacy |
| **Manifiesto\<de pasajero, de venta, ...\>** | son reportes que se generan en formato pdf                                                                                                                                                                                                                                  |                         | legacy |

> **Estado del modelo (2026-09):** el modelo nuevo (`src/Entity/`, ~30 clases) cubre geografía, flota y venta básica de asientos. `Agencia`, `Voucher`, `Encomienda`, `Reasignación`, `Anulación` y `Manifiesto` solo existen en el legacy (`src/EntitySistemaFdn/`, ~112 clases) — son el trabajo de dominio pendiente, no features ya resueltas. No asumas que existe un endpoint/servicio para ellas sin verificarlo primero.

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

---

#### Architecture critical facts

- **GraphQL collections** son `PageConnection` hulls, no arrays: `{ collection { id } paginationInfo { totalCount } }`.
- **REST** requiere `Accept: application/ld+json` (sin header → 406).
- **CRUD dinámico**: backend expone metadatos → frontend genera forms/listas via introspection GraphQL.
- **Boot order frontend**: unocss → api-rest → apollo → introspection → middleware → i18n → gsap.
- **Stores frontend**: en getters **no usar** `state` como parámetro (collision con auto-import). Usar `st`.
- **Íconos**: el repositorio de íconos es [Tabler](https://tabler.io/icons) y el único punto de uso es `frontend/src/components/common/Icon.vue` (`<icon name="grip-vertical" lg />`, prefijo `tabler:` implícito). Ver `docs/frontend/icons.md`.
- **Multi-tenancy**: `App\Doctrine\TenantFilter` (Doctrine SQLFilter, deshabilitado por defecto en el EM `default`) aísla `Bus`/`Piloto`/`Recorrido`/`BoletoTarifa` por `empresa_id`. Se habilita por request en `App\EventListener\TenantFilterListener` según `Usuario.empresa` — si el usuario no tiene empresa asignada, navega sin filtro. Ver ADR-015.

---

## Agent behavior rules

1. **Leer antes de modificar.** Explorar código existente, patrones y ADRs antes de cambiar.
2. **Cambios mínimos.** Preferir ediciones quirúrgicas sobre rewrites. No refactorizar código funcional sin razón.
3. **Preservar compatibilidad.** No romper interfaces existentes.
4. **Seguridad.** No commitear secrets, `.env.local`, passwords o JWT secrets. Todo input externo es untrusted.
5. **Verificar.** Tests, GraphQL schema válido, lint (frontend). `phpstan/phpstan` **no está instalado** en `backend/composer.json` (solo la dependencia transitiva `phpstan/phpdoc-parser`) — no asumas que hay static analysis corriendo en backend hasta que se agregue.
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

No existe un sitio MkDocs — se eliminó el 2026-09 por documentar un modelo de datos obsoleto (`Recorrido`, `Boleto`, `Venta`, `Parada`, `RecorridoMatrioska`). La documentación vigente es solo esta:

| Área                | Ruta                                    |
| ------------------- | ---------------------------------------- |
| Contexto de dominio | `CONTEXT.md`                             |
| Terminología + estado del modelo | `AGENTS.md` (este archivo, sección "Domain terminology") |
| ADRs (decisiones de arquitectura) | `docs/architecture/decisions/` (ADR-001 a ADR-016, ver `index.md`) |
| Convención de exploración de dominio para skills | `docs/agents/domain.md` |
| Convención de issue tracker | `docs/agents/issue-tracker.md` |
| Backend (Symfony, Doctrine, GraphQL) | `backend/AGENTS.md` |
| Frontend (Quasar, Vue, stores) | `frontend/AGENTS.md` |

Si necesitas documentación de un tema que no está en esta lista (ERD, mapa de entidades por subdominio, guía de performance, etc.), **no asumas que existe en `docs/`** — verifícalo primero; probablemente haya que escribirla desde cero contra el estado actual del código.

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
