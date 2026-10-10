/** Genera un reporte y lo entrega: el PDF se abre en otra pestaña; el Excel se descarga. */
import { fetchArchivo, type ReporteId } from '@/core/reporte/api'
import type { FormatoReporte } from '@/core/reporte/types'
import { notify } from '@/core/notify'

/** El archivo del reporte, o null (avisa del error). Para quien lo muestra o lo adjunta en vez de abrirlo. */
export async function obtenerReporte(reporte: ReporteId, query: string, formato: FormatoReporte): Promise<Blob | null> {
  try {
    return await fetchArchivo(reporte, query, formato)
  } catch (e) {
    notify.error(e instanceof Error && e.message && !e.message.startsWith('HTTP ') ? e.message : 'No se pudo generar el reporte.')
    return null
  }
}

export async function generarReporte(reporte: ReporteId, query: string, formato: FormatoReporte, nombre: string): Promise<boolean> {
  const blob = await obtenerReporte(reporte, query, formato)
  if (!blob) return false
  const url = URL.createObjectURL(blob)
  if (formato === 'pdf') {
    window.open(url, '_blank', 'noopener')
  } else {
    const a = document.createElement('a')
    a.href = url
    a.download = `${nombre}.${formato}`
    a.click()
  }
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
  return true
}
