import type { RagfConfig, RagfOptions } from "./types";

const DEFAULTS = {
  graphqlEndpoint: "http://localhost/graphql",
  restEndpoint: "http://localhost/api",
  mercureUrl: "http://localhost/.well-known/mercure",
} as const;

function stripTrailingSlash(value: string): string {
  return value.length > 1 && value.endsWith("/") ? value.slice(0, -1) : value;
}

/**
 * Resolves runtime endpoints from the given overrides, the `VITE_*` env
 * surface (`.env`) and hard-coded development defaults, in that order.
 */
export function resolveConfig(options: RagfOptions = {}): RagfConfig {
  const graphqlEndpoint = stripTrailingSlash(
    options.graphqlEndpoint ?? import.meta.env.VITE_GRAPHQL_ENDPOINT ?? DEFAULTS.graphqlEndpoint,
  );
  const mercureUrl = stripTrailingSlash(
    options.mercureUrl ?? import.meta.env.VITE_MERCURE_URL ?? DEFAULTS.mercureUrl,
  );

  let restEndpoint: string;
  if (options.restEndpoint) {
    restEndpoint = stripTrailingSlash(options.restEndpoint);
  } else {
    // REST is served by the same origin as the GraphQL entrypoint in the
    // Caddy/FrankenPHP stack (e.g. http://localhost/graphql -> http://localhost/api).
    try {
      const origin = new URL(graphqlEndpoint).origin;
      restEndpoint = `${origin}/api`;
    } catch {
      restEndpoint = DEFAULTS.restEndpoint;
    }
  }

  return { graphqlEndpoint, restEndpoint, mercureUrl };
}