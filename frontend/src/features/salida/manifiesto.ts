/** Abre el PDF de un manifiesto de la salida en otra pestaña. */
import { fetchManifiestoPdf } from '@/core/salida/api'
import type { TipoManifiesto } from '@/core/salida/types'
import { notify } from '@/core/notify'

export const MANIFIESTOS: ReadonlyArray<{ tipo: TipoManifiesto; etiqueta: string; icono: string }> = [
  { tipo: 'interno', etiqueta: 'Manifiesto interno', icono: 'file-description' },
  { tipo: 'piloto', etiqueta: 'Manifiesto del piloto', icono: 'steering-wheel' },
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
