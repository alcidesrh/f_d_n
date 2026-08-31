import { ApolloLink } from "@apollo/client/core";

function isObject(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === "object" && !Array.isArray(value);
}

/**
 * Normaliza valores planos a ID/IRI antes de la mutación: una relación enviada
 * como `{ id: 5 }` / `{ '@id': '/api/x/5' }` se reduce a su id, y las listas se
 * mapean igual. El contexto `keepId: true` deja el input tal cual.
 */
export function createMutationLink() {
  return new ApolloLink((operation, forward) => {
    if (operation.operationType == "mutation") {
      const ctx = operation.getContext();
      const input = operation.variables.input;
      if (!ctx?.keepId && isObject(input)) {
        Object.keys(input).forEach((k) => {
          const value = input[k];
          if (isObject(value) && value.id !== undefined) {
            input[k] = value.id;
          } else if (Array.isArray(value)) {
            input[k] = value.map((v) =>
              isObject(v) && v.id !== undefined ? v.id : v,
            );
          }
        });
      }
    }
    return forward(operation);
  });
}