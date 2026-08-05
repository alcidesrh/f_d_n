/**
 * RAGF — Runtime Adaptive GraphQL Framework.
 *
 * Public entry point. `createRagf()` builds the runtime graph for a given
 * configuration; later milestones attach the schema registry, entity manager,
 * identity map / unit of work, query & mutation DSL, validation and UI
 * generators to the returned instance.
 */
import { resolveConfig } from "./config";
import { GraphQLClient, RestClient } from "./transport/client";
import type { RagfInstance, RagfOptions } from "./types";

export type {
  GraphQlError,
  GraphQlResult,
  RagfConfig,
  RagfInstance,
  RagfOptions,
  RagfStatus,
} from "./types";
export {
  formatGraphQlErrors,
  isRagfError,
  RagfError,
  RagfGraphQLError,
  RagfTransportError,
} from "./transport/errors";
export { GraphQLClient, RestClient, httpErrorMessage } from "./transport/client";

/**
 * Builds a RAGF runtime instance. Each call creates independent transports
 * (no shared mutable state) so tests and consumers can own their instances.
 */
export function createRagf(options: RagfOptions = {}): RagfInstance {
  const config = resolveConfig(options);
  const graphql = new GraphQLClient({
    endpoint: config.graphqlEndpoint,
    fetchImpl: options.fetchImpl,
  });
  const rest = new RestClient({
    baseUrl: config.restEndpoint,
    fetchImpl: options.fetchImpl,
  });

  const ping = async (): Promise<boolean> => {
    const result = await graphql.request<{ __typename: string }>("{ __typename }");
    return result.__typename === "Query";
  };

  return { config, graphql, rest, ping };
}