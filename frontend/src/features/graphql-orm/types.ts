import type { GraphQLSchema } from 'graphql';
import type { Ref } from 'vue';

export interface SelectionOptions {
  maxDepth?: number; // default 1
  include?: Record<string, SelectionOptions | true>;
}

export interface IntrospectionSource {
  load(): Promise<GraphQLSchema>;
}

export interface PageInfo {
  startCursor?: string;
  endCursor?: string;
  hasNextPage?: boolean;
  hasPreviousPage?: boolean;
}

export interface CollectionResult<T> {
  items: T[];
  totalCount?: number;
  pageInfo?: PageInfo;
}

export interface FindAllParams {
  first?: number;
  after?: string;
  last?: number;
  before?: string;
  order?: Array<Record<string, 'ASC' | 'DESC'>>; // preserva orden — ver sección 3.4
  filters?: Record<string, unknown>; // resto de argumentos reales del schema
}

export interface Repository<T = Record<string, unknown>> {
  findById(id: string, options?: SelectionOptions): Promise<T | null>;
  findAll(params?: FindAllParams, options?: SelectionOptions): Promise<CollectionResult<T>>;
  create(input: Record<string, unknown>): Promise<T>;
  update(id: string, input: Record<string, unknown>): Promise<T>;
  remove(id: string): Promise<boolean>;
  call<TResult = unknown>(
    operationName: string,
    args?: Record<string, unknown>,
    selection?: SelectionOptions,
  ): Promise<TResult>;
}

export type FieldKind = 'scalar' | 'enum' | 'object' | 'input-object' | 'unknown';

export interface FieldDescriptor {
  name: string;
  typeName: string;
  kind: FieldKind;
  isList: boolean;
  isNonNull: boolean;
}

export interface OperationArg {
  name: string;
  typeName: string; // tipo "desnudo" (sin NonNull/List) para lookups
  printedType: string; // tipo tal cual, p.ej. "[BookFilter_order!]"
  isNonNull: boolean;
  isList: boolean;
}

export type CollectionShape = 'relay' | 'page' | 'flat';

export interface OperationDescriptor {
  fieldName: string; // "books", "createBook", ...
  kind: 'query-item' | 'query-collection' | 'mutation' | 'custom-query';
  args: OperationArg[];
  returnTypeName: string; // resuelto de forma independiente por operación
  returnFields: FieldDescriptor[];
  collectionShape?: CollectionShape; // solo si kind === 'query-collection'
  nodeTypeName?: string; // solo si collectionShape === 'relay' | 'page': el tipo del elemento
  inputTypeName?: string; // solo mutaciones: p.ej. "createBookInput"
  inputFields?: FieldDescriptor[];
  payloadEntityField?: string; // solo mutaciones: campo dentro del Payload que trae la entidad
}

export interface EntityDescriptor {
  typeName: string;
  queries: { item?: OperationDescriptor; collection?: OperationDescriptor };
  mutations: { create?: OperationDescriptor; update?: OperationDescriptor; delete?: OperationDescriptor };
  customOperations: OperationDescriptor[];
}

export interface GraphQLViolation {
  propertyPath: string;
  message: string;
  code?: string;
}

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

export interface GraphQLOrmOptions {
  endpoint: string;
  source: IntrospectionSource;
  entities: string[];
  authHeaders?: () => Record<string, string> | Promise<Record<string, string>>;
}

export interface GraphQLOrmContext {
  isReady: Ref<boolean>;
  error: Ref<unknown>;
  /** Schema GraphQL resuelto por la fuente de introspección (null hasta estar listo). */
  schema: Ref<GraphQLSchema | null>;
  repository<T = Record<string, unknown>>(typeName: string): Repository<T>;
}
