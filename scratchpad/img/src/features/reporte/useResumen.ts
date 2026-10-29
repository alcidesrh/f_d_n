/** Vista previa con las cifras del reporte: consulta (con espera) cada vez que cambia la query válida. */
import { ref, watch } from 'vue'

export function useResumen<T>(query: () => string | null, cargar: (q: string) => Promise<T>) {
  const datos = ref<T | null>(null)
  const cargando = ref(false)
  const error = ref<string | null>(null)
  let vuelta = 0
  let espera: ReturnType<typeof setTimeout> | undefined

  watch(
    query,
    (q) => {
      clearTimeout(espera)
      const mia = ++vuelta
      error.value = null
      if (!q) {
        datos.value = null
        cargando.value = false
        return
      }
      cargando.value = true
      espera = setTimeout(async () => {
        try {
          const r = await cargar(q)
          if (mia === vuelta) datos.value = r
        } catch (e) {
          if (mia === vuelta) {
            datos.value = null
            error.value = e instanceof Error ? e.message : 'No se pudo consultar el reporte.'
          }
        } finally {
          if (mia === vuelta) cargando.value = false
        }
      }, 350)
    },
    { immediate: true },
  )

  return { datos, cargando, error }
}
