import { ApolloLink, Observable } from '@apollo/client'
import { useLoadingStore } from '@/core/loading'

/** Retardo antes de mostrar la carga: las respuestas rápidas no parpadean. */
const DELAY_MS = 150

/**
 * Registra cada operación en `useLoadingStore`. Contexto opcional por
 * operación: `{ noLoading: true }` o `{ loadingKey: "x" }`.
 */
export function createLoadingLink() {
  return new ApolloLink((operation, forward) => {
    const ctx = operation.getContext() as { noLoading?: boolean; loadingKey?: string }
    if (ctx.noLoading) return forward(operation)
    const key = ctx.loadingKey ?? (operation.operationName || 'graphql')
    const loading = useLoadingStore()

    return new Observable((observer) => {
      let started = false
      const timer = setTimeout(() => {
        started = true
        loading.start(key)
      }, DELAY_MS)
      const finish = () => {
        clearTimeout(timer)
        if (started) loading.stop(key)
      }
      const subscription = forward(operation).subscribe({
        next: (value) => observer.next(value),
        error: (cause) => {
          finish()
          observer.error(cause)
        },
        complete: () => {
          finish()
          observer.complete()
        },
      })
      return () => {
        finish()
        subscription.unsubscribe()
      }
    })
  })
}
