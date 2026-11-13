# ADR-061: Irregularidades del GPS avisadas por el chat

**Estado:** Aceptada.

## Contexto

El seguimiento ya muestra los buses en el mapa (ADR-025, ADR-055) y guarda y reproduce la trayectoria de
cada salida con sus velocidades (ADR-050). Nadie mira el mapa todo el día: lo que faltaba es que el
sistema avise cuando el GPS muestra algo que pide atención. Se eligieron dos casos que dependen solo de
lecturas confiables: exceso de velocidad y parada larga. Bus sin señal y desvío de la ruta quedaron fuera
por ahora: en carretera sin cobertura y con trazados aproximados darían avisos falsos.

## Decisión

- **Reglas puras** en `App\Seguimiento\Irregularidades::detectar(lecturas, paradas, umbrales)`:
  - *Exceso de velocidad*: lecturas seguidas por encima del límite durante al menos unos minutos. Un pico
    suelto no cuenta (ruido del GPS, un rebase).
  - *Parada larga*: detenido (menos de 3 km/h, como `HistorialGps`) fuera del radio de toda parada de la
    ruta durante al menos el tiempo fijado.
  - Un hueco sin lecturas corta el tramo: no se supone qué hizo el bus sin señal.
  - Un tramo se da en cuanto cumple el umbral, aunque siga, y su clave (tipo + instante en que empezó) no
    cambia mientras dura.
- **Umbrales** (`UmbralesIrregularidad`): 90 km/h sostenidos 3 minutos; 20 minutos detenido a más de 500 m
  de una parada; 5 minutos sin lecturas cortan. Los tres primeros se cambian con
  `SEGUIMIENTO_VELOCIDAD_MAXIMA_KMH`, `SEGUIMIENTO_EXCESO_MINUTOS` y `SEGUIMIENTO_PARADA_LARGA_MINUTOS`.
- **`IrregularidadesEnCurso`** las busca en las salidas cuya ventana de trayectoria incluye el momento
  (`Trayectorias::enCurso`), con las lecturas de las últimas dos horas de cada bus, y deja solo lo de ahora
  (seguía hace menos de 15 minutos). Si no se pudo ubicar ninguna parada de la ruta, no da paradas largas.
- **Se avisa como mensaje predeterminado** (ADR-045), tipo `anomalia_gps` «Irregularidad en la trayectoria
  (GPS)»: un administrador crea la instancia, elige la audiencia y, si quiere, la empresa. Sale con los
  envíos de cada 5 minutos (no en la revisión de la madrugada: `enVivo()`), un mensaje por irregularidad y
  una sola vez por instancia (`gps:{salida}:{tipo}:{instante}`). Crear la instancia no avisa lo anterior.
  Con las notificaciones activas (Web Push, ADR-060) llega también con la app cerrada.

## Consecuencias

- Sin migración: el tipo se guarda como texto en `mensaje_predeterminado.tipo`.
- Los umbrales son del servidor, iguales para todas las empresas y rutas. Si hacen falta por ruta o por
  clase de bus, pasan a la instancia del mensaje.
- El aviso llega con hasta 5 minutos de retraso respecto a que se cumpla el umbral.
- Depende de que el bus envíe GPS: sin lecturas no hay aviso (y eso mismo no se avisa todavía).
- Las paradas de la ruta sin coordenadas se ubican por aproximación (`TrazadoDeRuta`): una parada real
  mal ubicada puede salir como parada larga. Se corrige poniéndole coordenadas al enclave.
