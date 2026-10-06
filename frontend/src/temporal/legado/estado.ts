/**
 * TEMPORAL — prueba de la venta y los reportes con datos reales del sistema anterior.
 * Para quitarlo: borrar `src/temporal/`, las líneas marcadas `TEMPORAL-LEGADO` (core/venta/api.ts,
 * core/reporte/api.ts, VentaPage.vue y las dos páginas de reporte) y, en el backend,
 * `Controller/PruebaLegadoController.php` + `src/PruebaLegado/`.
 *
 * Con la opción activa las pantallas piden lo mismo a `/api/prueba-legado/*` (solo lectura; la
 * venta es una simulación). No se recuerda entre recargas, a propósito.
 */
import { ref } from 'vue'

export const usarLegado = ref(false)

/** Prefijo de la API según la fuente elegida. */
export const prefijoLegado = () => (usarLegado.value ? '/prueba-legado' : '')
