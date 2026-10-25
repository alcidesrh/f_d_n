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
5. Levantar el ciclo: `docker compose -f compose.yaml -f compose.prod.yaml -f compose.sincronizacion.yaml up -d sincronizacion`.
6. Encender: desde el indicador de la cabecera (quien tiene `config.editar`) o `bin/console app:sincronizacion:interruptor encender`.
7. Al día siguiente, y cuando haga falta: `bin/console app:sincronizacion:conciliar -v` (y `--reparar`).

Para un piloto con una estación basta con encenderla: los dos sistemas venden
todo; lo que cambia es dónde trabaja cada estación.

## Si algo falla

- **El legado no responde**: aquí no se venden asientos de salidas que existen en los dos sistemas (error «No se pudo confirmar el asiento en el sistema anterior»). Lo demás queda en `sync_cambio` y se reintenta. Apagar la sincronización deja vender aquí sin comprobar.
- **FDN no responde**: el legado no vende (con `si_falla = bloquear`). Para que siga vendiendo: `bin/console app:sincronizacion:instalar-legado --si-falla=permitir` o, desde SQL Server, `UPDATE sync_config SET si_falla = 'permitir'` (o `activo = 0`).
- **Indicador ámbar o rojo**: `bin/console app:sincronizacion:interruptor` muestra el último ciclo, su error y lo pendiente; `SELECT * FROM sync_cambio WHERE procesado_en IS NULL` (aquí) y `SELECT * FROM sync_cambio WHERE procesado IS NULL` (legado) muestran cada cambio con su error.

## Cómo se quita

Ver «Cómo se quita» en el ADR-042.
