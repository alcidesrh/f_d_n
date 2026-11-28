# ADR-073: Costo de combustible por salida

**Estado:** Aceptada

## Contexto

Para saber cuánto cuesta una salida hace falta su gasto. Solo el combustible se puede calcular hoy: la
distancia de la ruta ya existe (`Parada.km`, `Ruta.kilometros`, ADR-030 y ADR-055), pero ni el precio del
combustible ni lo que rinde cada bus estaban en el sistema.

El precio que interesa es el del mercado nacional del día. El Ministerio de Energía y Minas publica
referencias semanales en PDF, no una fuente diaria que el sistema pueda leer sola, y sus cifras son de
terminal (ex-planta), no de bomba. Los buses tampoco tienen medido su rendimiento.

## Decisión

- **Costo** de una salida = `km de su ruta ÷ km por galón de su bus × precio del galón de su día`, en
  quetzales, redondeado a centavos. Solo combustible: pilotos, peajes y mantenimiento quedan fuera.
  La unidad es el galón, como las facturas de Forcon.
- **Precio** (`PrecioCombustible`): un registro por día y tipo (diésel, regular, superior), con su fuente
  en texto libre. Lo carga quien tiene `combustible.precios` por el CRUD genérico. No hay importación
  automática: si aparece una fuente diaria legible, se añade como tarea del programador (ADR-044).
- **Precio del día de la salida.** Si no hay precio de ese día, se usa el último anterior y el costo sale
  marcado `precioEstimado`. Una salida futura, cuyo día aún no tiene precio, se estima con el último que
  haya. Sin ningún precio, no hay costo (`motivo: sin_precio`).
- **Rendimiento** (`Bus.rendimientoKmGal`): 5,00 km/gal por defecto. Es un valor de partida; se mide con
  las cargas de combustible y el odómetro (ADR-065) y se corrige bus por bus. El tipo de combustible de
  cada bus (`Bus.combustible`) es diésel por defecto.
- **Sin guardar copia.** El costo se calcula al consultar la salida, no se guarda en ella. Así una salida
  programada muestra su costo estimado y el precio de su día entra cuando llega. El precio de un día no
  cambia, pero un nuevo rendimiento sí reprecia las salidas pasadas. Si eso importa, se pasará a guardar
  el costo como `SalidaParada` guarda las horas (ADR-030).
- **Dónde se ve.** `GET /api/gestion-salidas/{id}/detalle` trae `costoCombustible` (el cálculo puro está
  en `App\Combustible\CostoCombustible`, con tests), y el diálogo «Ver» de la salida lo muestra.

## Consecuencias

- Los km son los de la ruta: no incluyen el recorrido en vacío del bus hacia el origen ni los desvíos. Si
  pesan, el costo se queda corto.
- Hasta que se carguen precios, ninguna salida tiene costo. El diálogo dice por qué.
- Migración `Version20261128102412`: tabla `precio_combustible`; en `bus`, `rendimiento_km_gal` (5,00) y
  `combustible` (diésel) para todos los buses existentes.
- Pendiente: listado de salidas con su costo, medir el rendimiento con cargas de combustible, y una fuente
  diaria del precio si el MEM o un tercero la publica.
