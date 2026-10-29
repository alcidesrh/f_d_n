/** Abre el PDF de un manifiesto de la salida en otra pestaña. */
import { fetchManifiestoPdf } from '@/core/salida/api'
import type { TipoManifiesto } from '@/core/salida/types'
import { notify } from '@/core/notify'

export const MANIFIESTOS: ReadonlyArray<{ tipo: TipoManifiesto; etiqueta: string; icono: string }> = [
  { tipo: 'interno', etiqueta: 'Manifiesto interno', icono: 'description-outline' },
  { tipo: 'piloto', etiqueta: 'Manifiesto del piloto', icono: 'sports-motorsports-outline' },
]

export async function abrirManifiesto(salidaId: number, tipo: TipoManifiesto): Promise<void> {
  try {
    const url = URL.createObjectURL(await fetchManifiestoPdf(salidaId, tipo))
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch {
    notify.error('No se pudo generar el manifiesto.')
  }
}

/** Descarga el PDF de un manifiesto de la salida (en vez de abrirlo en otra pestaña). */
export async function descargarManifiesto(salidaId: number, tipo: TipoManifiesto): Promise<boolean> {
  try {
    const url = URL.createObjectURL(await fetchManifiestoPdf(salidaId, tipo))
    const a = document.createElement('a')
    a.href = url
    a.download = `manifiesto_${tipo}_${salidaId}.pdf`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
    return true
  } catch {
    notify.error('No se pudo generar el manifiesto.')
    return false
  }
}
