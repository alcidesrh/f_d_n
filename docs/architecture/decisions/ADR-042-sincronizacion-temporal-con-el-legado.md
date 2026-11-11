# ADR-042: Sincronización temporal con el legado mientras dura la mudanza

**Estado:** Aceptada (temporal: se quita cuando la última estación use el sistema nuevo)

## Contexto

Las estaciones pasan al sistema nuevo una a una. Mientras tanto los dos sistemas venden los mismos asientos de las mismas salidas, y lo que se crea o cambia en uno tiene que verse en el otro: boletos y su árbol (cliente, venta, factura), sus operaciones (anular, reasignar, chequear, cancelar, revertir) y encomiendas con sus movimientos. Los estáticos (rutas, estaciones, tarifas) ya se migraron; lo viejo no importa.

Condiciones:

- El legado (Symfony 2.2 sobre SQL Server) está en producción, con actividad permanente más de 16 horas al día. Se puede modificar, con cuidado.
- Los modelos son distintos aunque las entidades se llamen igual: hace falta una capa que traduzca en los dos sentidos.
- Basta con que lo del otro sistema llegue en un minuto; el croquis no tiene que verse en vivo. Lo que no puede pasar es **duplicar o perder** datos, y sobre todo **vender dos veces un asiento**.
- Antes de insertar se comprueba que en el otro sistema el asiento no esté vendido, y se inserta **primero allá** y después donde se hizo la compra.
- Tiene que poder encenderse y apagarse a mano, con un indicador visible al menos en el sistema nuevo, y quitarse sin dejar rastro.
- Eficacia antes que eficiencia: es temporal.

## Decisión

Un módulo aparte, `backend/src/Sincronizacion/` (más un bundle en el legado), que trae con el motor de importación por salida (`App\Sincronizacion\Legado\Importacion`; era `App\Migration` hasta que la migración masiva se quitó, [ADR-056](ADR-056-refactorizacion-arquitectura-rendimiento-y-limpieza.md)) y en un listener de Doctrine para llevar.

### 1. Interruptor e indicador

- `sync_estado` (PostgreSQL) es el interruptor; se copia a `sync_config` del legado, que es lo que lee su bundle. Un solo interruptor para los dos.
- Encender exige que el legado responda (si no, seguiría vendiendo sin comprobar). Apagar se hace aquí aunque el legado no responda, con aviso.
- Indicador en la cabecera (`app/layout/IndicadorSincronizacion.vue`): encendida la ve todo el mundo (verde; ámbar con cambios pendientes o si el ciclo no corre hace más de 3 minutos; rojo con error); apagada solo quien tiene `config.editar`, que es quien la cambia (`PUT /api/sincronizacion`). También `app:sincronizacion:interruptor encender|apagar`.

### 2. Del legado a aquí: triggers + ciclo cada minuto

- Triggers `AFTER INSERT, UPDATE` en `boleto`, `salida`, `factura_generada`, `encomienda` y `encomienda_bitacora`, y `AFTER INSERT` en `cliente`, anotan el id en `sync_cambio` del legado. No cambian nada de lo que hace el legado.
- La tarea `sincronizar_legado` del programador (ADR-044, cada minuto, con cerrojo: un ciclo largo hace que se omita el siguiente) corre `Ciclo` (`app:sincronizar` corre uno a mano): `ImportadorLegado` agrupa los cambios por salida y vuelve a migrar cada salida con `Migrador::migrarSalidas` (idempotente por `legacy_id`: crea lo que falta, completa lo que cambió), las encomiendas con `EncomiendasLegado::migrarPorIds` y los clientes nuevos con `DependenciasLegado::cliente`. `ActualizadorBoletos` pone al día los boletos que ya estaban: estado (con su línea en la bitácora, `detalle.legado`), de qué boleto viene una reasignación y la factura certificada después.
- Escribe con DBAL: Doctrine no lo ve y no vuelve al legado.

### 3. De aquí al legado: primero allá

- `Nuevo\VigilanteCambios` escucha el flush de Doctrine, así que no importa por dónde llegue el cambio (taquilla, agencia, página web, reasignación, abordaje).
  - Lo que **ocupa un asiento** (boleto nuevo, revertir una cancelación) se escribe en el legado **dentro de la transacción de aquí**, antes del commit: `EscritorLegado::ocupar` bloquea la salida en SQL Server (`UPDLOCK`), comprueba boletos y reservaciones vigentes del asiento y, si está libre, inserta el boleto (`identificador_web = 'FDN:<uuid>'`, más su línea de bitácora). Si allá está ocupado, la venta de aquí se rechaza con «vendido en el sistema anterior»; si el legado no responde, también (503): mejor no vender que vender dos veces.
  - Lo que **libera** o solo cambia el estado se lleva **después del commit** (`Transaccion::despuesDeConfirmar`); si el legado no responde queda en `sync_cambio` (PostgreSQL) y lo reintenta el ciclo (`ExportadorCambios`, hasta 20 intentos).
  - Encomiendas, ediciones de clientes y facturas van a `sync_cambio` y se llevan al terminar la petición (`AlTerminar`) o en el ciclo. Un cliente nuevo se crea allá antes que aquí (punto 5).
- Lo que escribe FDN en SQL Server lleva `CONTEXT_INFO 'FDNSYNC'`: los triggers lo ignoran (no hay eco). La importación además omite lo marcado `FDN:` que aquí no está.
- Si el proceso muere entre el commit del legado y el de aquí, queda allá un boleto `FDN:` huérfano: la conciliación lo anula.

### 4. Ventas del legado: apartado en FDN antes de guardar

- `SincronizacionFdnBundle` (en el legado) escucha `onFlush`: antes de guardar un boleto que ocupa un asiento llama a `POST /api/sincronizacion/legado/apartar` (`salida`, `origen`, `destino`, `asientos` por número). FDN bloquea la salida (como su propia venta), comprueba con `ReglasVenta::exigirDisponibles` y deja un `ReservaAsiento` de 10 minutos (como el carrito web). Con 409 el legado no vende («ya fue vendido o apartado en el sistema nuevo»).
  - Al terminar la petición del legado (`kernel.terminate`, ya hecho el commit o el rollback) llama a `confirmar`: FDN trae esa salida al momento y quita el apartado. Si el aviso se pierde, el ciclo trae la venta y `ApartadosLegado::limpiar` quita el apartado cuando el boleto llega; si no llega, vence.
- Si FDN no responde: `sync_config.si_falla = 'permitir'` (defecto) vende igual y FDN lo trae al volver; `'bloquear'` no vende. Se eligió permitir: el legado está en producción y no debe detenerse por FDN; el riesgo es vender dos veces un asiento mientras FDN no responde, y la conciliación lo muestra. Apagado de emergencia desde SQL Server: `UPDATE sync_config SET activo = 0`.
- Clave compartida en `X-Sincronizacion-Clave` (`SINCRONIZACION_CLAVE`); URL de FDN vista desde el legado en `SINCRONIZACION_URL_FDN`. Las dos las deja `app:sincronizacion:instalar-legado` en `sync_config`: la configuración del legado (`app/config`, fuera de git) no se toca.

### 5. Identidades

- **Clientes: el legado da el id.** Un cliente tiene el mismo id en los dos sistemas; crearlo en los dos con secuencias propias no bastaría, porque dos altas del mismo minuto recibirían el mismo id.
  - Alta aquí con la sincronización encendida: `Nuevo\AltaCliente` (`prePersist`, cualquier camino: venta, página web, GraphQL) lo crea primero en el legado y usa ese id (`IdCliente`, el generador de `Cliente` mientras exista el módulo; la columna sigue siendo identidad). Sin legado, 503. Si el índice único del legado (NIT, nombre y documento, los tres con valor) ya tiene esa persona: se usa su id, o se rechaza si aquí también está. Sin documento siempre es una persona nueva. En modo «solo traer» no se crean clientes.
  - Alta en el legado: `trg_sync_cliente` y el ciclo la traen con su id. Si antes llega un boleto suyo, la migración lo crea igual.
  - Apagada: el id sale de la secuencia de aquí, que al apagar queda después del mayor conocido, y el cliente se anota en `sync_cliente_local`: allá ese id puede ser de otra persona. Al encender, y en cada ciclo, `Nuevo\ClientesLocales` lo crea en el legado y lo pasa a su id con sus boletos, ventas y encomiendas (las referencias sin clave foránea, como una tarjeta del chat, conservan el id viejo). Mientras quede alguno sin llevar no se importa nada y `EscritorLegado::cliente` no lo usa.
- **Usuarios: el legado no crea más.** Se migraron con su id y los de aquí siguen desde el mayor de los dos sistemas (`DependenciasLegado::reiniciarIdentidades`, igual para clientes). `EscritorLegado::usuario` usa el mismo id si existe allá. La conciliación avisa si un id es de usuarios distintos.
- Boletos, salidas y encomiendas se emparejan por `legacy_id`. Las encomiendas importadas conservan el id del legado (≈ 10 millones); las de aquí empiezan en 1: no chocan mientras FDN no registre 10 millones.

### 6. Conciliación

`app:sincronizacion:conciliar [día] [--reparar]` compara boletos de las salidas del día y encomiendas creadas ese día. Repara solo lo que no necesita decisión: trae lo que falta aquí, anula los `FDN:` huérfanos (más de 10 minutos) y encola las encomiendas de aquí que no llegaron. Los estados distintos y los boletos de aquí que faltan allá solo se informan.

La tarea `barrer_salidas_legado` (cada 30 minutos) cubre lo que los triggers no avisan: una salida que no cambió nunca llega, y una omitida por no tener bus que inferir (ADR-027) no se reintenta, porque su cambio queda procesado. Busca las salidas no canceladas que faltan aquí, futuras o de los últimos dos días con boletos, e intenta hasta 50, primero las que tienen boletos. Las que quedan con boletos se anotan en `sync_estado.salidas_sin_traer` y ponen el indicador en atención.

El panel de migración, que no tomaba el cerrojo del ciclo, ya no existe (ADR-056): solo la sincronización escribe salidas del legado. `salida.legacy_id` es único, así que una carrera falla en vez de duplicar.

### 7. Modo «solo traer»

Con `SINCRONIZACION_SOLO_TRAER=1`, un FDN (de desarrollo) trae del legado de producción y nunca escribe allá: `ConexionLegado` rechaza toda escritura fuera de las tablas `sync_*` y del esquema, el interruptor deja `sync_config.activo = 0`, no se vigila el flush ni se exporta, y los endpoints del legado responden 503. Solo hacen falta los triggers en el legado, no el bundle.

## Consecuencias

- Una venta aquí espera además al SQL Server del legado (una transacción corta por venta); una venta en el legado espera a FDN (unos milisegundos; tope de 8 s).
- Con el legado caído no se vende aquí en salidas que existen en los dos ni se crean clientes, aunque sea para una salida solo de aquí; con FDN caído el legado sigue vendiendo (`si_falla = 'permitir'`) y el mismo asiento podría venderse en los dos hasta que FDN lo traiga; con `'bloquear'`, el legado no vende.
- El croquis de un sistema refleja lo vendido en el otro en menos de un minuto (al instante si es apartado o venta propia).
- Límites conocidos, que se informan y no se sincronizan: la caja del legado (`caja_operacion`), las ediciones de un cliente en el legado (aquí solo llega su alta) y los clientes antiguos que ningún boleto ha traído (no aparecen en la búsqueda de la taquilla), las salidas creadas solo aquí (sin `legacy_id`, el legado no puede venderlas), las reservaciones del legado (allá bloquean la venta de aquí, pero aquí no se ven) y una encomienda con varios artículos llega al legado como una sola fila. La factura en el legado (`factura_generada`) es de cortesía: si falta la serie o el tipo de cambio, sus datos van en la observación.
- Si el legado tiene el índice único `CUSTOM_IDX_BOLETO_SALIDA_ASIENTO_ESTADO` sin filtro, un segundo boleto anulado del mismo asiento chocaría al llevarlo; queda en `sync_cambio` con su error y lo muestra el indicador.

## Cómo se quita

1. Apagar la sincronización (indicador o `app:sincronizacion:interruptor apagar`).
2. `app:sincronizacion:instalar-legado --desinstalar` (triggers y tablas `sync_*` del legado).
3. En el legado: quitar `SincronizacionFdnBundle` de `AppKernel` y su carpeta.
4. Aquí: borrar `src/Sincronizacion/`, `config/packages/sincronizacion.yaml`, `config/routes/sincronizacion.yaml`, la línea de `security.yaml`, `Tarea::SINCRONIZAR_LEGADO` (y su `hora`/`frecuencia`/`mensaje` en `App\Programador\Tarea`), `IndicadorSincronizacion.vue` (y su línea en `AppHeader.vue`), `core/sincronizacion/` y los tests; una migración que borre las tablas `sync_*`. Con `src/Sincronizacion/` se va también el motor de importación (`Legado/Importacion`, ADR-056) y con él lo último que habla con el legado: los servicios `PDO $oldPdo` y `SondaLegado` de `config/services.yaml`, las variables `systemfdn_*`, la extensión `pdo_dblib` del `Dockerfile` y `App\Acceso\Legado`.
