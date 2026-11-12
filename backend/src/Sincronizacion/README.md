# Sincronización con el legado (temporal)

Mantiene iguales este sistema y el legado (TerminalOmnibus, SQL Server)
mientras las estaciones se mudan. Diseño y límites: [ADR-042](../../../docs/architecture/decisions/ADR-042-sincronizacion-temporal-con-el-legado.md).

| Pieza | Qué hace |
| --- | --- |
| `Interruptor` | Encender / apagar (`sync_estado`, copiado a `sync_config` del legado) y el estado del indicador. |
| `Nuevo/VigilanteCambios` | Listener del flush: lleva al legado lo que cambia aquí (el asiento, antes del commit). |
| `Nuevo/EscritorLegado` | Escribe en SQL Server: boletos, estados, clientes, facturas, encomiendas. |
| `Nuevo/FacturaParaLegado` | Cómo queda allá la factura de una venta de varios boletos: una `factura_generada` por boleto, el total solo en la del de menor id, `boletos_ticket`; anulaciones y reasignaciones. |
| `Nuevo/AltaCliente`, `Nuevo/IdCliente` | Un cliente nuevo se crea primero en el legado y toma su id. |
| `Nuevo/ClientesLocales` | Los clientes creados con la sincronización apagada: al encender se llevan allá y pasan a su id. |
| `Nuevo/ExportadorCambios`, `Nuevo/AlTerminar` | Lo que quedó en `sync_cambio` de aquí, al terminar la petición o en el ciclo. |
| `Legado/ImportadorLegado`, `Legado/ActualizadorBoletos` | Trae lo que anotaron los triggers del legado. De lo que nació aquí, solo lo que el legado todavía le hace (chequeo, tránsito; movimientos de encomienda): los triggers `trg_sync_proteger_*` no le dejan anularlo ni reasignarlo. |
| `Legado/Importacion/` | El motor que escribe aquí una salida del legado con su árbol (bus, ruta, boletos, ventas, facturas, encomiendas) y los puestos y alcances de sus usuarios. Era `App\Migration`; la migración masiva ya se hizo y se quitó (ADR-056). |
| `Legado/ApartadosLegado` | Aparta aquí lo que el legado está por vender (`/api/sincronizacion/legado/*`). |
| `Ciclo`, `Conciliador` | La vuelta de cada minuto y la comparación de un día. |
| `BarrerSalidasLegado` | Cada 30 minutos, las salidas del legado que faltan aquí: las trae y deja anotadas las que no pudo. |
| `Legado/sql/*.sql` | Tablas `sync_*` y triggers del legado. |

## Puesta en marcha (por primera vez)

1. Variables de FDN: en producción en `.env.prod.local`, en la raíz del repo (plantilla en `.env.prod.dist`; se aplican con `make prod`, porque `make reiniciar` no vuelve a leer el archivo); en desarrollo y con `make vps`, en `backend/.env.local`.
   - `SINCRONIZACION_CLAVE`: una clave larga al azar (`openssl rand -hex 32`).
   - `SINCRONIZACION_URL_FDN`: la URL de FDN vista **desde el servidor del legado** (p. ej. `https://fdn.ejemplo.com`).
   - Opcional: `SINCRONIZACION_USUARIO_LEGADO`, id de `custom_user` del legado que firma lo que hagan aquí usuarios sin par allá.
   - La conexión al legado es la de siempre (`systemfdn_*`).
2. `bin/console doctrine:migrations:migrate` (tablas `sync_*` de aquí).
3. `bin/console app:sincronizacion:instalar-legado`: tablas y triggers en el legado, y la URL y la clave en su `sync_config`. Los `trg_sync_*` solo anotan ids; los `trg_sync_proteger_*` rechazan que el legado anule o reasigne un boleto, su factura o una encomienda `FDN:` (errores 51001–51003; la sincronización sí puede). Se puede repetir, en horas de poca venta: recrea los triggers y lo que pase en ese instante no se anota. **Copia las dos variables tal como estén: si una está vacía, borra la que haya en `sync_config`.**
4. En el legado: desplegar `src/Acme/SincronizacionFdnBundle` y su línea en `app/AppKernel.php`; limpiar la caché (`php app/console cache:clear --env=prod`). Con el interruptor apagado no hace nada.
5. El ciclo lo corre cada minuto el programador de tareas (ADR-044): basta con que estén arriba `programador` y `worker` (`make tareas` muestra `sincronizar_legado`).
6. Encender: desde el indicador de la cabecera (quien tiene `config.editar`) o `bin/console app:sincronizacion:interruptor encender`.
7. Al día siguiente, y cuando haga falta: `bin/console app:sincronizacion:conciliar -v` (y `--reparar`).

Para un piloto con una estación basta con encenderla: los dos sistemas venden
todo; lo que cambia es dónde trabaja cada estación.

## Actualizar una instalación con el rango de ids

Las versiones anteriores daban ids desde 1.000.000.000 a lo creado aquí. Para pasar a esta:

1. Apagar la sincronización.
2. Desplegar con la migración `Version20261105160000`: los clientes con par en `sync_vinculo` pasan al id del legado y los demás del rango quedan en `sync_cliente_local`. Los usuarios del rango conservan su id.
3. `bin/console app:sincronizacion:instalar-legado`, para el trigger de `cliente`.
4. Encender: los clientes pendientes se llevan al legado. Si no pasan todos, lo dice el aviso y sigue el ciclo.

## FDN de desarrollo contra el legado de producción («solo traer»)

Para ver en un FDN local lo que pasa en producción sin tocar sus ventas:

1. En el `.env.local` de tu FDN: `systemfdn_*` del legado de producción y `SINCRONIZACION_SOLO_TRAER=1`. `SINCRONIZACION_CLAVE` y `SINCRONIZACION_URL_FDN` no se usan en este modo, pero instalar las copia a `sync_config`: si el legado ya las tiene porque lo usa otro FDN, no instales desde aquí sin ponerles los mismos valores.
2. `bin/console doctrine:migrations:migrate`. La base tiene que venir de un respaldo de una ya migrada: la migración inicial del legado ya no existe (ADR-056) y la sincronización solo trae lo que cambia.
3. `bin/console app:sincronizacion:instalar-legado` contra producción, en horas de poca venta: crea las tablas `sync_*` y los triggers (los que anotan ids y los que protegen lo `FDN:`, que en este modo no hay). **No** se despliega el bundle en el legado.
4. Con `programador` y `worker` arriba (el ciclo corre cada minuto), encender (indicador o `interruptor encender`). El indicador dice «Sinc. (solo trae)».

En este modo FDN no escribe en el legado fuera de `sync_*` (`ConexionLegado` rechaza la sentencia), deja `sync_config.activo = 0` (el legado no llama aquí), no exporta y la conciliación solo trae. Encendida no se crean clientes aquí; los que se crearon apagada no se llevan allá y su id puede coincidir con el de otra persona al importar (el interruptor avisa cuántos hay). Lo que vendas aquí queda solo aquí: si el legado vende después el mismo asiento, la importación de esa salida falla y lo muestra el indicador. Mejor no vender en local en salidas de producción.

Solo un FDN puede leer el `sync_cambio` del legado (lo marca como procesado). Antes de conectar el FDN de producción, desinstalar (`--desinstalar`) y volver a instalar desde él. Mientras tu FDN está apagado, el legado sigue anotando; al volver se pone al día.

## Si algo falla

- **El legado no responde**: aquí no se venden asientos de salidas que existen en los dos sistemas (error «No se pudo confirmar el asiento en el sistema anterior») ni se crean clientes. Lo demás queda en `sync_cambio` y se reintenta. Apagar la sincronización deja vender aquí sin comprobar y crear clientes, que pasan al legado al encender.
- **«Hay N clientes de aquí sin llevar al legado»**: el ciclo no trae nada hasta llevarlos. `SELECT * FROM sync_cliente_local` los muestra; se reintenta en cada ciclo.
- **FDN no responde**: el legado vende igual (`si_falla = permitir`, el defecto) y FDN lo trae al volver; correr luego `app:sincronizacion:conciliar`. Para que el legado no venda sin comprobar: `bin/console app:sincronizacion:instalar-legado --si-falla=bloquear`.
- **«N salidas con boletos del sistema anterior sin traer»**: el barrido no pudo traerlas, casi siempre porque no hay bus que inferir (ADR-027). Se reintentan solas cada 30 minutos; asignarles un bus en el legado las trae en el siguiente ciclo. `app:sincronizacion:interruptor` las lista en `salidasSinTraer`, con su motivo; `make tareas t=barrer_salidas_legado` corre un barrido ya.
- **«Ventas de aquí con su factura sin cuadrar allá»** (conciliación): filas de `factura_generada` que faltan, el total repetido en varias o el `boletos_ticket` que falta. `--reparar` las vuelve a llevar; una que no tiene serie del legado para su empresa queda con los datos del DTE en la observación del boleto y se sigue mostrando.
- **Indicador ámbar o rojo**: `bin/console app:sincronizacion:interruptor` muestra el último ciclo, su error y lo pendiente; `SELECT * FROM sync_cambio WHERE procesado_en IS NULL` (aquí) y `SELECT * FROM sync_cambio WHERE procesado IS NULL` (legado) muestran cada cambio con su error.

## Cómo se quita

Ver «Cómo se quita» en el ADR-042.
