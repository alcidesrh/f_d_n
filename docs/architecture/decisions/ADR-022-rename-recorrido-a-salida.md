# ADR-022: Renombrar la entidad Recorrido a Salida

**Estado:** Aceptada

## Contexto

ADR-013 renombró `Servicio` a `Recorrido`. El negocio y el sistema legado (TerminalOmnibus, tabla `salida`) llaman "salida" a este mismo concepto: el viaje concreto de un bus (trayecto + fecha + bus). Mantener `Recorrido` obligaba a traducir mentalmente entre el modelo nuevo y el legado, y `Migrador` ya hablaba de "migrar salida → recorrido".

## Decisión

Se renombra `Recorrido` a `Salida` en todo el código: entidad, `SalidaRepository`, enum `EstadoSalida`, `HorasSalida`, `BoletoAsiento::$salida`, `ReservaAsiento::$salida`, endpoints (`/api/venta/salidas`, `/api/publico/salidas`), tópico Mercure `/salidas/{id}/ocupacion`, claves JSON (`salida`, `salidaId`), permisos IAM `salida.*`, frontend (`SalidasLista.vue`, tipos `Salida*`) y página pública. `SCHEMA_VERSION` del frontend sube a 8.

En base de datos, la migración `Version20260930210000` usa `RENAME` (tabla `recorrido` → `salida`, columnas `recorrido_id` → `salida_id`, secuencia, índices, claves foráneas y filas `action` con código `recorrido.*`), de modo que se conservan los datos.

No se tocan `App\EntitySistemaFdn\Salida`/`EstadoSalida` (espejo del legado, otro namespace) ni `TipoEstacion::RECORRIDO` (tipo de estación del legado). Las ADRs anteriores conservan el nombre `Recorrido` como registro histórico.

## Consecuencias

- El vocabulario del modelo nuevo coincide con el del negocio y el legado.
- En los payloads, la hora de salida sigue llamándose `salida` (p. ej. `salida.salida`); el campo que antes era `salidaRecorrido` pasa a `salidaInicio`.
- Clientes externos, URLs guardadas (`/pagina/recorrido/:id`) y el mensaje Mercure del tópico antiguo dejan de funcionar.
