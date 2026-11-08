import { esManifiesto, type ManifiestoMapa } from './estilo'

/** Cuánto vale el manifiesto leído antes de volver a preguntar (ms). */
const VIGENCIA_MS = 10 * 60 * 1000

let pedido: Promise<ManifiestoMapa | null> | null = null
let pedidoEn = 0

/**
 * El manifiesto del mapa base (`/mapas/mapa.json`, Caddy lo sirve sin caché). Se lee
 * de nuevo pasado un rato: una pestaña abierta días toma las teselas nuevas de
 * `make mapas` en el próximo mapa que abra. Nulo si los mapas no están preparados
 * o no se pudo leer: el mapa sale con fondo liso y se vuelve a preguntar la próxima vez.
 */
export function cargarManifiesto(): Promise<ManifiestoMapa | null> {
  if (pedido && Date.now() - pedidoEn < VIGENCIA_MS) return pedido
  pedidoEn = Date.now()
  const este: Promise<ManifiestoMapa | null> = fetch('/mapas/mapa.json', { headers: { Accept: 'application/json' } })
    .then((r) => (r.ok ? r.json() : null))
    .then((x: unknown) => (esManifiesto(x) ? x : null))
    .catch(() => null)
    .then((m) => {
      if (!m && pedido === este) pedido = null
      return m
    })
  pedido = este
  return este
}
