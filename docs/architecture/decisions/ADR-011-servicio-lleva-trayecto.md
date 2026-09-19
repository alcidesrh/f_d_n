# ADR-011: Servicio lleva el trayecto directamente (sin plantilla intermedia)

**Estado:** Aceptada

## Contexto

En el esquema heredado, `Recorrido` mezclaba dos conceptos distintos: una plantilla reutilizable (precio, bus habitual, trayecto) y, a la vez, se apoyaba en `Servicio` para la fecha concreta de salida. Esta ambigüedad generaba confusión: un desarrollador que buscara la fecha de un "recorrido" no la encontraba donde el glosario sugería.

## Decisión

Se elimina la entidad `Recorrido`. Cada `Servicio` fija su ruta al crearse: se resuelve el par (origen → destino) de la salida legacy y se le asigna el `Trayecto` canónico directamente (`Servicio.trayecto`). El precio no viaja desde un catálogo de plantilla; se resuelve vía `BoletoTarifa` y se congela como snapshot en cada `BoletoAsiento.precio`.

## Consecuencias

**Positivas:**

- Elimina la ambigüedad semántica plantilla vs. instancia — `Servicio` es ahora la única entidad con fecha, trayecto, bus y piloto de una salida concreta.
- `BoletoTarifa` queda como catálogo de precios independiente, no acoplado a una plantilla de servicio.

**Negativas:**

- No existe un "horario reutilizable": si se necesita programación recurrente (plan semanal de salidas), habrá que reintroducir una plantilla de servicios. Se prefirió autonomía de instancia por simplicidad mientras esa necesidad no sea prioritaria.
