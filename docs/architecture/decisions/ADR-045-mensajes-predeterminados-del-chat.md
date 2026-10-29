# ADR-045: Mensajes predeterminados del chat

**Estado:** Aceptada

## Contexto

La administración quiere que el chat avise solo, sin que nadie escriba:

- Un mensaje propio (texto, imagen, archivo) a una hora fija, como un recordatorio.
- Lo que pasó al iniciar una salida: asientos libres y ocupados por canal y lo que vendió cada usuario.
- Las anomalías: un boleto reasignado una y otra vez, una tarifa creada o cambiada.
- Reportes del mismo día al cierre: ventas por estación y desempeño general.

Cada aviso va a una audiencia elegida (usuarios o grupos del chat), y puede haber varias instancias del mismo tipo (una "Salida iniciada" por ruta). Solo lo configuran `ROLE_SUPER_ADMIN` y `ROLE_ADMIN`.

Ya existían los avisos del sistema (ADR-026), el programador de tareas y los reportes por suscripción (ADR-044). La diferencia: en ADR-044 cada usuario se suscribe y el contenido se recorta a su alcance; aquí un administrador decide quién recibe qué.

Se acordó con negocio:

- El mensaje sin predeterminado sale con horario propio (hora y días).
- Llega a "Avisos del sistema" de cada destinatario. Un grupo se expande a sus miembros.
- La anomalía de reasignación es un boleto reasignado más de dos veces (la cadena `reasignadoDe`).
- Los reportes salen al cierre del día, con lo del mismo día.

## Decisión

### Modelo

- **`MensajePredeterminado`** (la instancia):
  - Nombre, `activo` y un tipo del catálogo cerrado `TipoMensajePredeterminado`.
  - Audiencia: `usuarios` y `grupos` (`ChatCanal` de tipo grupo).
  - Contenido propio: `texto` (≤ 2000) y `archivos` (≤ 10 `ChatArchivo` sin mensaje).
  - Configuración según el tipo: `empresa`, `ruta`, `hora` (`HH:MM`) y `dias` (ISO 1–7, vacío = todos).
- **Catálogo** (`TipoMensajePredeterminado`). Cada tipo declara qué exige: empresa, ruta, hora, días o contenido.

  | Tipo | Exige | Cuándo sale |
  | --- | --- | --- |
  | `libre` | Contenido y hora | A su hora, en sus días |
  | `salida_iniciada` | Empresa y ruta | Al pasar la hora de cada salida de esa ruta y empresa |
  | `anomalia_reasignacion` | Nada (empresa opcional, filtra) | De madrugada, con lo del día anterior |
  | `anomalia_tarifa` | Nada (empresa opcional, filtra) | De madrugada, con lo del día anterior |
  | `ventas_dia` | Empresa | A su hora (22:00 por defecto) |
  | `desempeno_dia` | Empresa | A su hora (22:00 por defecto) |

  El contenido propio, si lo hay, va encima del predeterminado.
- **`EnvioMensajePredeterminado`**: una constancia por instancia y clave (`salida:123`, `dia:2026-10-29`, `anomalias:2026-10-28`). Se escribe por DBAL antes de enviar (`ON CONFLICT DO NOTHING`) y se borra si el envío falla, como `EnvioReporte`. Un reintento o una corrida recuperada no duplican.
- **Archivos**: los de una instancia no son "sueltos". `Archivos::purgarSueltos` los excluye y, al purgar, borra del disco solo las rutas que ningún otro registro usa: cada envío lleva copias que comparten la ruta.

### Envío (`App\Chat\Predeterminado`)

- **Dos tareas nuevas en el catálogo de ADR-044**:
  - `mensajes_predeterminados`, cada 5 minutos: los de hora cuya hora de hoy ya pasó y las salidas cuya hora ya pasó.
  - `revisar_anomalias`, a las 02:30: lo del día anterior.
- **Salida iniciada**:
  - Una salida cuenta como iniciada cuando pasa su hora programada (`salida.fecha`), no por su estado: en la operación casi nadie la pasa a `iniciada`, y con el estado no salía ningún aviso. Las canceladas no se avisan.
  - Cubre también un worker caído hasta 6 horas.
  - Lo que esta instancia ya avisó se descarta antes de armar el contenido.
  - Solo cuenta desde que la instancia existe: crearla no avisa lo de antes.
- **Los de hora**:
  - Salen si ya pasó su hora de hoy, hoy es uno de sus días y la instancia existía a esa hora. Crearla a las 23:00 con hora 22:00 no la manda hasta el día siguiente.
  - Una vez por día (`dia:…`).
- **Contenidos** (`Contenidos`), con las mismas consultas que las pantallas:
  - **Salida iniciada** (`OcupacionAlIniciar`, puro):
    - Ocupado: un asiento con un boleto vivo. Uno vendido solo en tramos intermedios también cuenta.
    - Libres: los asientos del bus menos los ocupados.
    - Desglose por canal (estación, agencia, web).
    - Una fila por quien vendió: el usuario de la venta, y el reasignado sigue en la misma venta. Aparece aunque solo tenga anulados.
    - Columnas: reasignados (llegaron por reasignación), anulados, vouchers, cortesías, intermedias, clase A, clase B y total (A + B, los vivos). Se solapan.
    - El chat lleva el resumen en el texto (ruta, hora, bus, empresa, asientos y canales), la tarjeta `OcupacionSalida` con la tabla por vendedor (calculada al leer, como las demás tarjetas: muestra lo vendido hasta ahora, no una foto del inicio; llega desplegada), el PDF con la tabla al momento del envío (`templates/reporte/salida_iniciada.html.twig`) y la salida como tarjeta.
  - **Ventas del día**: total, anulados, cortesías, por canal y por estación (`Tablero::puntos(null)`).
  - **Desempeño del día**: `ResumenDiario::seccion` del mismo día.
  - **Anomalías**: una línea por anomalía, hasta 20 por mensaje, con su tarjeta. El texto dice "Anomalía en reasignación" o "Anomalía en tarifa" y la referencia a la bitácora del registro.
    - Reasignación: los boletos creados en el día por una reasignación que ya van por la tercera o más (CTE recursiva sobre `reasignado_de_id`). Solo el último de cada cadena.
    - Tarifa: las tarifas con bitácora del día (creada, modificada o eliminada), con quién lo hizo.
- **Bitácora de tarifas**:
  - `TipoRegistro::TARIFA` y `OperacionTarifa`.
  - `RegistradorBitacora` anota creada, modificada (con los cambios campo por campo) y eliminada.
  - Solo lo que hace un usuario: la migración y la sincronización con el legado no tienen autor.
  - Se lee por `GET /api/bitacora/tarifa/{id}` con `tarifa.ver` en su empresa.
- **Cada instancia por su lado**: un fallo se anota en el log y no detiene a las demás. El latido de la tarea resume envíos, destinatarios y fallidos.

### Acceso y administración

- **Capacidad nueva `chat.predeterminados`** (sin dimensiones). La migración `Version20261029150000` se la da a `ROLE_ADMIN`; el rol de sistema la tiene por serlo. Sigue ADR-039: no se pregunta por el rol, sino por la capacidad.
- **REST `/api/chat/predeterminados`**:
  - Listar con las opciones del formulario.
  - Crear, editar y eliminar.
  - `…/{id}/activo`: activar o desactivar.
  - `…/{id}/probar`: lo que saldría ahora, solo a quien prueba y sin constancia. Para una salida, la última de la ruta cuya hora ya pasó; para una anomalía, la revisión de ayer.
- **Frontend**: `/configuracion/mensajes-predeterminados`, con una tarjeta por instancia: cuándo sale, audiencia, último envío, activar, probar, editar y eliminar. El formulario sigue los tres pasos del requisito: nombre, audiencia y contenido. Reglas puras en `core/chat/predeterminados.ts`.

## Consecuencias

- **No se recorta al alcance del destinatario** (a diferencia de ADR-044): la audiencia la eligió un administrador, como quien reenvía un reporte. Sí se recortan las tarjetas, que se resuelven con los permisos de quien lee.
- **Latencia**:
  - Salida iniciada: hasta 5 minutos después de su hora programada. Una salida que sale tarde se avisa igual a su hora, y una que se adelanta, al llegar su hora.
  - Los de hora: hasta 5 minutos después de su hora.
  - Anomalías: a la madrugada siguiente.
- **Ventana de 6 horas**: una salida cuya hora pasó mientras el programador estuvo detenido más de 6 horas no se avisa.
- **Las salidas del legado** se avisan igual: el criterio es la hora, no la bitácora. Las tarifas que cambian por la sincronización (ADR-042), no.
- **Agregar un tipo es código**: un caso del enum, su contenido en `Contenidos` y su prueba. La configuración de cada instancia sí está en la base.
