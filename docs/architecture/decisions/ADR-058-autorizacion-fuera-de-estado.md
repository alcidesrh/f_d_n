# ADR-058: Autorización fuera de estado — una vez, lo que el rol de sistema hace siempre

**Estado:** Aceptada (amplía ADR-040 con una cuarta dispensa y el sujeto «encomiendas»; usa el camino del rol de sistema de ADR-057)

## Contexto

Una autorización (ADR-040) salva tres reglas: sin permiso, límite diario y fuera de hora. La de hora
exige que el boleto siga `emitido` (o `pospuesto`) y que la salida no esté finalizada ni anulada. Un
boleto chequeado, en tránsito, finalizado o cancelado, o uno cuya salida ya se finalizó, no se anula ni
se reasigna ni con autorización. Las encomiendas no tienen autorizaciones: solo se anulan en la bodega
de su origen.

Solo el rol de sistema opera en esos estados (ADR-057), para corregir errores de operación: un boleto
que se dio por finalizado y nunca viajó, o una encomienda entregada por error. Eso deja todas las
correcciones en una persona. En las estaciones, quien conoce el caso no puede pedirlas con el
mecanismo de siempre (motivo, aprobador, bitácora).

## Decisión

Una cuarta dispensa, **`estado` («Fuera de estado»)**. Con ella, quien la pidió hace **una vez**,
sobre registros concretos, lo que el rol de sistema hace siempre.

### Qué se puede autorizar

| Capacidad | Sin permiso | Límite diario | Fuera de hora | Fuera de estado |
| --- | :-: | :-: | :-: | :-: |
| `boleto.anular` | ✓ | ✓ | ✓ | ✓ |
| `boleto.reasignar` | ✓ | ✓ | ✓ | ✓ |
| `venta.cortesia` | ✓ | ✓ | — | — |
| `encomienda.anular` | — | — | — | ✓ |

- **Boletos** (`ReglasBoletos::soloPorEstado`): no se pueden operar de forma normal, la autorización
  fuera de hora no alcanza, pero el rol de sistema sí podría (`exigirOperablePorSistema`: cualquier
  estado con valor, sin mirar la salida). Las dos dispensas no se superponen:
  - Emitido, pasada la hora, con la salida programada, abordando o iniciada: **hora**.
  - Chequeado, en tránsito, finalizado o cancelado, o emitido con la salida finalizada o anulada:
    **estado**.
  - Anulado o reasignado: ninguna. Su importe ya se devolvió o pasó al boleto nuevo.
- **Cancelar** no entra: sigue con sus reglas.
- **Encomiendas** (`ReglasEncomienda::soloPorEstado`): ya viajó (embarcada, en tránsito, desembarcada
  en otra parada o entregada) y el rol de sistema la anularía.
  - La que va con un boleto se sigue anulando solo con su venta. Cuando su venta se anula con una
    autorización fuera de estado de sus boletos, se anula en cualquier estado, como con el rol de
    sistema.
  - La anulada, nunca.
  - La estación de origen se sigue exigiendo a quien tiene estación.
- Sigue exigida la venta confirmada. Al reasignar, la salida destino sigue sus reglas: misma empresa,
  mismo precio, vendible y antes de su hora.

### Quién la aprueba

Como «sin permiso» (`ReglasAutorizacion::rechazoParaResolver`): `autorizacion.aprobar` donde cae la
operación, **y además la capacidad de la operación ahí** (`boleto.anular`, `boleto.reasignar` o
`encomienda.anular`). Es una corrección: la aprueba quien podría hacer esa operación en ese lugar. Los
avisos a los aprobadores más cercanos y la bandeja «por resolver» ya filtran con esa regla.

### Cómo se usa

Igual que la de hora, con el camino del rol de sistema:

- `DispensasEnCurso` guarda los boletos y las encomiendas que la operación de la petición opera fuera
  de estado. `GuardiaBoleto`, solo para `anular` y `reasignar`, y `GuardiaEncomienda`, para `anular` y
  `anular_con_boleto`, los tratan como si los operara el rol de sistema. La máquina ya admite esos
  estados de origen (ADR-057), así que no cambia.
- `PasesDelFlujo` (en `App\Venta\Boleto\Flujo`) reemplaza los dos `exigirHora` que estaban duplicados
  en `AnulacionBoletos` y `ReasignacionBoletos`:
  1. Clasifica cada boleto rechazado: hora, estado o rechazo.
  2. Marca las dispensas y vuelve a exigir el flujo.
  3. Exige un pase por dispensa con el mensaje y el código del rechazo (409).
- `MovimientosEncomienda::anular` hace lo mismo con la encomienda. El pase se usa en la transacción de
  la anulación, y la bitácora de la transición lleva `autorizaciones`.
- El sujeto nuevo de la solicitud es **`encomiendas`** (`SolicitudAutorizacion::SUJETO_ENCOMIENDAS`):
  - Encomiendas de una misma empresa.
  - Caen donde el usuario opera sobre esa empresa (`Ubicador::operacion`), como al anularla.
  - El detalle lleva código, estado, origen, destino y salida.

### Lo que ve la pantalla

- Listado de boletos: `fueraDeEstado` en cada fila. «Anular» y «Reasignar» se ofrecen con candado.
- Boletos para operar (`GET /api/venta/boletos`): `conAutorizacion: "estado"`.
- Listado de encomiendas: `fueraDeEstado`. «Anular» con candado. El diálogo de anular ofrece pedirla
  ante el bloqueo (`useAutorizacionesStore().ofrecer`).
- Autorizaciones: la regla «Fuera de estado» y las encomiendas en la tarjeta y en el diálogo.

Sin migración: `dispensa` y `sujeto` son VARCHAR(20).

## Consecuencias

**Positivas:**

- Las correcciones que antes solo podía hacer el administrador del sistema las pide quien conoce el
  caso y las aprueba alguien con esa capacidad ahí. Queda constancia de quién la pidió, quién la aprobó
  y por qué.
- Las reglas no se aflojan: el código es el mismo del rol de sistema (ADR-057) y se aplica a un pase
  con nombre, motivo y vigencia.
- Anular y reasignar comparten la lógica de las dispensas del flujo (`PasesDelFlujo`), que antes
  estaba copiada.

**Negativas / pendientes:**

- Una operación que necesita varias (fuera de hora y fuera de estado en la misma venta, o además sin
  permiso) se bloquea una por una: se pide, se aprueba, se repite y se pide la siguiente, como en
  ADR-040.
- La bitácora de cada encomienda no anota la solicitud ni su resolución, solo la anulación con
  `autorizaciones`. La historia completa está en la bitácora de la autorización.
- Encomiendas: solo anular y solo fuera de estado. Ni sin permiso, ni embarcar, entregar o desembarcar.
