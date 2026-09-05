# Trayecto canónico por par de enclaves (origen, destino)

Varias rutas legacy pueden compartir el mismo par de estaciones, así que se decidió que el
`Trayecto` es único por par `(origen_id, destino_id)`: existe una constraint única
(`uq_trayecto_origen_destino`), la migración colapsa los duplicados pre-existentes y el
migrador reutiliza el trayecto canónico por enclaves en lugar de crear uno por ruta.
La composición interna se modela con `Subtrayecto` (trayecto hijo + posición).
Alternativa rechazada: conservar la identidad por `ruta_codigo` legacy, que multiplicaba
trayectos idénticos y rompía la deduplicación del modelo nuevo.