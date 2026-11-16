# ADR-065: Mantenimiento de flota por kilómetros y por fecha

**Estado:** Aceptada (usa las lecturas de [ADR-050](ADR-050-historial-gps-y-reproduccion-de-trayectorias.md), el programador de [ADR-044](ADR-044-programador-de-tareas.md) y el CRUD genérico de [ADR-039](ADR-039-accesos-con-alcance.md))

## Contexto

El sistema sabía qué bus hace cada salida, pero no cuánto ha rodado ni cuándo le toca taller. El
mantenimiento se llevaba fuera, y nada avisaba al programar una salida con un bus que ya debía un
servicio. Las lecturas GPS de cada bus ya se guardan (ADR-050): alcanzan para llevar el odómetro.

## Decisión

**Odómetro.** `Bus.odometroKm` se escribe a mano en el bus (para igualarlo con el tablero) y
`App\Mantenimiento\Odometros` le suma cada madrugada lo que el bus hizo según el GPS. Cada bus
recuerda hasta qué lectura ya se sumó (`odometroAl`); la corrida siguiente arranca en esa misma
lectura. `Kilometraje` (puro, con tests) suma tramos en línea recta con dos cuidados: el bus detenido
no suma el temblor del GPS (un tramo cuenta al alejarse 50 m del último punto contado) y se descarta
la lectura a la que no pudo llegar (más de 150 km/h).

**Planes y órdenes.** `PlanMantenimiento` (nombre, `cadaKm`, `cadaDias`, activo) es un servicio que
se le hace a toda la flota cada tanto. `OrdenMantenimiento` (bus, plan opcional, fecha, odómetro,
costo en quetzales, taller, descripción) es un trabajo hecho: con plan, desde ella se cuenta el
siguiente; sin plan, es una reparación suelta. Las dos se mantienen por el CRUD genérico
(capacidades `plan_mantenimiento.*`, de lectura libre, y `orden_mantenimiento.*`, acotada por la
empresa del bus).

**Estado.** `ReglasMantenimiento` (puro, con tests): un plan en un bus está **vencido** al cumplirse
los kilómetros o los días desde su última orden (lo que llegue primero), **por vencer** cuando le
falta el 10 % del intervalo o menos, **al día**, o **sin registro** si nunca se le hizo. «Sin
registro» no cuenta como vencido: al estrenar el módulo no hay historia y toda la flota quedaría
en rojo.

- `GET /api/mantenimiento/estado` (`orden_mantenimiento.ver`, buses del alcance) →
  `{ generado, planes, buses: [{ id, codigo, matricula, empresaId, odometroKm, estado, planes: [{ plan, estado, faltanKm, faltanDias, ultimaFecha, ultimoKm }] }], puede }`.
  Pantalla `/flota/mantenimiento`: los buses por urgencia, con «Registrar» (la orden nueva con el bus
  y el plan puestos).
- Tarea `mantenimiento_flota` (03:30): suma los odómetros y avisa por el canal del sistema del chat
  lo que entra en «por vencer» o «vencido», a quien tiene `orden_mantenimiento.editar` en la empresa
  del bus. Cada servicio se avisa una vez por estado (`aviso_mantenimiento`); al volver a estar al
  día se olvida.
- Programar o editar una salida: `GET /api/salidas/opciones` marca `mantenimiento: "vencido"` en el
  bus y el formulario lo advierte. **No lo impide**: quien programa decide.

## Consecuencias

- El kilometraje por GPS se queda corto, nunca largo: entre lecturas separadas mide la cuerda, y un
  bus sin GPS no suma. Por eso el odómetro se corrige a mano; lo del GPS se sigue sumando encima.
- Corre antes de la purga de lecturas (03:45) y solo mira lecturas nuevas: los 90 días de retención
  no le afectan. Un bus que nunca se sumó arranca un día atrás, no desde su primera lectura.
- Los planes valen para toda la flota. Si hace falta uno por marca o clase de bus, es un campo más
  en el plan y un filtro en `EstadoFlota`.
- La orden registra lo hecho; no hay orden «abierta» ni repuestos ni inventario.
- Corregir el odómetro hacia abajo no vuelve a avisar un servicio ya avisado como vencido.
