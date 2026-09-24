import { CombinedGraphQLErrors, ServerError, ServerParseError } from "@apollo/client";
import { ErrorLink } from "@apollo/client/link/error";
import { handleUnauthorized } from "../../auth/unauthorized";
import { notify } from "../../notify";

/** Traduce los errores de red y de GraphQL a notificaciones; un 401 cierra la sesión. */
export function createErrorLink() {
  return new ErrorLink(({ error }) => {
    if (ServerError.is(error)) {
      if (error.statusCode === 401) return handleUnauthorized();
      return notify.error(`Error ${error.statusCode} del servidor: ${error.message}`);
    }
    if (CombinedGraphQLErrors.is(error)) {
      for (const { message, extensions } of error.errors) {
        const debug = extensions?.debugMessage ? ` (${String(extensions.debugMessage)})` : "";
        notify.error(`${message}${debug}`);
      }
      return;
    }
    if (ServerParseError.is(error)) {
      return notify.error(`Respuesta inválida del servidor (HTTP ${error.statusCode}).`);
    }
    notify.error("Problema con la conexión.");
  });
}
