# ADR-012: Trayecto canónico por par de enclaves (origen, destino)

**Estado:** Aceptada

## Contexto

Varias rutas del sistema legado podían compartir el mismo par de estaciones bajo códigos distintos, lo que multiplicaba trayectos idénticos y rompía cualquier intento de deduplicación en el modelo nuevo.

## Decisión

`Trayecto` es único por par `(origen_id, destino_id)`, reforzado con `UniqueConstraint(uq_trayecto_origen_destino)`. La migración colapsa los duplicados preexistentes y el migrador reutiliza el trayecto canónico por enclaves en lugar de crear uno por cada ruta legacy. La composición interna de un trayecto (tramos con orden) se modela con `Subtrayecto` (trayecto hijo + `belowTo` + `position`).

**Alternativa rechazada:** conservar la identidad por `ruta_codigo` legacy — se descartó porque perpetuaba trayectos idénticos duplicados.

## Consecuencias

**Positivas:**

- Un trayecto geográfico (A → B) tiene una sola fila, sin importar cuántas rutas legacy o empresas lo operen.
- `Trayecto` es vectorial por diseño y por constraint: A→B y B→A son y deben seguir siendo filas distintas.

**Negativas:**

- El migrador debe resolver activamente el trayecto canónico en cada importación en vez de crear uno nuevo por ruta, lo que añade lógica de deduplicación al pipeline de migración.
