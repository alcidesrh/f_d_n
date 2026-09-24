/** Endpoints del backend (variables `VITE_*` de `.env`), en un solo lugar. */
export const config = {
  graphqlUrl: import.meta.env.VITE_GRAPHQL_ENDPOINT ?? "http://localhost/graphql",
  restUrl: (import.meta.env.VITE_REST_ENDPOINT ?? "http://localhost/api").replace(/\/$/, ""),
  mercureUrl: import.meta.env.VITE_MERCURE_URL ?? "http://localhost/.well-known/mercure",
} as const;
