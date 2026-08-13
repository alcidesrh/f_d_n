/**
 * Cliente Apollo singleton para API Platform.
 *
 * Capa de transporte GraphQL de acceso global. A partir de la metadata
 * semántica (extraída por `parseIntrospection`) simplifica las operaciones
 * por defecto de un CRUD agnóstico: item, collection, create, update y
 * delete. Los documentos se construyen dinámicamente y los resultados se
 * devuelven ya "aplanados" (sin el hull de las PageConnection/CursorConnection).
 */

import { ApolloClient, HttpLink, InMemoryCache, gql, type DocumentNode } from '@apollo/client'
import { getIntrospectionQuery, parse } from 'graphql'
import {
  buildCollectionQuery,
  buildItemQuery,
  buildMutation,
  type CollectionQuerySpec,
} from './documents'
import { parseIntrospection, type IntrospectionSchemaLike } from './parseIntrospection'
import type { CollectionPagination, CollectionResult, EntitySchema, MutationSchema } from './types'

const GRAPHQL_URI = import.meta.env.VITE_GRAPHQL_ENDPOINT ?? 'http://localhost/graphql'

/** Los builders de `documents.ts` emiten SDL en string; Apollo requiere DocumentNode. */
function toDocument(source: string): DocumentNode {
  return parse(source)
}

export interface ApiPlatformClientOptions {
  uri?: string
  fetch?: typeof fetch
}

function emptyPagination(totalCount = 0): CollectionPagination {
  return {
    currentPage: 1,
    itemsPerPage: totalCount,
    lastPage: 1,
    totalCount,
    hasNextPage: false,
  }
}

function normalizeCollection<T>(entity: EntitySchema, raw: unknown): CollectionResult<T> {
  if (entity.collectionKind === 'page-connection') {
    const record = (raw ?? {}) as {
      collection?: T[]
      paginationInfo?: Partial<CollectionPagination>
    }
    const pagination = record.paginationInfo ?? {}
    return {
      items: record.collection ?? [],
      pagination: {
        currentPage: pagination.currentPage ?? 1,
        itemsPerPage: pagination.itemsPerPage ?? 0,
        lastPage: pagination.lastPage ?? 1,
        totalCount: pagination.totalCount ?? 0,
        hasNextPage: pagination.hasNextPage ?? false,
      },
    }
  }
  if (entity.collectionKind === 'cursor-connection') {
    const record = (raw ?? {}) as { edges?: Array<{ node?: T }>; totalCount?: number }
    const nodes = (record.edges ?? [])
      .map((edge) => edge.node)
      .filter((node): node is T => node != null)
    const totalCount = record.totalCount ?? nodes.length
    return {
      items: nodes,
      pagination: { ...emptyPagination(totalCount), itemsPerPage: nodes.length },
    }
  }
  if (entity.collectionKind === 'single') {
    return raw
      ? { items: [raw as T], pagination: emptyPagination(1) }
      : { items: [], pagination: emptyPagination(0) }
  }
  const list = Array.isArray(raw) ? (raw as T[]) : []
  return { items: list, pagination: emptyPagination(list.length) }
}

export class ApiPlatformClient {
  readonly client: ApolloClient
  readonly uri: string

  constructor(options: ApiPlatformClientOptions = {}) {
    this.uri = options.uri ?? GRAPHQL_URI
    this.client = new ApolloClient({
      link: new HttpLink({ uri: this.uri, fetch: options.fetch }),
      cache: new InMemoryCache(),
      devtools: {
        enabled: import.meta.env.DEV,
        name: 'api-platform',
      },
    })
  }

  /** Ejecuta la introspección y la traduce a metadatos planos por entidad. */
  async introspect(): Promise<Record<string, EntitySchema>> {
    const query = gql(getIntrospectionQuery({ descriptions: true }))
    const result = await this.client.query<{ __schema: IntrospectionSchemaLike }>({ query })
    return parseIntrospection(result.data!.__schema)
  }

  /** Ejecuta un documento arbitrario (passthrough a Apollo). */
  async query<T>(document: DocumentNode, variables?: Record<string, unknown>): Promise<T> {
    const result = await this.client.query<T>({ query: document, variables })
    return result.data as T
  }

  /** Ejecuta una mutación arbitraria (passthrough a Apollo). */
  async mutate<T>(document: DocumentNode, variables?: Record<string, unknown>): Promise<T> {
    const result = await this.client.mutate<T>({ mutation: document, variables })
    return result.data as T
  }

  /** Item por id. */
  async item<T>(entity: EntitySchema, id: string | number): Promise<T> {
    if (!entity.queryItem) throw new Error(`[apollo] "${entity.name}" no expone query item`)
    const { query } = buildItemQuery(entity)
    const result = await this.client.query<Record<string, T>>({
      query: toDocument(query),
      variables: { id },
    })
    return result.data![entity.queryItem] as T
  }

  /** Colección con paginación/filtros/orden normalizada a `{ items, pagination }`. */
  async collection<T>(
    entity: EntitySchema,
    spec: CollectionQuerySpec = {},
  ): Promise<CollectionResult<T>> {
    if (!entity.queryCollection)
      throw new Error(`[apollo] "${entity.name}" no expone query collection`)
    const { query, variables } = buildCollectionQuery(entity, spec)
    const result = await this.client.query<Record<string, unknown>>({
      query: toDocument(query),
      variables,
    })
    return normalizeCollection<T>(entity, result.data![entity.queryCollection])
  }

  async create<T>(entity: EntitySchema, input: Record<string, unknown>): Promise<T> {
    return this.runMutation<T>(entity, entity.create, input)
  }

  async update<T>(entity: EntitySchema, input: Record<string, unknown>): Promise<T> {
    return this.runMutation<T>(entity, entity.update, input)
  }

  async delete<T>(entity: EntitySchema, id: string | number): Promise<T> {
    return this.runMutation<T>(entity, entity.delete, { id })
  }

  private async runMutation<T>(
    entity: EntitySchema,
    mutation: MutationSchema | null,
    input: Record<string, unknown>,
  ): Promise<T> {
    if (!mutation) throw new Error(`[apollo] "${entity.name}" no expone mutación`)
    const { query } = buildMutation(entity, mutation)
    const result = await this.client.mutate<Record<string, Record<string, T>>>({
      mutation: toDocument(query),
      variables: { input },
    })
    return result.data?.[mutation.field]?.[mutation.returnsField] as T
  }
}

export function createApiPlatformClient(options: ApiPlatformClientOptions = {}): ApiPlatformClient {
  return new ApiPlatformClient(options)
}

/** Cliente GraphQL singleton de acceso global. */
export const apollo = createApiPlatformClient()
