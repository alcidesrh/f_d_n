# ADR-024: Gestión de salidas: programador, esquemas y cambios con propagación

**Estado:** Aceptada

## Contexto

Las salidas se creaban de una en una por el CRUD genérico (GraphQL). La operación logística necesita:

- Programar de una vez las salidas de un trayecto: varias horas al día, cada una con su bus, un día o repetidas N días (consecutivos o cada X días) o hasta una fecha.
- Guardar esa configuración con un nombre (esquema) para reutilizarla, y cambiarla o borrarla después.
- Editar (trayecto, bus, fecha/hora), anular o eliminar una salida y, si se pide, hacer lo mismo con las futuras "idénticas" sin tocar las que ya tienen asientos vendidos.
- Un listado que por defecto muestre primero lo que está en curso y luego lo más próximo.

## Decisión

### Módulo `App\Salida` + REST `/api/gestion-salidas`

Endpoints propios (Symfony, como `App\Venta`) bajo `/api/gestion-salidas` para no chocar con el recurso `Salida` de API Platform, que sigue igual. Errores `{ error, codigo }` (`SalidaRechazada`). Permisos: `salida.ver` (listado), `salida.crear` (programar y esquemas), `salida.editar`, `salida.cancelar` (anular) y `salida.eliminar` (nuevo); permiso "Gestion Salidas" con las cinco.

### Programador

- `Programacion` (puro) valida y despliega `{ trayectoId, momentos: [{ hora, busId }], desde, hasta?, intervaloDias }`: hasta 48 horas por día, intervalo 1–60, un año como máximo, 3000 salidas por vez, nada en días pasados.
- `ProgramadorSalidas` clasifica cada salida antes de crearla: `nueva`, `existe` (mismo trayecto, bus y hora: no se duplica), `conflicto` (el bus está en otro viaje según la duración estimada del trayecto; `AgendaBus`, puro) o `pasada`. La vista previa y la creación usan el mismo plan; al crear se recalcula en una transacción que bloquea la fila del trayecto (dos usuarios no duplican) y solo se crean las nuevas. La empresa de cada salida es la del bus.
- El frontend pide la vista previa sola cada vez que el formulario es válido; "N días" se convierte en la fecha final en el cliente.

### Esquemas (`EsquemaSalida`, `EsquemaSalidaMomento`)

Nombre, trayecto, intervalo y horas con su bus; sin fechas (los días se eligen cada vez). Se crean al programar (`guardarComo`) y en el mismo programador se cargan, cambian (buses, horas, quitar horas) o borran. Son plantillas: cambiarlos no toca salidas ya creadas. Aislados por empresa (`TenantFilter`); nombre único por empresa.

### Editar, anular y eliminar con propagación

- **Idénticas** (`FirmaSalida`): mismo trayecto, bus, empresa y hora del día; solo cambia el día. Se propagan solo a las futuras que siguen programadas.
- Las salidas afectadas se bloquean (`PESSIMISTIC_WRITE`, como en la venta) y se recuentan los **asientos comprometidos** (`AsientosVendidos`: boletos vivos + reservas web vigentes). Las que tienen alguno no se tocan y vuelven en `omitidas` (`motivo: asientos`) para que el usuario reasigne o anule esos asientos a mano; lo mismo para la propia salida.
- Editar: la salida toma la fecha completa; las idénticas conservan su día y toman la hora, el trayecto y el bus. Si el bus queda en otro viaje a esa hora: `omitidas` con `motivo: conflicto`.
- Anular = estado `cancelada` (sigue en la base de datos). Solo desde `programada`.
- Eliminar: solo programadas o canceladas, y nunca con historial de boletos (aunque estén anulados; `motivo: historial` → se anula).
- `GET /{id}/propagacion` dice de antemano cuántas idénticas hay y cuántas tienen asientos.

### Listado

`FiltroSalidas`: por defecto sin finalizadas ni canceladas; orden "próximas" = iniciadas, luego abordando, luego programadas por fecha ascendente (la más lejana al final). Filtros: estados (con conteo por estado), empresa, trayecto, origen, destino, bus, rango de fechas; orden por fecha opcional. Cada fila trae asientos comprometidos y capacidad del bus.

## Consecuencias

- La salida "atrasada" (programada cuya hora ya pasó) solo se marca en el listado; nadie la mueve de estado automáticamente.
- Sin duración estimada en el trayecto, la detección de choques solo ve el mismo bus a la misma hora.
- Las rutas `/salidas` y `/salidas/programar` se sincronizan como `VueRoute`; hay que agregarlas a un menú en `/configuracion/menus`.
