/**
 * Observadores de las respuestas del backend, REST (`core/http`) y GraphQL
 * (`core/graphql/client`): leen cabeceras como `X-Metadata-Revision` sin que
 * los clientes sepan quién las usa. Un observador que falla no rompe la
 * respuesta.
 */

type Observador = (respuesta: Response) => void

const observadores = new Set<Observador>()

/** Llama a `observador` con cada respuesta; devuelve cómo dejar de observar. */
export function observarRespuestas(observador: Observador): () => void {
  observadores.add(observador)
  return () => void observadores.delete(observador)
}

/** `fetch` que pasa cada respuesta a los observadores antes de devolverla. */
export async function fetchObservado(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  const respuesta = await fetch(input, init)
  for (const observador of observadores) {
    try {
      observador(respuesta)
    } catch (error) {
      console.error('[respuestas] un observador falló:', error)
    }
  }
  return respuesta
}
