import type { SchemaRegistry } from '../schema/schema-registry';
import type { GraphQLTransport } from '../transport/graphql-transport';
import {
  buildItemQuery,
  buildCollectionQuery,
  buildCreateMutation,
  buildUpdateMutation,
  buildDeleteMutation,
  buildCustomOperation,
} from '../documents/document-factory';
import { createInputValidator } from '../validation/schema-validator';
import { ClientValidationError } from '../transport/errors';
import type { SelectionOptions } from '../documents/selection-set-builder';

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

function assertKnownArgs(known: string[], provided: Record<string, unknown> | undefined, context: string) {
  for (const key of Object.keys(provided ?? {})) {
    if (!known.includes(key)) {
      throw new Error(`Argumento desconocido "${key}" en ${context}. Argumentos válidos según schema: ${known.join(', ')}`);
    }
  }
}

export function createRepository<T = Record<string, unknown>>(
  registry: SchemaRegistry,
  transport: GraphQLTransport,
  typeName: string,
): Repository<T> {
  return {
    async findById(id, options) {
      const { document, op } = buildItemQuery(registry, typeName, options);
      const data = await transport.execute<Record<string, unknown>>(document, { id });
      return (data[op.fieldName] as T) ?? null;
    },

    async findAll(params = {}, options) {
      const { document, op } = buildCollectionQuery(registry, typeName, options);
      const knownArgNames = op.args.map((a) => a.name);
      assertKnownArgs(knownArgNames, params.filters, `findAll("${typeName}")`);

      const rawVars: Record<string, unknown> = {
        first: params.first,
        after: params.after,
        last: params.last,
        before: params.before,
        order: params.order,
        ...params.filters,
      };

      const variables: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(rawVars)) {
        if (v !== undefined && knownArgNames.includes(k)) {
          variables[k] = v;
        }
      }

      const data = await transport.execute<Record<string, any>>(
        document,
        Object.keys(variables).length > 0 ? variables : undefined,
      );
      const raw = data[op.fieldName];

      if (op.collectionShape === 'relay') {
        return {
          items: (raw?.edges ?? []).map((e: any) => e.node),
          totalCount: raw?.totalCount,
          pageInfo: raw?.pageInfo,
        };
      }

      if (op.collectionShape === 'page') {
        return {
          items: raw?.collection ?? [],
          totalCount: raw?.paginationInfo?.totalCount,
          pageInfo: raw?.paginationInfo,
        };
      }

      return { items: (raw ?? []) as T[] };
    },

    async create(input) {
      const op = registry.describe(typeName).mutations.create;
      if (!op) throw new Error(`"${typeName}" no admite creación (mutación "create" no definida).`);
      const { validate } = createInputValidator(registry, op.inputFields ?? []);
      const result = validate(input);
      if (!result.success) throw new ClientValidationError(`Datos inválidos para crear ${typeName}`, result.error.issues);
      const { document } = buildCreateMutation(registry, typeName);
      const data = await transport.execute<Record<string, any>>(document, { input: result.data });
      const payloadKey = op.payloadEntityField ?? typeName.toLowerCase();
      return data[op.fieldName][payloadKey];
    },

    async update(id, input) {
      const op = registry.describe(typeName).mutations.update;
      if (!op) throw new Error(`"${typeName}" no admite actualización (mutación "update" no definida).`);
      const payloadInput = { id, ...input };
      const { validate } = createInputValidator(registry, op.inputFields ?? []);
      const result = validate(payloadInput);
      if (!result.success) throw new ClientValidationError(`Datos inválidos para actualizar ${typeName}`, result.error.issues);
      const { document } = buildUpdateMutation(registry, typeName);
      const data = await transport.execute<Record<string, any>>(document, { input: result.data });
      const payloadKey = op.payloadEntityField ?? typeName.toLowerCase();
      return data[op.fieldName][payloadKey];
    },

    async remove(id) {
      const op = registry.describe(typeName).mutations.delete;
      if (!op) throw new Error(`"${typeName}" no admite borrado (mutación "delete" no definida).`);
      const { document } = buildDeleteMutation(registry, typeName);
      await transport.execute(document, { input: { id } });
      return true;
    },

    async call<TResult = unknown>(
      operationName: string,
      args?: Record<string, unknown>,
      selection?: SelectionOptions,
    ) {
      const { document, op } = buildCustomOperation(registry, typeName, operationName, selection);
      const data = await transport.execute<Record<string, unknown>>(document, args);
      return data[op.fieldName] as TResult;
    },
  };
}
