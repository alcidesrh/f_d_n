/**
 * Suscripción a tópicos de Mercure (hub integrado en Caddy, ADR-005). Si el
 * hub no está disponible no pasa nada: quien suscribe también refresca a
 * mano o por intervalo.
 */
import { config } from './config'

/** Llama a `alCambiar` con cada mensaje del tópico; devuelve la desuscripción. */
export function suscribir(topico: string, alCambiar: (datos: unknown) => void): () => void {
  if (typeof EventSource === 'undefined') return () => {}
  const url = new URL(config.mercureUrl)
  url.searchParams.append('topic', topico)
  let fuente: EventSource | null = null
  try {
    fuente = new EventSource(url.toString())
    fuente.onmessage = (evento) => {
      let datos: unknown = null
      try {
        datos = JSON.parse(evento.data)
      } catch {
        datos = evento.data
      }
      alCambiar(datos)
    }
  } catch {
    fuente = null
  }
  return () => fuente?.close()
}
