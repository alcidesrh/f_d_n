import { describe, it, expect } from 'vitest';
import { FetchTransport, authMiddleware, headersMiddleware, type TransportRequest } from '../src/transport/graphql-transport';
import { GraphQLApiError } from '../src/transport/errors';

interface CapturedCall {
  method: string;
  headers: Record<string, string>;
  body: string;
}

function makeFetch(captured: CapturedCall[], response: unknown, status = 200) {
  return async (input: RequestInfo | URL, init?: RequestInit) => {
    captured.push({
      method: init?.method ?? 'GET',
      headers: (init?.headers ?? {}) as Record<string, string>,
      body: String(init?.body),
    });
    return {
      ok: status >= 200 && status < 300,
      status,
      json: async () => response,
    } as Response;
  };
}

describe('FetchTransport', () => {
  it('POSTs the document and returns data', async () => {
    const captured: CapturedCall[] = [];
    const transport = new FetchTransport('http://localhost/graphql', makeFetch(captured, { data: { ok: true } }));

    const result = await transport.execute('query { status(id: $id) { id } }', { id: '/statuses/1' });

    expect(result).toEqual({ ok: true });
    expect(captured[0]?.method).toBe('POST');
    expect(JSON.parse(captured[0]!.body)).toEqual({
      query: 'query { status(id: $id) { id } }',
      variables: { id: '/statuses/1' },
    });
  });

  it('throws GraphQLApiError when the response carries errors', async () => {
    const captured: CapturedCall[] = [];
    const transport = new FetchTransport('http://localhost/graphql', makeFetch(captured, {
      data: null,
      errors: [{ message: 'boom' }],
    }));

    await expect(transport.execute('query { x }')).rejects.toBeInstanceOf(GraphQLApiError);
  });

  it('authMiddleware attaches a Bearer token only when one exists', async () => {
    const captured: CapturedCall[] = [];
    let token: string | null = 'jwt-123';
    const transport = new FetchTransport('http://localhost/graphql', makeFetch(captured, { data: {} }));
    transport.use(authMiddleware(() => token));

    await transport.execute('{ x }');
    expect(captured[0]?.headers).toMatchObject({ Authorization: 'Bearer jwt-123' });

    token = null;
    await transport.execute('{ x }');
    expect(captured[1]?.headers).not.toHaveProperty('Authorization');
  });

  it('headersMiddleware merges extra headers per request', async () => {
    const captured: CapturedCall[] = [];
    const transport = new FetchTransport('http://localhost/graphql', makeFetch(captured, { data: {} }));
    transport.use(headersMiddleware(async () => ({ 'X-Tenant': 'gt', 'Accept-Language': 'es' })));

    await transport.execute('{ x }');

    expect(captured[0]?.headers).toMatchObject({ 'X-Tenant': 'gt', 'Accept-Language': 'es' });
    expect(captured[0]?.headers['Content-Type']).toBe('application/json');
  });

  it('middleware can observe and forward the request in order', async () => {
    const seen: string[] = [];
    const transport = new FetchTransport('http://localhost/graphql', makeFetch([], { data: {} }));

    transport.use(async (req: TransportRequest, next) => {
      seen.push(`a:${req.document.slice(0, 1)}`);
      req.headers = { ...req.headers, 'X-A': '1' };
      return next(req);
    });
    transport.use(async (req: TransportRequest, next) => {
      seen.push(`b:${req.document.slice(0, 1)}`);
      req.headers = { ...req.headers, 'X-B': '2' };
      return next(req);
    });

    await transport.execute('q');

    expect(seen).toEqual(['a:q', 'b:q']);
  });
});
