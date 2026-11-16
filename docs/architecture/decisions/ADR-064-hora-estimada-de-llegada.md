# ADR-064: Hora estimada de llegada por parada

**Estado:** Aceptada (usa el horario de [ADR-030](ADR-030-ruta-con-paradas-ordenadas.md) y las lecturas de [ADR-050](ADR-050-historial-gps-y-reproduccion-de-trayectorias.md))

## Contexto

El mapa de seguimiento mostraba una «llegada estimada» que salía de la simulación (`PlanDeViaje`:
distancia y velocidad media), no del horario de la salida ni de dónde iba el bus de verdad. Nadie
podía decir con cuánto atraso llegaba un bus a una parada.

## Decisión

`App\Seguimiento\HoraEstimada` (puro, con tests):

- El **atraso** es la hora de la última lectura GPS menos la hora a la que el horario
  (`SalidaParada`) ponía al bus en ese punto. El punto es la proyección de la lectura sobre la línea
  que une las paradas con coordenadas; la hora ahí, la interpolación entre las dos paradas del tramo.
- La **hora estimada** de cada parada que falta es su hora del horario más el atraso, nunca antes de
  ahora. Las paradas que el bus dejó atrás quedan como «ya pasó».
- En el origen (a menos de 1 km) no hay adelanto: un bus esperando su hora va a tiempo.
- Una lectura a más de 15 km de la ruta no se usa. Sin lectura vigente (5 min, como el mapa), las
  horas son las del horario y la fuente lo dice (`horario` en vez de `gps`).

`HorasEstimadas` lo arma para una salida: solo toma la lectura del bus desde 30 minutos antes de la
hora de salida y si la salida no está cancelada ni finalizada (el mismo bus hace otras salidas).

API: `GET /api/seguimiento/salidas/{id}/llegadas` (permiso `salida.ver`) →
`{ fuente, atrasoMinutos, lectura, paradas: [{ enclave, nombre, programada, estimada, paso }] }`.
El panel del bus en `/seguimiento` muestra la lista de paradas con su hora y el atraso.

## Consecuencias

- La estimación supone que el atraso se mantiene: no conoce el tráfico de lo que falta. Es el método
  de «propagar el atraso» de GTFS Realtime; se puede afinar después con el ruteo de Valhalla (ADR-055).
- La proyección es recta entre paradas: en rutas con muchas curvas entre dos paradas lejanas el
  atraso puede variar unos minutos. Más paradas con coordenadas lo mejoran.
- No guarda nada: se calcula al pedirlo.
