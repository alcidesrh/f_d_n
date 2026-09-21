# ADR-016: La asignación de Piloto vuelve a Bus, no a Recorrido; pilotoAux se renombra a copiloto

**Estado:** Aceptada — revierte parcialmente la parte de ADR-014/changelog 2026-09 sobre `Bus`↔`Piloto`

## Contexto

En una iteración anterior (mismo ciclo de trabajo que ADR-013/014) se quitó la relación `Bus.piloto`/`Bus.pilotoAux` bajo el argumento de que la asignación operativa de piloto debía vivir en la instancia concreta del viaje (`Recorrido.piloto`), no de forma estática en el bus, para no perder historial de "quién condujo qué bus cada día".

El negocio corrige ese criterio: la asignación de piloto y copiloto es un atributo del **bus** (tripulación habitual asignada a esa unidad), no del recorrido. `Recorrido` no debe tener relación con `Piloto`.

## Decisión

- Se elimina `Recorrido.piloto` (campo, getter, setter, columna `piloto_id` en la tabla `recorrido`).
- Se restaura `Bus.piloto` (`ManyToOne` a `Piloto`).
- Se restaura el segundo piloto de `Bus`, renombrado de `pilotoAux` a **`copiloto`** (campo, getter/setter, columna `copiloto_id` — antes `piloto_aux_id`). Es un rename puro, sin cambio de semántica.
- `Migration\Mapeador::bus()` devuelve `copiloto_id` en vez de `piloto_aux_id` (la clave del array legacy de origen sigue siendo `piloto_aux_id`, tal como la expone el sistema TerminalOmnibus; solo cambia la columna destino).
- `Migration\Migrador::crearSalida()` deja de leer `salida.piloto_id` del legado — no hay dónde escribirlo en el modelo nuevo. El piloto puntual de una salida histórica (que podía diferir del piloto habitual del bus) **no se migra**.

## Consecuencias

**Positivas:**

- Corrige el modelo a la semántica de negocio real: piloto/copiloto son tripulación asignada al bus.
- `pilotoAux` → `copiloto` es más claro en español (evita el calco "auxiliar").

**Negativas:**

- Se pierde la posibilidad de saber, por reporte histórico, "qué piloto condujo este bus en esta salida específica" si alguna vez difería del piloto habitual asignado al bus — ni el modelo nuevo ni la migración capturan ese dato hoy.
- El dato legacy `salida.piloto_id` de las salidas ya migradas queda sin destino; si se necesita en el futuro, requiere una nueva relación explícita en `Recorrido` (revirtiendo, otra vez, este ADR) con una razón de negocio distinta a la de la iteración anterior.
