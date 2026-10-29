# Sincronización con el legado (temporal)

Mantiene iguales este sistema y el legado (TerminalOmnibus, SQL Server)
mientras las estaciones se mudan. Diseño y límites: [ADR-042](../../../docs/architecture/decisions/ADR-042-sincronizacion-temporal-con-el-legado.md).

| Pieza | Qué hace |
| --- | --- |
| `Interruptor` | Encender / apagar (`sync_estado`, copiado a `sync_config` del legado) y el estado del indicador. |
| `Nuevo/VigilanteCambios` | Listener del flush: lleva al legado lo que cambia aquí (el asiento, antes del commit). |
| `Nuevo/EscritorLegado` | Escribe en SQL Server: boletos, estados, clientes, facturas, encomiendas. |
| `Nuevo/ExportadorCambios`, `Nuevo/AlTerminar` | Lo que quedó en `sync_cambio` de aquí, al terminar la petición o en el ciclo. |
| `Legado/ImportadorLegado`, `Legado/ActualizadorBoletos` | Trae lo que anotaron los triggers del legado, con el código de la migración. |
| `Legado/ApartadosLegado` | Aparta aquí lo que el legado está por vender (`/api/sincronizacion/legado/*`). |
| `Ciclo`, `Conciliador` | La vuelta de cada minuto y la comparación de un día. |
| `Legado/sql/*.sql` | Tablas `sync_*` y triggers del legado. |

## Puesta en marcha (por primera vez)

1. Variables en el `.env.local` del servidor de FDN:
   - `SINCRONIZACION_CLAVE`: una clave larga al azar (`openssl rand -hex 32`).
   - `SINCRONIZACION_URL_FDN`: la URL de FDN vista **desde el servidor del legado** (p. ej. `https://fdn.ejemplo.com`).
   - Opcional: `SINCRONIZACION_USUARIO_LEGADO`, id de `custom_user` del legado que firma lo que hagan aquí usuarios sin par allá.
   - La conexión al legado es la de siempre (`systemfdn_*`).
2. `bin/console doctrine:migrations:migrate` (tablas `sync_*` de aquí).
3. `bin/console app:sincronizacion:instalar-legado`: tablas y triggers en el legado, y la URL y la clave en su `sync_config`. Los triggers solo anotan ids; no cambian nada de lo que hace el legado. Se puede repetir.
4. En el legado: desplegar `src/Acme/SincronizacionFdnBundle` y su línea en `app/AppKernel.php`; limpiar la caché (`php app/console cache:clear --env=prod`). Con el interruptor apagado no hace nada.
5. El ciclo lo corre cada minuto el programador de tareas (ADR-044): basta con que estén arriba `programador` y `worker` (`make tareas` muestra `sincronizar_legado`).
6. Encender: desde el indicador de la cabecera (quien tiene `config.editar`) o `bin/console app:sincronizacion:interruptor encender`.
7. Al día siguiente, y cuando haga falta: `bin/console app:sincronizacion:conciliar -v` (y `--reparar`).

Para un piloto con una estación basta con encenderla: los dos sistemas venden
todo; lo que cambia es dónde trabaja cada estación.

## FDN de desarrollo contra el legado de producción («solo traer»)

Para ver en un FDN local lo que pasa en producción sin tocar sus ventas:

1. En el `.env.local` de tu FDN: `systemfdn_*` del legado de producción y `SINCRONIZACION_SOLO_TRAER=1`. No hacen falta `SINCRONIZACION_CLAVE` ni `SINCRONIZACION_URL_FDN`.
2. `bin/console doctrine:migrations:migrate` y, si tu base no la tiene, la migración inicial del legado.
3. `bin/console app:sincronizacion:instalar-legado` contra producción, en horas de poca venta: crea las tablas `sync_*` y los triggers, que solo anotan ids. **No** se despliega el bundle en el legado.
4. Con `programador` y `worker` arriba (el ciclo corre cada minuto), encender (indicador o `interruptor encender`). El indicador dice «Sinc. (solo trae)».

En este modo FDN no escribe en el legado fuera de `sync_*` (`ConexionLegado` rechaza la sentencia), deja `sync_config.activo = 0` (el legado no llama aquí), no exporta y la conciliación solo trae. Lo que vendas aquí queda solo aquí: si el legado vende después el mismo asiento, la importación de esa salida falla y lo muestra el indicador. Mejor no vender en local en salidas de producción.

Solo un FDN puede leer el `sync_cambio` del legado (lo marca como procesado). Antes de conectar el FDN de producción, desinstalar (`--desinstalar`) y volver a instalar desde él. Mientras tu FDN está apagado, el legado sigue anotando; al volver se pone al día.

## Si algo falla

- **El legado no responde**: aquí no se venden asientos de salidas que existen en los dos sistemas (error «No se pudo confirmar el asiento en el sistema anterior»). Lo demás queda en `sync_cambio` y se reintenta. Apagar la sincronización deja vender aquí sin comprobar.
- **FDN no responde**: el legado vende igual (`si_falla = permitir`, el defecto) y FDN lo trae al volver; correr luego `app:sincronizacion:conciliar`. Para que el legado no venda sin comprobar: `bin/console app:sincronizacion:instalar-legado --si-falla=bloquear`.
- **Indicador ámbar o rojo**: `bin/console app:sincronizacion:interruptor` muestra el último ciclo, su error y lo pendiente; `SELECT * FROM sync_cambio WHERE procesado_en IS NULL` (aquí) y `SELECT * FROM sync_cambio WHERE procesado IS NULL` (legado) muestran cada cambio con su error.

## Cómo se quita

Ver «Cómo se quita» en el ADR-042.
