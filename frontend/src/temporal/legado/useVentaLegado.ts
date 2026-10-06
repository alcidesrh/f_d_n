/** TEMPORAL (ver `estado.ts`): estaciones y recarga de la pantalla de venta al cambiar de fuente. */
import { computed, ref } from 'vue'
import { http } from '@/core/http'
import type { useVentaStore } from '@/features/venta/store'
import { usarLegado } from './estado'

interface EstacionLegado {
  id: number
  nombre: string
  direccion: string | null
  departamento: string | null
}

export function useVentaLegado(store: ReturnType<typeof useVentaStore>) {
  const delLegado = ref<EstacionLegado[]>([])

  /** Estaciones del selector: las del legado (sus ids son otros) o las del sistema nuevo. */
  const estaciones = computed(() => (usarLegado.value ? delLegado.value : (store.contexto?.estaciones ?? [])))
  const departamentoPropio = computed(() => (usarLegado.value ? undefined : store.contexto?.estacion?.departamento))

  async function cambioDeFuente() {
    store.cerrarSalida()
    if (usarLegado.value) {
      if (!delLegado.value.length) delLegado.value = await http.get<EstacionLegado[]>('/prueba-legado/venta/estaciones')
      // Mismo nombre que la estación del usuario, si existe en el legado; si no, todas.
      const propia = store.contexto?.estacion?.nombre.trim().toLowerCase()
      store.estacionId = delLegado.value.find((e) => e.nombre.trim().toLowerCase() === propia)?.id ?? null
    } else {
      store.estacionId = store.contexto?.estacion?.id ?? null
    }
    await store.cargarSalidas()
  }

  return { estaciones, departamentoPropio, cambioDeFuente }
}
