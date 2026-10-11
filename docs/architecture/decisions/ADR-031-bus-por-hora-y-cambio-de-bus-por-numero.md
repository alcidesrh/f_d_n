# ADR-031: Bus por hora en el programador y cambio de bus con boletos por número de asiento

**Estado:** Aceptada (2026-10-11). Completa ADR-027.

## Contexto

Tras ADR-030, el programador de salidas mostraba arriba un filtro de distribución y clase de bus común a todas las horas. Pero una misma ruta sale con buses muy distintos: en las salidas futuras de la ruta 1 hay 9 clases y 7 distribuciones. El filtro no ayudaba a elegir el bus de cada hora, no se guardaba en el esquema y podía contradecir los buses ya elegidos. Además, la clase del bus ya cambia los minutos entre paradas (`ParadaTiempo`), pero los choques se calculaban siempre con la clase más lenta.

El personal está acostumbrado a crear salidas sin bus y a asignarlo cerca de partir. Se analizó volver a permitirlo:

- **Salida sin bus que no se vende:** no sirve, porque la salida existe para venderse.
- **Vender sobre el croquis y asignar el asiento al poner el bus:** el molde sí garantiza la numeración. Su firma (`Croquis::firma`) incluye celda, número y clase de cada asiento, así que dos buses del mismo molde tienen los mismos números en los mismos lugares. Pero obliga a que boletos, reservas, la exclusión `ex_boleto_asiento_tramo` y el croquis de taquilla trabajen sin asiento real. También deja la tarifa sin clase de bus (se cobraría la menos específica) y no resuelve el caso en que el bus final tiene otro molde.

La ventaja real de crear sin bus es aplazar la decisión del bus concreto. Se consigue igual si cambiar el bus por otro del mismo molde no cuesta nada aunque haya boletos vendidos.

## Decisión

- **Sigue la regla de ADR-027: toda salida tiene bus.**
- **El programador elige por hora:** cada fila tiene hora, distribución (galería de moldes, `features/salida/CroquisSelector.vue`, con buscador y plantas fijos) y bus con esa distribución. Al elegir un bus, la fila toma su distribución; si se cambia la distribución y el bus no la tiene, el bus se quita. Se retira el filtro global (`FiltroBus.vue`). La distribución de la fila no se guarda: es la del bus (también al cargar un esquema).
- **Empresa como filtro:** si el usuario no tiene empresa (ve toda la flota), puede elegir una. Así solo se ofrecen las distribuciones que tiene algún bus de esa empresa (siempre hay un bus que asignar) y solo sus buses. `opciones.miEmpresaId` dice si hace falta. La salida es de la empresa de su bus, como antes.
- **Cambio de bus con asientos vendidos:** se permite si el bus nuevo tiene el **mismo molde** y la salida no cambia de ruta ni de hora.
  - Boletos (de cualquier estado) y reservas pasan al asiento del mismo número del bus nuevo (`GestionSalidas::pasarAsientos`, como ya hacía la migración con el bus inferido). Si faltara un número, se deshace todo.
  - Vale también al propagar a las idénticas. Con otro molde, la salida se omite (`otro_croquis`); cualquier otro cambio con asientos, como antes (`asientos`).
  - La bitácora de la salida registra el cambio de bus con `asientosPorNumero` y se avisa la ocupación por Mercure.
- **Duración por clase de bus:** `Ruta::duracionPara(?BusClase)` toma los minutos de la clase o, si no tiene, los generales. La usan los choques del programador, de la edición y `AgendaFlota`. Las opciones de ruta traen `duracionGeneral` y `duracionPorClase`, y cada fila del programador muestra la hora de llegada con la clase de su bus. `getDuracionMinutos()` sigue siendo la de la clase más lenta.

## Consecuencias

- Asignar un bus al programar deja de ser un compromiso: se pone uno con la distribución prevista y se cambia después, aun con boletos vendidos, sin reasignar a mano.
- Los boletos vendidos conservan su número de asiento y su precio aunque la clase del bus nuevo sea otra. El precio de lo ya vendido no se recalcula.
- La migración no cambia: sigue infiriendo el bus de las salidas del legado que no lo tienen (ADR-027).
