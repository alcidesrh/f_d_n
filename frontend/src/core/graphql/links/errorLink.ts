import { CombinedGraphQLErrors, ServerError, ServerParseError } from '@apollo/client'
import { ErrorLink } from '@apollo/client/link/error'
import { handleUnauthorized } from '@/core/auth/unauthorized'
import { avisarDesfase } from '@/core/graphql/desfase'
import { notify } from '@/core/notify'

/**
 * Traduce los errores de red y de GraphQL a notificaciones; un 401 cierra la
 * sesión. Los que delatan un schema viejo además se avisan (`desfase.ts`).
 */
export function createErrorLink() {
  return new ErrorLink(({ error }) => {
    if (ServerError.is(error)) {
      if (error.statusCode === 401) handleUnauthorized()
      else notify.error(`Error ${error.statusCode} del servidor: ${error.message}`)
      return
    }
    if (CombinedGraphQLErrors.is(error)) {
      for (const { message, extensions } of error.errors) {
        const debug = extensions?.debugMessage ? ` (${String(extensions.debugMessage)})` : ''
        notify.error(`${message}${debug}`)
      }
      avisarDesfase(error.errors.map(({ message }) => message))
      return
    }
    if (ServerParseError.is(error)) {
      notify.error(`Respuesta inválida del servidor (HTTP ${error.statusCode}).`)
      return
    }
    notify.error('Problema con la conexión.')
  })
}
