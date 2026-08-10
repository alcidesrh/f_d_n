import {
  getIntrospectionQuery,
  buildClientSchema,
  buildSchema,
  type IntrospectionQuery,
  type GraphQLSchema,
} from 'graphql';
import type { IntrospectionSource } from '../../../types';

export class LiveIntrospectionSource implements IntrospectionSource {
  constructor(
    private endpoint: string,
    private headers?: () => Record<string, string> | Promise<Record<string, string>>,
    private fetchImpl: typeof fetch = (...args) => globalThis.fetch(...args),
  ) {}

  async load(): Promise<GraphQLSchema> {
    const customHeaders = this.headers ? await this.headers() : {};
    const headers = { 'Content-Type': 'application/json', ...customHeaders };
    const res = await this.fetchImpl(this.endpoint, {
      method: 'POST',
      headers,
      body: JSON.stringify({ query: getIntrospectionQuery({ inputValueDeprecation: true }) }),
    });

    if (!res.ok) {
      throw new Error(`Introspección falló: HTTP ${res.status}. ¿Está habilitada api_platform.graphql.introspection?`);
    }

    const { data, errors } = (await res.json()) as { data?: IntrospectionQuery; errors?: any[] };
    if (errors?.length) {
      throw new Error(`La introspección devolvió errores GraphQL: ${JSON.stringify(errors)}`);
    }
    if (!data) {
      throw new Error('La respuesta de introspección no trae "data".');
    }

    return buildClientSchema(data);
  }
}

export class SdlSnapshotSource implements IntrospectionSource {
  constructor(
    private sdlUrl: string,
    private fetchImpl: typeof fetch = (...args) => globalThis.fetch(...args)
  ) {}

  async load(): Promise<GraphQLSchema> {
    const res = await this.fetchImpl(this.sdlUrl);
    if (!res.ok) {
      throw new Error(`No se pudo cargar el snapshot SDL (${this.sdlUrl}): HTTP ${res.status}`);
    }
    return buildSchema(await res.text());
  }
}

export class JsonSnapshotSource implements IntrospectionSource {
  constructor(
    private jsonUrl: string,
    private fetchImpl: typeof fetch = (...args) => globalThis.fetch(...args),
  ) {}

  async load(): Promise<GraphQLSchema> {
    const res = await this.fetchImpl(this.jsonUrl);
    if (!res.ok) {
      throw new Error(`No se pudo cargar el snapshot JSON (${this.jsonUrl}): HTTP ${res.status}`);
    }
    const data = (await res.json()) as IntrospectionQuery;
    return buildClientSchema(data);
  }
}
