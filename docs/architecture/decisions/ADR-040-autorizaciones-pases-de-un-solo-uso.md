# ADR-040: Autorizaciones — pases de un solo uso para lo que una regla impide

**Estado:** Aceptada (completa ADR-039; modifica la regla de hora de ADR-028 y ADR-037 con la dispensa «fuera de hora»)

## Contexto

En el legado, cuando una regla impedía una operación sobre un boleto, el usuario registraba una «solicitud de autorización» (`autorizacion_operacion`) con un motivo; un supervisor la autorizaba o la denegaba y, autorizada, la operación pasaba. El modelo nuevo no tenía equivalente: con ADR-039 lo que un usuario puede es la unión de sus asignaciones, sin excepciones por usuario; lo temporal se resuelve con la vigencia de una asignación, que es demasiado para «solo esta vez».

Los datos del legado (abril 2025 a octubre 2026, 4.361 solicitudes) dicen para qué se usaba:

| Tipo en el legado | Solicitudes | Cuándo |
| --- | ---: | --- |
| Reasignación por tiempo | 4.185 | 2.657 en las 2 h antes de salir; 1.459 después de la hora de salida |
| Anulación por tiempo | 143 | 102 en las 2 h antes; 36 después |
| Reasignación sin permiso | 20 | |
| Reasignación con el límite diario superado | 11 | |
| Reasignación secuencial (más de 20 reasignaciones en cadena) | 2 | |

El legado exigía autorización desde 2 h antes de la salida; el modelo nuevo deja anular y reasignar hasta la hora de salida (ADR-028), así que el 63 % de esos casos ya no la necesita. Lo que el modelo nuevo **no** permitía de ninguna forma es lo que más quedaba: **reasignar o anular después de la hora de salida** (el pasajero que perdió el bus), alrededor de 2,7 por día. Los límites diarios (cortesías y reasignaciones por día, ADR-039) y la falta de permiso eran, además, callejones sin salida para quien los alcanzaba.

## Decisión

Una **autorización** (`SolicitudAutorizacion`) es un pase de un solo uso: para **quien la pide**, para **una operación** (una capacidad), sobre **registros concretos** (unos boletos de una venta, o una salida con N asientos) y **de una regla** (una dispensa). La regla sigue en el código; la autorización solo dice «esta vez, para esto, sí».

### Qué se puede autorizar

Un conjunto cerrado en el código: cada capacidad declara de qué reglas admite dispensa (`Capacidad::dispensas()`, enum `Dispensa`):

| Capacidad | Sin permiso (`permiso`) | Límite diario (`limite`) | Fuera de hora (`hora`) |
| --- | :-: | :-: | :-: |
| `boleto.anular` | ✓ | ✓ (`anulaciones_por_dia`, nuevo) | ✓ |
| `boleto.reasignar` | ✓ | ✓ (`reasignaciones_por_dia`) | ✓ |
| `venta.cortesia` | ✓ | ✓ (`cortesias_por_dia`) | — |

- **Sin permiso**: hacerlo sin tener la capacidad donde cae la operación.
- **Límite**: pasar el límite diario del rol (`Limite`). Se suma `anulaciones_por_dia` (el ejemplo de negocio: «tres anulaciones por usuario por día»).
- **Fuera de hora**: anular o reasignar un boleto `emitido` cuya salida ya pasó su hora o inició, mientras no esté finalizada ni anulada (`ReglasBoletos::exigirOperableFueraDeHora`). Un pasajero que no se presentó (`cancelado`) se revierte primero (ADR-032) y luego se reasigna con la autorización.

La reasignación secuencial del legado no se trae: el modelo nuevo no la limita.

### Ciclo

```
pendiente ──aprobar──▶ aprobada ──usar──▶ usada
    │                      │
    ├──rechazar──▶ rechazada
    └──retirar───▶ retirada ◀──retirar──┘
pendiente o aprobada, pasadas 24 h ──▶ vencida (se calcula al leerla)
```

- **Pedirla**: cualquier usuario con sesión, para lo que de verdad lo bloquea (`SujetosAutorizacion` comprueba que no tenga el permiso, que tenga un límite, que solo la hora lo impida) y sin otra abierta para lo mismo; con motivo.
- **Resolverla**: quien tiene la capacidad nueva `autorizacion.aprobar` donde cae la operación (empresa, estación de la venta o de trabajo, agencia: `Ubicacion` guardada en la solicitud) y no la pidió él. Si dispensa del **permiso**, además tiene que tener esa capacidad ahí: dos personas no hacen juntas lo que ninguna puede (como la delegación de ADR-039). Para el límite o la hora basta con aprobar. Rechazar exige motivo; aprobar admite una nota.
- **Usarla**: no hay un botón «usar». El servicio que opera (`AnulacionBoletos`, `ReasignacionBoletos`, `RegistroVenta`), al toparse con la regla, busca un pase aprobado de quien opera que cubra la operación (`Pases`, `ReglasAutorizacion::cubre`: mismo solicitante, capacidad, dispensa y tipo de registro, todos los registros pedidos y no más unidades) y lo marca `usada` **dentro de su transacción**, con la fila bloqueada: si la operación se revierte, el pase sigue aprobado; dos operaciones a la vez no gastan el mismo. Una operación puede necesitar varios (fuera de hora y pasado el límite).
- **Vigencia**: 24 h desde que se pide (pendiente) y otras 24 h desde que se aprueba (pase sin usar).

### Cómo se entera cada uno

- El bloqueo responde como siempre (`{ error, codigo }`, mismo estado HTTP) más `autorizable`: lo necesario para pedirla y, si ya hay una pendiente, su número. El cliente no arma la solicitud: devuelve ese `autorizable` con el motivo.
- Pedida, el chat avisa (canal «Avisos del sistema», con la autorización como tarjeta) a los aprobadores **más cercanos** a la operación: los de esa estación; si no hay, los de esa empresa; si no, los que aprueban en todas (`ConsultaAutorizaciones::resolvedoresCercanos`). La bandeja de todos los que la pueden resolver se refresca por Mercure (`tipo: autorizacion` en el tópico privado del chat).
- Resuelta, el chat le avisa a quien la pidió (aprobada: «ya puede repetir la operación»; rechazada: el motivo).
- Bitácora propia (`/api/bitacora/autorizacion/{id}`: solicitada, aprobada, rechazada, retirada, usada) y en la de cada boleto (`autorizacion_solicitada|aprobada|rechazada`, y la anulación o reasignación lleva `autorizaciones: [id]` en su detalle), como el legado.

### Dónde vive

- Backend: `App\Autorizacion` (`Autorizaciones`: pedir, aprobar, rechazar, retirar; `Pases`: buscar y usar; `ReglasAutorizacion`: puras; `SujetosAutorizacion`; `ConsultaAutorizaciones`; `DispensasEnCurso`: las dispensas de hora de la petición en curso, que consulta el guard del flujo del boleto). REST `/api/autorizaciones` (listar por vista `por-resolver|mias|todas`, `resumen`, ver, pedir, `aprobar`, `rechazar`, `retirar`). Tabla `solicitud_autorizacion` (`Version20261022112523`).
- La regla de hora del flujo del boleto (ADR-037) acepta la dispensa: `ReglasTransicion::exigir(…, $fueraDeHora)` y `GuardiaBoleto` pregunta a `DispensasEnCurso`. Las acciones del listado de boletos traen `fueraDeHora` y los boletos para operar (`GET /api/venta/boletos`) `conAutorizacion`, para que el cliente deje intentar.
- Los endpoints de anular, reasignar y vender ya no cortan antes por la capacidad: el servicio la exige por venta (o por salida) y, sin ella, responde el bloqueo autorizable.
- Frontend: `core/autorizacion` (tipos, REST, reglas puras con tests, store con el resumen y el bloqueo ofrecido), `shared/autorizacion/SolicitarAutorizacionDialog` (lo monta el shell; se abre con `useAutorizacionesStore().ofrecer(error)` desde el diálogo de anular, la reasignación y la venta), página `/autorizaciones` (`features/autorizacion/`) y la campana de la cabecera (por resolver + pases sin usar).
- Migración: la capacidad `autorizacion.aprobar` a los roles que autorizaban en el legado (`ROLE_ADMIN`, `ROLE_ADMIN_EMPRESA`, `ROLE_ADMIN_ESTACION`, `ROLE_PROPIETARIO`, `ROLE_AUTORIZADOR`; el de sistema ya la tiene), también en `EquivalenciasLegado`.

## Consecuencias

**Positivas:**

- Se recupera lo que el legado resolvía y el modelo nuevo no: el pasajero que perdió el bus se reasigna o se anula, con quién lo pidió, quién lo aprobó y por qué.
- Las reglas no se aflojan: siguen en el código, iguales para todos; la excepción es un registro con nombre, motivo y vigencia, que se usa una vez.
- Sin estado del lado del cliente: el backend decide qué se puede pedir, lo comprueba al pedirlo y otra vez al usarlo.

**Negativas / pendientes:**

- Las autorizaciones del legado no se migran (sirvieron para operaciones que ya ocurrieron).
- `ROLE_PROPIETARIO` y `ROLE_AUTORIZADOR` aprueban fuera de hora y pasado el límite, pero no «sin permiso» para lo que ellos mismos no pueden hacer (anular, reasignar): revisarlo con quien opera esos puestos.
- Los avisos salen en la misma petición que aprueba o pide (después del commit): con muchos aprobadores, la respuesta tarda un poco más.
- Las autorizaciones de cortesía del legado (`autorizacion_cortesia`, pines) y las internas de encomiendas (`autorizacion_interna`) son otro mecanismo y no se cubren aquí.
