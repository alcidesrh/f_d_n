import type { GraphQlResult } from "../types";
import { formatGraphQlErrors, RagfGraphQLError, RagfTransportError } from "./errors";

export interface GraphQLRequestOptions {
  signal?: AbortSignal;
  headers?: Record<string, string>;
}

/**
 * Minimal fetch-based GraphQL transport. There is no response cache: the
 * Identity Map is the single cache owned by the framework, and the query
 * engine builds documents on top of this transport.
 */
export class GraphQLClient {
  readonly endpoint: string;

  readonly #fetch: typeof fetch;
  readonly #headers: Record<string, string>;
  #authToken: string | null = null;

  constructor(options: {
    endpoint: string;
    fetchImpl?: typeof fetch;
    headers?: Record<string, string>;
  }) {
    this.endpoint = options.endpoint;
    this.#fetch = options.fetchImpl ?? fetch.bind(globalThis);
    this.#headers = { "Content-Type": "application/json", ...options.headers };
  }

  /** Sets the bearer token used on subsequent requests (auth integration). */
  setAuthToken(token: string | null): void {
    this.#authToken = token;
  }

  /**
   * Executes a raw GraphQL document and returns the unwrapped `data`.
   * Throws `RagfTransportError` on fetch/HTTP failures and
   * `RagfGraphQLError` when the response contains GraphQL errors.
   */
  async request<T = unknown>(
    query: string,
    variables?: Record<string, unknown>,
    options: GraphQLRequestOptions = {},
  ): Promise<T> {
    const headers: Record<string, string> = { ...this.#headers, ...options.headers };
    if (this.#authToken) {
      headers.Authorization = `Bearer ${this.#authToken}`;
    }

    let response: Response;
    try {
      response = await this.#fetch(this.endpoint, {
        method: "POST",
        headers,
        body: JSON.stringify({ query, variables: variables ?? {} }),
        signal: options.signal,
      });
    } catch (cause) {
      throw new RagfTransportError(
        `Network error reaching ${this.endpoint}`,
        { cause },
      );
    }

    if (!response.ok) {
      throw new RagfTransportError(
        `GraphQL endpoint responded with HTTP ${response.status} ${response.statusText}`,
        { status: response.status, statusText: response.statusText, cause: response },
      );
    }

    let body: GraphQlResult<T>;
    try {
      body = (await response.json()) as GraphQlResult<T>;
    } catch (cause) {
      throw new RagfTransportError(
        "GraphQL endpoint returned a non-JSON response",
        { cause },
      );
    }

    if (body.errors && body.errors.length > 0) {
      throw new RagfGraphQLError(body.errors);
    }

    return body.data as T;
  }

  query<T = unknown>(
    query: string,
    variables?: Record<string, unknown>,
    options?: GraphQLRequestOptions,
  ): Promise<T> {
    return this.request<T>(query, variables, options);
  }

  mutation<T = unknown>(
    mutation: string,
    variables?: Record<string, unknown>,
    options?: GraphQLRequestOptions,
  ): Promise<T> {
    return this.request<T>(mutation, variables, options);
  }
}

export interface RestRequestOptions {
  signal?: AbortSignal;
  headers?: Record<string, string>;
}

/**
 * Thin JSON transport over the REST surface used for metadata/config
 * endpoints (e.g. `entity_configurations`, `config-versions`).
 */
export class RestClient {
  readonly baseUrl: string;

  readonly #fetch: typeof fetch;
  readonly #headers: Record<string, string>;
  #authToken: string | null = null;

  constructor(options: {
    baseUrl: string;
    fetchImpl?: typeof fetch;
    headers?: Record<string, string>;
  }) {
    this.baseUrl = options.baseUrl.replace(/\/$/, "");
    this.#fetch = options.fetchImpl ?? fetch.bind(globalThis);
    // API Platform negotiates JSON-LD by default; `application/json` alone
    // returns 406 when only the jsonld + graphql formats are enabled.
    this.#headers = { Accept: "application/ld+json", ...options.headers };
  }

  setAuthToken(token: string | null): void {
    this.#authToken = token;
  }

  async getJson<T = unknown>(
    path: string,
    options: RestRequestOptions = {},
  ): Promise<T> {
    const headers: Record<string, string> = { ...this.#headers, ...options.headers };
    if (this.#authToken) {
      headers.Authorization = `Bearer ${this.#authToken}`;
    }

    const url = path.startsWith("http") ? path : `${this.baseUrl}${path}`;

    let response: Response;
    try {
      response = await this.#fetch(url, {
        method: "GET",
        headers,
        signal: options.signal,
      });
    } catch (cause) {
      throw new RagfTransportError(`Network error reaching ${url}`, { cause });
    }

    if (!response.ok) {
      throw new RagfTransportError(
        `REST endpoint responded with HTTP ${response.status} ${response.statusText}`,
        { status: response.status, statusText: response.statusText, cause: response },
      );
    }

    return (await response.json()) as T;
  }
}

export function httpErrorMessage(error: unknown): string {
  if (error instanceof RagfGraphQLError) {
    return formatGraphQlErrors(error.errors);
  }
  if (error instanceof Error) {
    return error.message;
  }
  return String(error);
}