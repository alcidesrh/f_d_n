# ADR-027: Croquis como molde para elegir el bus y bus inferido en la migración

**Estado:** Aceptada

## Contexto

En el legado (TerminalOmnibus) los asientos son del **tipo de bus** (`bus_tipo`), no del bus. Una salida se crea con un tipo de bus y se vende sobre los asientos del tipo; el bus físico se asigna después, normalmente cerca de partir, y a veces nunca: hay salidas válidas con boletos que nunca tuvieron bus. El bus termina siendo decorativo y la pregunta "¿quién ocupa este asiento?" exige rodeos por el tipo.

El modelo nuevo ya decidió lo contrario (ADR-019): cada `Bus` tiene sus `Asiento`, y un `BoletoAsiento` apunta a un asiento real. Faltaban dos cosas:

1. Al programar una salida, elegir el bus entre ~300 sin ver su distribución es difícil. El usuario piensa primero en "un bus de 47 asientos, clase Platino" y después en cuál.
2. La migración omitía los boletos de las salidas del legado sin bus (`boleto_sin_bus`). Medido el 2026-10-09, desde un mes atrás en adelante: 291 salidas sin bus con boletos (162 futuras, 129 pasadas) y 1069 futuras sin bus ni boletos.

Se discutieron y descartaron:

- **Croquis como dueño de los asientos** (los boletos apuntan al asiento del croquis): es el `bus_tipo` del legado con otro nombre.
- **Salida con croquis y sin bus** (el boleto guarda la posición y recibe el asiento al asignar el bus): permite vender sin bus, pero obliga a que la ocupación, las reservas y el `BusMap` trabajen sin id de asiento, y vuelve a dejar al bus como algo opcional.
- **Bus fantasma por tipo:** mete en la flota buses que no existen.

## Decisión

**Reglas del modelo**

- **Toda salida tiene bus.** No se crean salidas sin bus.
- **`Croquis` es un molde** (`App\Entity\Croquis`): la distribución que comparten los buses con la misma firma (`App\Croquis\Croquis::firma`). Guarda la firma (única), el conteo de asientos (y de clase B), las plantas y los elementos sin ids. **No tiene asientos**: los asientos siguen siendo de cada bus (ADR-019). Solo `Bus` se relaciona con él (`Bus.croquis`, no se publica en GraphQL).
- **Un molde no se edita ni se duplica.** Si cambia la distribución de un bus, el bus pasa al molde que le corresponde, y si no existe se crea (`App\Croquis\Moldes::asociar`). Lo hacen `CroquisBus::guardar`, los dos migradores y `app:croquis:asociar` (relleno inicial, idempotente).
- **El croquis de un bus no cambia mientras el bus tenga salidas activas.** Activa es `programada`, `abordando` o `iniciada` con fecha de hace menos de un día o futura. Las más viejas en esos estados quedaron así sin que nadie las cerrara (muchas, del legado) y bloquearían el bus para siempre. `PUT /api/buses/{id}/croquis` responde 422 si la firma cambia. Guardar sin cambiar la distribución está permitido.
- **El croquis y la clase de bus (`BusClase`) son filtros** para elegir el bus al programar o editar una salida. `GET /api/gestion-salidas/opciones` devuelve `croquis` (moldes de la flota visible, con sus elementos para dibujarlos) y `clases`, y cada bus con `croquisId` y `claseId`. En el frontend, `features/salida/FiltroBus.vue` + `core/salida/buses.ts`. En "Editar salida" el filtro arranca con el croquis y la clase del bus actual.
- **Cambiar el bus de una salida** exige que no tenga asientos vendidos ni apartados: sus boletos tienen que estar anulados o reasignados. Ya lo aplicaba `GestionSalidas` con `AsientosVendidos` (ADR-024); no cambia.

**Migración del legado** (`App\Migration\Salida\InferenciaBus`, usada por `Migrador`)

Para cada salida del legado sin bus:

| Caso | Qué se hace |
|---|---|
| Con boletos (pasada o futura) | Se infiere el bus |
| Sin boletos y futura | Se infiere el bus |
| Sin boletos y pasada | Se omite (`salida_pasada_sin_bus`) |

Cómo se infiere el bus, en este orden:

1. **`hora`:** el bus de la salida más reciente del legado (anterior a esta y a hoy) con el mismo tipo de bus, empresa, ruta y hora.
2. **`franja`:** igual, con la hora a ±60 minutos.
3. **`flota`:** cualquier bus de la empresa con el croquis del tipo.

Un candidato sirve si es de la empresa, tiene **exactamente** el molde del tipo y está libre a esa hora. "Exactamente el molde del tipo" quiere decir que su firma coincide con la del croquis del `bus_tipo`, armado igual que la migración lo copia a cada bus. Así, los boletos se pasan por número sin perder ninguno. "Libre" se mide con `AgendaBus`, según la duración del trayecto, contra las salidas de la base nueva y las que el bus ya tiene en el legado. Si ningún candidato sirve, la salida se omite (`salida_sin_bus_inferible`) con su motivo. La clase de bus viene incluida en el tipo.

**Control:** `salida_bus_inferido` (`App\Entity\SalidaBusInferido`, fuera de la API) registra las salidas cuyo bus puso la migración, con el criterio y la salida del legado de referencia. En las corridas siguientes, si el legado ya tiene el bus real:

- **Mismo molde:** la salida pasa al bus real, sus boletos y reservas pasan a los asientos del mismo número, y se borra la fila de control (`bus_inferido_reemplazado`, o `bus_inferido_confirmado` si era el mismo bus).
- **Otro molde:** se conserva el inferido y se reporta (`bus_real_otro_croquis`) para reasignar a mano.

Los buses que no infirió la migración no se reemplazan nunca, igual que antes.

## Consecuencias

- El bus deja de ser decorativo: los asientos siguen siendo reales (ADR-019) y el usuario elige primero la distribución y la clase, y después el bus.
- Los boletos del legado vendidos sin bus se migran. Las salidas pasadas con boletos dentro del rango migrado quedan con un bus que no se registró en el legado. Aparecen en el reporte "por bus y salida" y se pueden identificar con `salida_bus_inferido`.
- **Riesgo operativo:** si en el sistema nuevo se mantiene la costumbre de decidir el bus cerca de partir, cada cambio de bus con boletos vendidos exige reasignarlos. La **Reasignación** (que hoy solo existe en el legado) se vuelve una operación necesaria: con el mismo molde se puede hacer automáticamente por número, como hace la migración.
- `salida.bus_id` sigue aceptando null en la base de datos por los datos ya migrados. La regla "toda salida tiene bus" la aplican el programador (cada hora exige bus), la edición (no se quita el bus) y la migración.
- La inferencia consulta el legado varias veces por salida. Migrar un rango de ~700 salidas toma unos minutos más.
- Despliegue: aplicar la migración `Version20261009120000` y correr `bin/console app:croquis:asociar` una vez.
