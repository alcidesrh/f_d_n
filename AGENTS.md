# AGENTS.md

Guía para asistentes IA que trabajan en este repo.

---

## Project identity

**Transporte Fuentes del Norte** — Sistema de venta de pasajes de buses.

**Descripcion**: Un grupo de empresas brindan un servicio de buses para el transporte de pasajeros en trayectos largos, medianos y cortos. También proveen un servicio secundario de envío de paquetes o encomiendas que son recibidas y entregadas siempre en las estaciones de los trayectos. Los trayectos pueden ser itinerarios exclusivos de una empresa o pueden estar compartido en cuyo caso las empresas involucradas se alternan los días para dar el servicio. Cada empresa gestiona un flujo de datos independiente propio de la empresa como las finanzas, su inventario de buses, estaciones y empleados. Sin embargo todas operan y gestionan el negocio de la misma manera y no hay procesos personalizados.

Monorepo con `backend/` (Symfony 8 + API Platform), `frontend/` (Vue 3 + Vite) y `pagina/` (página web pública de venta de boletos, Vue 3; se sirve desde el backend en `/pagina`).
Orquestación vía Docker Compose (`compose.yaml` + overrides).

### Domain terminology

Las definiciones del dominio, los términos a evitar y el modelo de cada uno están en `CONTEXT.md`. Revísalo antes de nombrar algo nuevo.

> **Estado del modelo (2026-10):** el modelo nuevo (`src/Entity/`, ~45 clases) cubre geografía, flota, la venta de asientos por taquilla, agencias y página web (ADR-021: `App\Venta`) y las encomiendas (ADR-035: `App\Encomienda`; las del legado se migran con la salida en que viajaron, conservando su id/código de barras; las que nunca se embarcaron, no). Los manifiestos de boletos distintos del interno y del piloto solo existen en el legacy (`src/EntitySistemaFdn/`, ~112 clases) — son el trabajo de dominio pendiente, no features ya resueltas. No asumas que existe un endpoint/servicio para ellas sin verificarlo primero. La factura electrónica usa **Forcon** (`App\Venta\Facturacion\Forcon`, `FEL_CERTIFICADOR=forcon`, credenciales por empresa con `app:fel:credencial`; docs en `docs/certificador_factura_electronica/`) o el simulado en desarrollo. El pago con tarjeta usa **Cybersource** con 3-D Secure (`App\Venta\Pago\Cybersource`, `PAGO_PASARELA=cybersource`, comercio por empresa con `app:pago:credencial`) o la pasarela simulada en desarrollo.

---

Despliegue

| Servicio   | Tecnología                                                          | Puerto |
| ---------- | ------------------------------------------------------------------- | ------ |
| `backend`  | FrankenPHP/Caddy, PHP 8.4, Symfony 8, API Platform (REST + GraphQL) | 80/443 |
| `frontend` | Vue 3, Vite 8, PrimeVue 4, FormKit 2                                | 9000   |
| `pagina/` (no es servicio) | Vue 3, PrimeVue 4, FormKit 2, Tailwind 4; se compila en `backend/public/pagina` y la sirve el backend en `/pagina` | (dev 9100) |
| `database` | PostgreSQL 16                                                       | 5432   |

- Dos entity managers: PostgreSQL (principal) + SQL Server (legacy).
- Accesos (ADR-039): capacidades en el código, roles planos en la base y asignaciones con alcance por empresa y estación; `App\Acceso\CapacidadVoter` + `Autorizacion`; denegar por defecto en la API genérica.
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
| `make e2e-usuario`    | Crea o reinicia la cuenta de pruebas y escribe `.env.e2e.local` (solo desarrollo) |
| `make -s e2e-token`   | Token Bearer de la cuenta de pruebas para `curl` |

### Página web (en `pagina/`)

| Comando             | Descripción |
| ------------------- | ----------- |
| `npm run dev`       | Dev server en 9100 bajo `/pagina/`, proxy de `/api` a `PAGINA_BACKEND` (por defecto `https://localhost`) |
| `npm run build`     | type-check + build a `backend/public/pagina` + prerender por idioma y `sitemap.xml` (en producción: `make pagina`, o `make deploy` si cambió) |
| `npm run test:unit` | Vitest |

### Frontend (en `frontend/`)

| Comando             | Descripción              |
| ------------------- | ------------------------ |
| `npm run dev`       | Dev server (puerto 9000) |
| `npm run build`     | type-check + build       |
| `npm run lint`      | oxlint + eslint --fix    |
| `npm run test:unit` | Vitest                   |
| `npm run test:e2e`  | Playwright (con el stack levantado) |

---

#### Architecture critical facts

- **GraphQL collections** son `PageConnection` hulls, no arrays: `{ collection { id } paginationInfo { totalCount } }`.
- **REST** requiere `Accept: application/ld+json` (sin header → 406).
- **Frontend por capas** (ADR-017): `app → features → shared → core`, imports explícitos. Mapa completo en `frontend/AGENTS.md`.
- **`label`** es derivado (de `nombre`/`name`) y de solo lectura en todas las entidades: no forma parte de los inputs de create/update.
- **Tokens de API**: el valor del Bearer solo lo entrega `POST /api/login`; nunca se expone por GraphQL/REST. `POST /api/logout` lo revoca (`App\EventListener\LogoutListener`).

Detalle por tema (mismo texto, en `docs/architecture/`):

- Ventas, salidas, encomiendas, abordaje, reportes y agencias: `docs/architecture/ventas-y-operacion.md`
- Accesos, multi-tenancy, autorizaciones, listados, menús, metadata, administración, chat y sincronización: `docs/architecture/plataforma.md`


### Probar contra el stack (navegador, Playwright, `curl`)

Hay una cuenta solo para pruebas en desarrollo (`pruebas`, rol de sistema sobre todo; no es `admin`). Sus credenciales viven en `.env.e2e.local` en la raíz (fuera de git; plantilla en `.env.e2e.example`): `E2E_BASE_URL`, `E2E_ADMIN_USER`, `E2E_ADMIN_PASSWORD`.

- **Si falta el archivo** o el login falla (p. ej. tras `make reset-db`): `make e2e-usuario` la crea o le pone otra contraseña. El comando (`app:usuario:pruebas`) no hace nada en producción.
- **Navegador** (panel de Claude Desktop, Chrome): entrar en `E2E_BASE_URL/login` con esas credenciales.
- **Consola**: `curl -H "Authorization: Bearer $(make -s e2e-token)" http://localhost/graphql …` (REST además con `Accept: application/ld+json`).
- **Playwright** (`npm run test:e2e` en `frontend/`) lee el archivo solo; lo que venga en el entorno tiene prioridad.
- Nunca mostrar la contraseña ni el token en la salida, en commits ni en mensajes.

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
- `config/services.yaml`
- `migrations/*`
- GraphQL schema definitions
- `frontend/src/core/entities/schema.ts` (`SCHEMA_VERSION`: subirla si cambia el formato de la copia guardada; los cambios del schema llegan solos por revisión, ADR-036) / `frontend/src/core/http.ts` / `frontend/src/core/graphql/client.ts`
- `backend/src/Entity/Base/Base.php`
- `CONTEXT.md`

---

## Documentation map

No existe un sitio MkDocs — se eliminó el 2026-09 por documentar un modelo de datos obsoleto (`Recorrido`, `Boleto`, `Venta`, `Parada`, `RecorridoMatrioska`). La documentación vigente es solo esta:

| Área                | Ruta                                    |
| ------------------- | ---------------------------------------- |
| Contexto de dominio | `CONTEXT.md`                             |
| Terminología + modelo de cada término | `CONTEXT.md` |
| Referencia funcional (ventas y operación) | `docs/architecture/ventas-y-operacion.md` |
| Referencia funcional (plataforma) | `docs/architecture/plataforma.md` |
| ADRs (decisiones de arquitectura) | `docs/architecture/decisions/` (ADR-001 a ADR-046, ver `index.md`) |
| Convención de exploración de dominio para skills | `docs/agents/domain.md` |
| Convención de issue tracker | `docs/agents/issue-tracker.md` |
| Backend (Symfony, Doctrine, GraphQL) | `backend/AGENTS.md` |
| Frontend (Vue, Vite, stores) | `frontend/AGENTS.md` |
| Página web pública | `pagina/AGENTS.md` |
| Responsive (breakpoints, patrones mobile-first) | `docs/frontend/responsive.md` |

Si necesitas documentación de un tema que no está en esta lista (ERD, mapa de entidades por subdominio, guía de performance, etc.), **no asumas que existe en `docs/`** — verifícalo primero; probablemente haya que escribirla desde cero contra el estado actual del código.

### Sub-AGENTS.md

Para reglas detalladas de cada capa:

- **Backend**: `backend/AGENTS.md` — Symfony, Doctrine, GraphQL, testing, security, checklist.
- **Frontend**: `frontend/AGENTS.md` — Stack, convenciones Vue, auto-imports, caveats.

---

## Practical caveats

- Los Makefiles (root + backend) incluyen targets legacy que pueden no estar disponibles.
- **Producción (VPS, `compose.prod.yaml`)**: el código no va en la imagen; se monta desde el checkout `/root/f_d_n` (`./backend:/app`, `./frontend` con su `dist/`), y `/app/var` es tmpfs (la caché de Symfony nace en cada arranque). Cambio de PHP → `make reiniciar` (unos segundos); de frontend → `make front` (`vite build` en el host, sin reiniciar); de la página → `make pagina`; de `composer.lock` → `make vendor`. `make deploy` hace `git pull` y solo lo que cambió. Reconstruir imágenes (`make imagen`) solo si cambia un Dockerfile o una extensión de PHP.
- **Alternar dev/prod en la VPS**: mismos contenedores y misma base, uno u otro. `make vps` pasa a dev (por la IP, `https://194.113.67.177`, Vite con recarga en caliente) y `make prod` vuelve; `make modo` dice cuál corre. Las imágenes de dev llevan el prefijo `dev-` y no pisan las de prod (`make vps-build` si cambia un Dockerfile). Al volver a prod, `make front` / `make pagina` si cambiaste el frontend o la página.
- Docker usa **npm**, no pnpm ni bun (aunque existan lockfiles de ambos).
- Migración completa desde TerminalOmnibus toma horas (6679 salidas).
- Debug: `make debug` usa Caddyfile.dev sin workers.
- Segundo plano (ADR-044), tres servicios de Compose con la imagen del backend:
  - `programador` (`messenger:consume scheduler_fdn`): el horario de las tareas. Solo reenvía.
  - `worker` (`messenger:consume async tareas`, se reinicia cada hora): correos, push y las tareas del programador.
  - `worker-pesadas` (`pesadas`): un mensaje por esquema automático o por destinatario de reporte.
  - En desarrollo, tras cambiar un handler: `docker compose restart worker worker-pesadas programador`.
  - `make tareas` muestra el latido de cada tarea; `make tareas t=<código>` corre una ya.
  - Lo de `async` y `pesadas` que agota sus reintentos queda en `failed` (`messenger:failed:show`). Una tarea del programador que falla no se reintenta: lo intenta la próxima corrida.
- `node_modules/.vite` puede quedar root-owned tras build del contenedor → `rm -rf node_modules/.vite`.
- Auto-imports generan `auto-imports.d.ts` y `components.d.ts` — no editar a mano.
