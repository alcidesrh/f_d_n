/** Avisos de Mercure (mismo origen). Sin hub, la página funciona igual. */
import { urlMercure } from './api'

export function suscribir(topico: string, alCambiar: () => void): () => void {
  if (typeof EventSource === 'undefined') return () => {}
  try {
    const fuente = new EventSource(urlMercure(topico))
    fuente.onmessage = () => alCambiar()
    return () => fuente.close()
  } catch {
    return () => {}
  }
}
