# Itinerario lleva el trayecto directamente (sin plantilla intermedia)

En el esquema heredado `Recorrido` mezclaba plantilla e instancia, y `BoletoTarifa` quedó
como catálogo de precios. Al rediseñar se eliminó `Recorrido` y se decidió que cada
`Itinerario` fija su ruta al crearse: se resuelve el par (origen → destino) de la salida
legacy y se le asigna el `Trayecto` canónico. El precio no viaja desde el catálogo; se
congela como snapshot en cada `BoletoAsiento`. Consecuencia: no existe un "horario
reutilizable" — si mañana se quiere programación recurrente (plan semanal), habrá que
reintroducir una plantilla de salidas; se prefirió autonomía de instancia por simplicidad.