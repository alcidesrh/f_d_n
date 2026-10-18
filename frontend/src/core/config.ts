/**
 * Endpoints del backend (variables `VITE_*` de `.env`), en un solo lugar.
 * Una ruta relativa (`/api`) se resuelve contra el origen de la página: la app y la API
 * las sirve el mismo Caddy, así funciona igual en `localhost` que por la IP de la red local.
 */
const absoluta = (url: string) =>
  typeof window === 'undefined' ? url : new URL(url, window.location.origin).href

export const config = {
  graphqlUrl: absoluta(import.meta.env.VITE_GRAPHQL_ENDPOINT ?? '/graphql'),
  restUrl: absoluta(import.meta.env.VITE_REST_ENDPOINT ?? '/api').replace(/\/$/, ''),
  mercureUrl: absoluta(import.meta.env.VITE_MERCURE_URL ?? '/.well-known/mercure'),
} as const
