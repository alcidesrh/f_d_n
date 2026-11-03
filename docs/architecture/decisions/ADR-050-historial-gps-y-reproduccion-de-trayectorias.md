# ADR-050: Historial GPS y reproducción de trayectorias

**Estado:** Aceptada

## Contexto

El seguimiento en tiempo real (ADR-025) recibe las lecturas de los teléfonos con Traccar Client en `/api/seguimiento/gps`, pero solo guarda la última de cada bus (en `cache.gps`, 10 min). El mapa en vivo dibuja la estela que va dejando el bus solo mientras la página está abierta. Operaciones necesita ver después por dónde fue un bus en una salida, a qué velocidad y dónde se detuvo: guardar las lecturas y reproducirlas en el mapa.

## Decisión

### Qué se guarda: lecturas por bus, no por salida

Cada trama aceptada se guarda además en `lectura_gps` (entidad `LecturaGps`, sin GraphQL, solo se agrega por DBAL en `App\Seguimiento\HistorialGps`): bus, instante (`timestamptz`), latitud, longitud, rumbo y velocidad en km/h. La clave primaria es `(bus_id, instante)`: lo que Traccar reenvía de su cola no se repite (`ON CONFLICT DO NOTHING`), y la misma clave sirve para leer la trayectoria de un bus entre dos instantes y para purgar. Un código que no es de ningún bus no se guarda (sí sigue en la caché del mapa en vivo, como hasta ahora).

La lectura no se asocia a una salida al llegar: se guarda por bus y la salida se reconoce al leer, por su horario. Así una lectura atrasada (Traccar reenvía horas después), un cambio de bus de la salida (ADR-031) o una corrección de horario no dejan lecturas en la salida equivocada, y la escritura no consulta salidas en cada trama.

**Bus quieto.** Con el bus detenido (< 3 km/h) a menos de ~55 m de la última lectura guardada también detenida, se guarda a lo sumo una cada 2 min (`HistorialGps::QUIETO_SEGUNDOS`): un teléfono encendido toda la noche en el predio no llena la tabla. En marcha se guarda todo.

**El historial no frena el mapa en vivo.** Si guardar en el historial falla, el endpoint responde 200 igual (y lo anota en el log): con un error, Traccar reenviaría la trama y el mapa en vivo se atrasaría.

### Qué lecturas son de una salida (`VentanaTrayectoria`)

Desde 30 min antes de la hora de salida (espera y abordaje en el origen) hasta 3 h después de la llegada programada (la hora del horario en la última parada), sin pasar de 30 min antes de la siguiente salida no cancelada del mismo bus (donde empieza la ventana de esa). Con un horario apretado, al menos hasta la llegada programada. Sin llegada programada válida (igual o anterior a la salida), se suponen 12 h, acotadas por la siguiente salida.

### API (permiso `salida.ver`, acotado por empresa)

- `GET /api/seguimiento/trayectorias?fecha=AAAA-MM-DD` (hoy si falta): las salidas que salen ese día con lecturas en su ventana (hora, ruta, bus, cuántas lecturas, primera y última) y cuántas no tienen. Una consulta para las salidas del día y otra (`unnest` de las ventanas) para contar.
- `GET /api/seguimiento/salidas/{id}/trayectoria`: datos de la salida, la ventana, las lecturas compactas `[instante, lat, lng, km/h, rumbo]` y la ruta planificada con sus estaciones (`TrazadoDeRuta`, como el mapa en vivo; vacía si no se pueden ubicar). 404 si no existe, 403 si es de una empresa que el usuario no ve.

### Purga

Tarea `purgar_lecturas_gps` (ADR-044), diaria a las 03:45: borra las lecturas de más de 90 días (`PurgarLecturasGps::ANTIGUEDAD`), bus por bus para que cada borrado use la clave.

### Frontend

Ruta `/seguimiento/reproducir` (`features/seguimiento/ReproduccionPage.vue`, `?salida=` abre una; con `meta.label`/`icon` para el sidebar): día, salida con GPS, mapa (Leaflet) con lo recorrido tenue, la ruta planificada en raya y sus estaciones; el bus avanza con el reloj a ×10–×300, la barra lleva a cualquier momento y «Seguir al bus» mueve el mapa. Un hueco de más de 5 min sin lecturas se dibuja en raya gris, no se interpola y se salta al reproducir. Lógica pura con tests en `core/seguimiento/reproduccion.ts` (interpolación, km recorridos, huecos). En el mapa en vivo, un bus con GPS ofrece «Reproducir lo recorrido». El marcador del bus es común a ambas pantallas (`features/seguimiento/marcador.ts`).

## Consecuencias

- Volumen: ~100 B por lectura con sus índices. Con 100 buses enviando cada 10 s unas 12 h al día en marcha, ~500 mil lecturas por día y ~45 millones en los 90 días (~4–5 GB). La frecuencia de Traccar Client y `ANTIGUEDAD` son las palancas.
- Doctrine crea además el índice de la clave foránea (`bus_id`), redundante con la clave primaria; se deja para que los diffs de esquema no lo vuelvan a pedir.
- Los km recorridos de la reproducción son los del GPS (suma de distancias entre lecturas), no los `kilometros` de la ruta.
- Las salidas de un mismo bus con horarios que se solapan (datos inferidos del legado, ADR-027) pueden compartir lecturas en el borde de sus ventanas.
