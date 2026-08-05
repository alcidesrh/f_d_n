/**
 * RAGF — Runtime Adaptive GraphQL Framework.
 *
 * Core type vocabulary (Milestone 1: transport + lifecycle).
 * Field/Entity/Operation definitions are introduced by the Schema AST
 * and Metadata Registry modules in later milestones.
 */
import type { GraphQLClient, RestClient } from "./transport/client";

/** Resolved runtime endpoints. */
export interface RagfConfig {
  /** GraphQL entrypoint, e.g. `http://localhost/graphql`. */
  graphqlEndpoint: string;
  /** REST base for metadata/config APIs, e.g. `http://localhost/api`. */
  restEndpoint: string;
  /** Mercure hub URL, e.g. `http://localhost/.well-known/mercure`. */
  mercureUrl: string;
}

/** Overrides accepted by `createRagf()`; unset values fall back to env/config. */
export interface RagfOptions {
  graphqlEndpoint?: string;
  restEndpoint?: string;
  mercureUrl?: string;
  /** Custom fetch implementation (tests, profilers). */
  fetchImpl?: typeof fetch;
  /** Called for non-fatal, recoverable errors during bootstrap. */
  onError?: (error: unknown) => void;
}

/** A single error entry returned by a GraphQL response `errors` array. */
export interface GraphQlError {
  message: string;
  locations?: readonly { line: number; column: number }[];
  path?: readonly (string | number)[];
  extensions?: Record<string, unknown>;
}

/** Raw GraphQL execution result before unwrapping `data`. */
export interface GraphQlResult<T> {
  data: T | null;
  errors?: GraphQlError[];
}

export type RagfStatus = "idle" | "loading" | "ready" | "error";

/** Runtime graph handed to consumers by `createRagf()`. */
export interface RagfInstance {
  readonly config: RagfConfig;
  readonly graphql: GraphQLClient;
  readonly rest: RestClient;
  /** Connectivity smoke check against the live entrypoint. */
  ping(): Promise<boolean>;
}