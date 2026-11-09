import { fetchBoletoPdf } from '@/core/boleto/api'
import { notify } from '@/core/notify'

/** Descarga el PDF de un boleto como `boleto_{id}.pdf` (el id es el que muestra la pantalla de boletos). Avisa si no se pudo. */
export async function descargarBoletoPdf(id: number): Promise<boolean> {
  try {
    const url = URL.createObjectURL(await fetchBoletoPdf(id))
    const a = document.createElement('a')
    a.href = url
    a.download = `boleto_${id}.pdf`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
    return true
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo descargar el boleto.')
    return false
  }
}
