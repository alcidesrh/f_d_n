import {
  CombinedGraphQLErrors,
  ServerError,
  ServerParseError,
} from "@apollo/client";
import { ErrorLink } from "@apollo/client/link/error";
export function createErrorLink() {
  return new ErrorLink(({ error, networkError, operation, forward }) => {
    let temp;
    if (ServerError.is(error)) {
      if (error.statusCode == 401) {
        useUserSessionStore().clear();
        const router = useRouter();
        temp = {
          message: "Acceso no permitido.",
        };
        router.push({ name: "Login" });
      } else if (error.statusCode == 500) {
        const { status, title, detail } = JSON.parse(error.bodyText);
        temp = {
          caption: `Status code: ${status}. GraphQL ServerError from plugin/apollo.ts: ${title}`,
          message: detail,
        };
      } else if (error.statusCode == 404) {
        temp = {
          message: `No GraphQL Endpoint`,
          caption: "Status code: 404. ",
        };
      } else {
        error.errors.forEach(({ message, locations, path }) =>
          merror({
            message: `[GraphQL error]: Message: ${message}, Location: ${locations}, Path: ${path}`,
          }),
        );
        return;
      }
    } else if (CombinedGraphQLErrors.is(error)) {
      error.errors.forEach(({ message, locations, path, extensions }) => {
        if (message) {
          if (extensions && extensions.debugMessage) {
            temp = {
              caption: message,
              message:
                extensions.debugMessage +
                " " +
                extensions?.file +
                " " +
                extensions.line,
            };
          } else {
            temp = {
              caption:
                "GraphQL error from /home/alcides/frontend/src/graphql/apollo-client.ts",
              message:
                message +
                " " +
                (extensions && extensions.debugMessage
                  ? extensions.debugMessage
                  : ""),
            };
          }
        }
      });
    } else if (ServerParseError.is(error)) {
      // Access the original parse error
      temp = {
        caption: `Failed to parse response from ${error.response.url}`,
        message: `${error.bodyText} Status code: ${error.statusCode}`,
      };
    } else {
      temp = {
        caption:
          "GraphQL error from /home/alcides/frontend/src/graphql/apollo-client.ts",
        message: "Problema con la conexión.",
      };
    }
    if (temp) merror(temp);
  });
}
