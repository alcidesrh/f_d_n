# ADR-015: TenantFilter — aislamiento por empresa vía Doctrine SQLFilter

**Estado:** Aceptada

## Contexto

`Bus`, `Piloto`, `Recorrido` y `BoletoTarifa` tienen `empresa_id`, pero no existía ningún mecanismo automático que impidiera que una consulta devolviera filas de una empresa distinta a la del usuario autenticado — el aislamiento dependía enteramente de que cada repositorio/query recordara añadir el criterio de empresa. Dado que varias empresas comparten el mismo backend y pueden operar el mismo `Trayecto` (trayectos compartidos, ver glosario), un olvido de ese criterio filtra datos entre empresas.

`Usuario` no tenía relación con `Empresa` — no había forma de saber, a partir del usuario autenticado, cuál era su empresa.

## Decisión

1. Se añade `Usuario.empresa` (`ManyToOne` a `Empresa`, nullable).
2. Se implementa `App\Doctrine\TenantFilter` (Doctrine `SQLFilter`) que añade `{alias}.empresa_id = :empresaId` para una lista cerrada de entidades tenant-scoped (`Bus`, `Piloto`, `Recorrido`, `BoletoTarifa`). Cualquier otra entidad (`Enclave`, `Trayecto`, `Cliente`, `Status`, ...) no se toca — son catálogos globales compartidos entre empresas por diseño.
3. Se registra como filtro **deshabilitado por defecto** en el entity manager `default` (`config/packages/doctrine.yaml`) — nunca se aplica al entity manager `systemfdn` (legacy).
4. `App\EventListener\TenantFilterListener` habilita/deshabilita el filtro **en cada request** (`kernel.request`), según `Security::getUser()->getEmpresa()`. Esto es explícito y no un "habilitar una vez": FrankenPHP en worker mode mantiene el kernel — y con él el `EntityManager` y el estado de sus filtros — vivo entre peticiones, así que el filtro debe resetearse en cada petición o el estado de un usuario podría filtrar (o no) las consultas de la siguiente petición atendida por el mismo worker.

## Consecuencias

**Positivas:**

- Aislamiento por empresa aplicado a nivel de SQL para las entidades operativas, sin depender de que cada query lo recuerde.
- Diseño explícito sobre qué es global (geografía, catálogos) vs. qué es por empresa (operación) — documentado en la propia lista `TENANT_ENTITIES`.

**Negativas / limitaciones conocidas:**

- Un `Usuario` sin `empresa` asignada navega **sin filtro** (ve todas las empresas) — hoy no hay UI ni proceso de migración que asigne `empresa` a los usuarios existentes; hay que poblarla antes de depender de este filtro en producción.
- El filtro no cubre `BoletoVenta`/`BoletoAsiento` (sin `empresa_id` directo); su aislamiento sigue dependiendo de navegar vía `Recorrido`/`Usuario` hasta encontrarlo.
- Un filtro Doctrine es una defensa a nivel de ORM, no de base de datos (no protege contra SQL crudo ni contra el EM `systemfdn`); no sustituye revisar cada nuevo repositorio/query nativa que se añada.
