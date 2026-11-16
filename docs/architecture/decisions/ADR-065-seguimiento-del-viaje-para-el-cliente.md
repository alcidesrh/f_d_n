# ADR-065: «¿Dónde va mi bus?» — seguimiento del viaje para el cliente

**Estado:** Aceptada (amplía [ADR-059](ADR-059-modificacion-de-compras-por-el-cliente.md); usa [ADR-064](ADR-064-hora-estimada-de-llegada.md))

## Contexto

El cliente no sabía nada de su bus entre la compra y el abordaje. El sistema ya tiene la posición del
bus y, con ADR-064, la hora estimada por parada.

## Decisión

- `GET /api/publico/mis-compras/viajes/{id}/seguimiento`, con la llave del cliente (`X-Llave-Cliente`)
  y el límite de peticiones público: solo ve los viajes de sus compras.
- `App\Venta\EnLinea\SeguimientoViaje`: por cada salida y trayecto de la venta, las paradas **de donde
  sube a donde baja** (no el resto de la ruta) con su hora del horario y la estimada, el atraso, la
  hora de la lectura y la posición del bus.
- Se muestra desde 3 horas antes de la hora de subida hasta 6 horas después de la de llegada; fuera
  de eso responde `visible: false` sin posición ni atraso.
- Página: `componentes/SeguimientoViaje.vue` dentro de la tarjeta del viaje en «Mis compras». Dice
  «A tiempo», «N min de atraso» o «Según horario» (sin GPS), la hora en su parada y en su destino, y
  abre la posición en el mapa del teléfono. Se actualiza cada minuto. En los cinco idiomas.

## Consecuencias

- La posición del bus la ve quien tiene un boleto de esa salida, y solo alrededor del viaje.
- No hay mapa propio en la página (no carga MapLibre): el enlace abre Google Maps. Queda pendiente
  si se quiere el mapa embebido.
- Los avisos al pasajero (recordatorio, bus asignado, retraso) no están en esta decisión: falta
  elegir el canal (correo, WhatsApp o SMS) y registrar qué se envió.
