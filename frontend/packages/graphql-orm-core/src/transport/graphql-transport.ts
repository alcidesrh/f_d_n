import { GraphQLApiError } from './errors';

export interface GraphQLTransport {
  execute<T = unknown>(document: string, variables?: Record<string, unknown>): Promise<T>;
}

export interface TransportRequest {
  document: string;
  variables?: Record<string, unknown>;
  headers?: Record<string, string>;
}

export type TransportMiddleware = (
  req: TransportRequest,
  next: (req: TransportRequest) => Promise<unknown>,
) => Promise<unknown>;

export class FetchTransport implements GraphQLTransport {
  private middlewares: TransportMiddleware[] = [];

  constructor(
    private endpoint: string,
    private fetchImpl: typeof fetch = (...args) => globalThis.fetch(...args),
  ) {}

  use(mw: TransportMiddleware): this {
    this.middlewares.push(mw);
    return this;
  }

  async execute<T = unknown>(document: string, variables?: Record<string, unknown>): Promise<T> {
    const run = this.middlewares.reduceRight<(req: TransportRequest) => Promise<unknown>>(
      (next, mw) => (req) => mw(req, next),
      async (req) => {
        const res = await this.fetchImpl(this.endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', ...req.headers },
          body: JSON.stringify({ query: req.document, variables: req.variables }),
        });

        const json = await res.json();
        if (json.errors?.length) {
          throw new GraphQLApiError(json.errors[0]?.message ?? 'Error GraphQL', json.errors);
        }
        return json.data;
      },
    );

    return run({ document, variables }) as Promise<T>;
  }
}

/** Middleware que adjunta headers por request (p. ej. X-Tenant, Accept-Language). */
export const headersMiddleware = (
  getHeaders: () => Record<string, string> | Promise<Record<string, string>>,
): TransportMiddleware => {
  return async (req, next) => {
    const extra = await getHeaders();
    req.headers = { ...req.headers, ...extra };
    return next(req);
  };
};

/** Middleware de autenticación Bearer — añade "Authorization: Bearer <token>" cuando hay token. */
export const authMiddleware = (getToken: () => string | null): TransportMiddleware => {
  return async (req, next) => {
    const token = getToken();
    if (token) {
      req.headers = { ...req.headers, Authorization: `Bearer ${token}` };
    }
    return next(req);
  };
};
