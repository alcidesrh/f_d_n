/**
 * Capa de transporte para el CRUD agnóstico:
 * - Apollo singleton (`apollo`) con las operaciones item/collection/create/
 *   update/delete simplificadas para API Platform.
 * - REST singleton (`rest`) para los metadatos de entidades
 *   (`/entity_configurations`).
 * - Parsing de introspección a metadata semántica plana.
 */

export { ApiPlatformClient, apollo, createApiPlatformClient } from './client'
export type { ApiPlatformClientOptions } from './client'
export { RestClient, rest, createRestClient } from './rest'
export { parseIntrospection } from './parseIntrospection'
export type {
  IntrospectionRef,
  IntrospectionSchemaLike,
  IntrospectionType,
} from './parseIntrospection'
export {
  buildCollectionQuery,
  buildItemQuery,
  buildMutation,
  buildSelection,
  toMutationInput,
} from './documents'
export type { CollectionQuerySpec, BuiltDocument, SelectionOptions } from './documents'
export type {
  AgnosticOption,
  CollectionKind,
  CollectionPagination,
  CollectionResult,
  EntityFieldSchema,
  EntitySchema,
  MutationSchema,
  OrderCondition,
  OrderDirection,
  SchemaArg,
  SchemaInputField,
} from './types'
