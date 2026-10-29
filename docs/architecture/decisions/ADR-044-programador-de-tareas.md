# ADR-044: Programador de tareas a la medida del dominio

**Estado:** Aceptada

## Contexto

Lo que debía correr solo estaba repartido y en parte sin dueño:

- `app:venta:purgar --cada=60` y `app:sincronizar --cada=60` eran bucles permanentes, cada uno con su forma de dormir y de fallar; la sincronización tenía su propio contenedor (`compose.sincronizacion*.yaml`).
- `app:venta:certificar-pendientes` y `app:chat:purgar-archivos` se documentaban como "cron", pero ningún cron los corría.
- Nadie sabía si algo de eso se había detenido.

Además faltaban procesos que el negocio pide: reportes diarios a quien corresponde (por el chat o por correo), mantener programadas las salidas de un esquema varias semanas adelante y avisar cuando un usuario pasa de cierto número de anulaciones en el día.

La evaluación previa recomendó `symfony/scheduler` sobre el Messenger y el PostgreSQL que ya hay. Se decidió además que no fuera genérico: un catálogo cerrado en código, con las reglas del dominio dentro, en lugar de tareas configurables desde la base.

## Decisión

### Infraestructura

- **`symfony/scheduler` + `symfony/lock`**, sin servicios nuevos: el horario se guarda en PostgreSQL (pool `cache.programador`, tabla `cache_items`) y los cerrojos son advisory locks de PostgreSQL (`LOCK_DSN=postgresql+advisory://…`; `in-memory` en pruebas).
- **Catálogo cerrado** `App\Programador\Tarea` (enum): código, nombre, frecuencia, hora (las diarias), tolerancia y el mensaje que la corre (un `MensajeDeTarea`). Agregar una tarea es agregar un caso y su handler.
- **`Programa`** (`#[AsSchedule('fdn')]`): una entrada por tarea. Tiene estado (si estuvo detenido, al volver corre una vez lo perdido, `processOnlyLastMissedRun`) y un cerrojo (un solo programador aunque haya dos contenedores). No ejecuta nada: reenvía cada mensaje a la cola `tareas` (`RedispatchMessage`).
- **Tres colas** en el transporte de Doctrine (`messenger_messages`): `async` (correos, push, como antes), `tareas` (las corridas del catálogo) y `pesadas` (lo que se reparte por esquema o por destinatario: `ExtenderHorizonte`, `EnviarReporteDiario`, con reintentos espaciados).
- **Contenedores**: `programador` (`messenger:consume scheduler_fdn`), `worker` (`async tareas`) y `worker-pesadas` (`pesadas`, con más memoria). Se quitó el servicio `sincronizacion`.
- **`TareasMiddleware`**, al consumir un `MensajeDeTarea`:
  - Toma el cerrojo `tarea.<código>`. Si la corrida anterior sigue, esta se omite (`omitida`).
  - Escribe el latido en `estado_tarea` (por DBAL, también si la tarea dejó el EntityManager cerrado).
  - Un fallo no se reintenta ni va a `failed`: queda en el latido y lo intenta la próxima corrida.
- **Salud**: `EstadoDeTarea::detenida()` dice si una tarea lleva más de su tolerancia sin una corrida buena. Una tarea que nunca corrió cuenta desde que el programador arrancó (`primer_inicio`). Si nada corrió nunca (una instalación nueva), no hay alarma.
  - El tablero suma las alertas `programador_detenido` (crítica) y `mensajes_fallidos`.
  - Pantalla `/configuracion/programador` (enlace en la administración, `config.editar`): cada tarea con cuándo corre, la última corrida buena y el último intento, su resultado o error, y «Correr ahora». Se actualiza sola cada 30 s.
  - `app:programador:tareas [--ejecutar=<código>]` (`make tareas t=…`) y `GET /api/programador/tareas`, `POST …/{código}/ejecutar` (`config.editar`).

### Tareas del catálogo

| Tarea | Cada | Qué hace |
| --- | --- | --- |
| `purgar_venta` | 1 min | Reservas web vencidas y ventas pendientes abandonadas (`Venta\Tarea\PurgaVenta`). |
| `certificar_pendientes` | 10 min | Facturas pendientes, de a 100 (`Venta\Facturacion\CertificacionPendientes`). |
| `sincronizar_legado` | 1 min | Un ciclo de la sincronización (ADR-042). Apagada, no hace nada. |
| `vigilar_operacion` | 30 min | Alertas críticas y de atención del tablero nuevas, a quien se suscribió. |
| `horizonte_salidas` | 01:30 | Despliega los esquemas automáticos. |
| `reportes_diarios` | `PROGRAMADOR_HORA_REPORTES` (06:00) | Reparte los reportes del día anterior. |
| `purgar_archivos_chat` | 03:15 | Archivos del chat de más de 24 h sin mensaje. |

Los comandos de antes siguen, pero corren una pasada: sirven para correr una tarea a mano. Ya no tienen `--cada` ni `--durante`.

### Umbrales diarios

- Tres límites nuevos que **no bloquean**: `aviso_anulaciones_por_dia`, `aviso_reasignaciones_por_dia` y `aviso_cortesias_por_dia`. Van en el rol, como los que bloquean (ADR-040). `Capacidad::limiteQueBloquea()` separa unos de otros.
- Al anular, reasignar o vender cortesías se cuenta lo del día antes y después (`Vigilancia\ConteoDiario`). Solo la operación que cruza el umbral emite `UmbralDiarioAlcanzado`, tras el commit (`UmbralesDiarios::cruza`).
- `AvisosDelSistema` avisa por el chat, con las tarjetas de la operación, a los aprobadores de autorizaciones más cercanos (`ConsultaAutorizaciones::aprobadoresCercanos`, misma regla de cercanía que las autorizaciones).
- Es en el acto, no por sondeo: no hace falta la tarea para enterarse.

### Reportes programados

- **Catálogo** `Reporte\Diario\ReporteProgramado`:
  - Resumen del día.
  - Cuadre de boletos y cuadre de encomiendas (los PDF de siempre).
  - Alertas de la operación.

  Cada reporte declara la capacidad que exige y su nivel (empresa entera o estación).
- **Suscripción voluntaria** (`suscripcion_reporte`) desde Mi cuenta → Reportes y avisos (`/api/me/reportes`), por el chat y/o por correo. Nadie recibe nada que no pidió.
- **Recorte por alcance**: `Celdas` parte el alcance del destinatario (ADR-039) en celdas `{empresa, estación}`, sin repetir lo que otra celda ya cubre. Cada pieza se arma con el alcance de su celda. El worker no tiene `TenantFilter`, así que el recorte es explícito siempre.
- **Entrega**:
  - Por el chat, en "Avisos del sistema", con los PDF adjuntos (archivos generados, `chat_archivo.autor_id` nulo).
  - Por correo, con plantillas `templates/reporte/programado_*`.
  - Una vez por usuario, reporte y día (`envio_reporte`). Un reintento no duplica.
  - Un día sin movimiento no manda nada.
- Las alertas de la operación se avisan una vez por ámbito, código y día (`aviso_operativo`, se purga a los 7 días).

### Esquemas automáticos

- **Campos del esquema** (`EsquemaSalida`, ADR-024):
  - `automatico`.
  - `horizonteDias`: de 7 a 180, 90 por defecto.
  - `ancla`: el día desde el que cuenta `intervaloDias`.
  - `programadoHasta`: la frontera, el último día ya desplegado.
  - `pausadoMotivo` y `pausadoEn`.
- **La frontera solo avanza.** Lo que queda detrás no se vuelve a desplegar: una salida anulada o borrada a mano no reaparece.
- **Despliegue por tramos** de hasta 14 días (o un intervalo, si es más largo) que empiezan en un día de la cadencia (`Salida\Horizonte\Cadencia`, puro):
  - Cada tramo es una transacción con el esquema bloqueado. Usa el mismo plan que el programador de salidas (`ProgramadorSalidas::desplegarEsquema`): las existentes no se duplican y las que chocan no se crean.
  - La frontera avanza en el mismo commit. Un fallo a mitad deja lo hecho, y el reintento sigue desde ahí.
- **Conflicto** (el bus ya tiene otro viaje): no detiene el esquema. Se avisa una vez por el chat a quienes tienen `salida.crear` en la empresa del esquema, con la salida que choca.
- **Rechazo** (ruta inactiva, un bus que ya no es de la empresa del esquema): pausa el esquema y se avisa. Sigue cuando alguien lo corrige y lo guarda.
- **Sin usuario ni alcance**: el despliegue usa los buses del esquema y exige que sigan siendo de su empresa. Las salidas creadas no tienen `createdBy`.
- **Al guardar un esquema automático** se pide la extensión enseguida, tras el commit. Si se agregaron horas, esas se ponen desde hoy hasta la frontera. Además:
  - Un cambio de bus rige desde la frontera.
  - Quitar una hora no toca lo ya creado.
  - Cambiar la ruta reinicia la frontera (lo de la ruta anterior queda).
  - Apagarlo conserva la frontera, para no repetir al volver a encenderlo.
  - Guardar quita la pausa.
- **Frontend**: en el programador, el panel del esquema enciende y apaga el modo automático (con el esquema tal como está guardado) y muestra la frontera y la pausa. Al encenderlo, la cadencia cuenta desde el primer día del formulario.

## Consecuencias

- **Despliegue**:
  - Tres contenedores en lugar de dos más el de la sincronización.
  - `LOCK_DSN` nuevo.
  - La migración `Version20261029120000`.
  - Sin el `programador` y el `worker`, no se purga, no se certifica, no se sincroniza y no salen los reportes. El tablero lo dice.
- **Latencia**:
  - Las tareas de cada minuto pueden empezar hasta un tic tarde.
  - Los avisos de umbral son en el acto.
  - Las alertas de la operación llegan hasta 30 minutos después.
- **Efecto en ventas y taquilla**: ninguno en el camino de la petición, salvo un `COUNT` del día al anular, reasignar o vender cortesías (ya existía para los límites que bloquean).
- **Agregar una tarea, un reporte o un umbral es código**, con su prueba. No hay tareas configurables desde la base.
- **Al retirar la sincronización** (ADR-042), también se quita `Tarea::SINCRONIZAR_LEGADO` y su handler.
