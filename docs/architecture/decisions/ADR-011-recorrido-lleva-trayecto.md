# ADR-011: Recorrido lleva el trayecto directamente (sin plantilla intermedia)

**Estado:** Aceptada — nombre de la entidad actualizado por ADR-013 (originalmente `Servicio`)

## Contexto

En el esquema heredado, un `Recorrido` (nombre que en ese momento designaba una plantilla reutilizable: precio, bus habitual, trayecto) convivía con `Servicio` para la fecha concreta de salida. Esta ambigüedad generaba confusión: un desarrollador que buscara la fecha de un "recorrido" no la encontraba donde el glosario sugería.

## Decisión

Se elimina la plantilla intermedia. La entidad de la salida concreta (en ese momento llamada `Servicio`, renombrada a `Recorrido` por ADR-013) fija su ruta al crearse: se resuelve el par (origen → destino) de la salida legacy y se le asigna el `Trayecto` canónico directamente (`Recorrido.trayecto`). El precio no viaja desde un catálogo de plantilla; se resuelve vía `BoletoTarifa` y se congela como snapshot en cada `BoletoAsiento.precio`.

## Consecuencias

**Positivas:**

- Elimina la ambigüedad semántica plantilla vs. instancia — `Recorrido` es la única entidad con fecha, trayecto y bus de una salida concreta (el piloto vive en `Bus`, ver ADR-016).
- `BoletoTarifa` queda como catálogo de precios independiente, no acoplado a una plantilla de servicio.

**Negativas:**

- No existe un "horario reutilizable": si se necesita programación recurrente (plan semanal de salidas), habrá que reintroducir una plantilla. Se prefirió autonomía de instancia por simplicidad mientras esa necesidad no sea prioritaria.
