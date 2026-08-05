import type { GraphQlError } from "../types";

/**
 * Base class for every error surfaced by the RAGF transport layer.
 *
 * Pinned to a single-argument `super` so the class type-checks even when a
 * consuming project type-checks with a minimal lib (see `tsconfig.vitest.json`,
 * which uses `"lib": []`). The standard `cause` slot is populated via a typed
 * assignment rather than the ES2022 `ErrorOptions` constructor parameter.
 */
export class RagfError extends Error {
  constructor(message: string, options: { cause?: unknown } = {}) {
    super(message);
    this.name = new.target.name;
    (this as Error & { cause?: unknown }).cause = options.cause;
  }
}

/** Fetch-level failure: network error, timeout, HTTP non-2xx response. */
export class RagfTransportError extends RagfError {
  readonly status?: number;
  readonly statusText?: string;

  constructor(
    message: string,
    options: { cause?: unknown; status?: number; statusText?: string } = {},
  ) {
    super(message, options);
    this.status = options.status;
    this.statusText = options.statusText;
  }
}

/** The server executed the request but the GraphQL payload carried errors. */
export class RagfGraphQLError extends RagfError {
  readonly errors: readonly GraphQlError[];

  constructor(errors: readonly GraphQlError[], options: { cause?: unknown } = {}) {
    super("GraphQL request failed", options);
    this.errors = errors;
  }

  get messages(): string {
    return formatGraphQlErrors(this.errors);
  }
}

export function isRagfError(value: unknown): value is RagfError {
  return value instanceof RagfError;
}

/** Compact human-readable rendering of a GraphQL `errors` array. */
export function formatGraphQlErrors(errors: readonly GraphQlError[]): string {
  return errors
    .map((error) => {
      const path = error.path ? ` @ ${error.path.join(".")}` : "";
      return `${error.message}${path}`;
    })
    .join("; ");
}