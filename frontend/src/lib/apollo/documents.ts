/**
 * Construcción de documentos GraphQL (queries/mutations) a partir de la
 * metadata plana de `EntitySchema`. Los documentos se generan con variables
 * tipadas para cada filtro/orden presentes, y la selección de campos se
 * aplanan (relaciones a un solo nivel) para no recursar el schema.
 */

import type { EntitySchema, MutationSchema, OrderCondition, SchemaInputField } from "./types";

function indent(text: string, spaces: number): string {
  const pad = " ".repeat(spaces);
  return text
    .split("\n")
    .map((line) => pad + line)
    .join("\n");
}

export interface SelectionOptions {
  includeRelations?: boolean;
  fields?: string[];
}

/** Selección hoja de una entidad: scalars + relaciones a 1 nivel. Si `fields`
 * se pasa, solo se incluyen los campos pedidos (relaciones como `{ id label }`). */
export function buildSelection(entity: EntitySchema, options: SelectionOptions = {}): string {
  const requested = options.fields ? new Set(options.fields) : null;
  const want = (name: string): boolean => requested === null || requested.has(name);
  const lines: string[] = [];
  const seen = new Set<string>();
  for (const field of entity.scalarFields) {
    if (!want(field) || seen.has(field)) continue;
    seen.add(field);
    lines.push(field);
  }
  if (options.includeRelations || requested !== null) {
    for (const relation of entity.relations) {
      if (!want(relation.name) || seen.has(relation.name)) continue;
      seen.add(relation.name);
      lines.push(`${relation.name} {\n  id\n  label\n}`);
    }
  }
  if (lines.length === 0) lines.push("id");
  return lines.join("\n");
}

export interface CollectionQuerySpec {
  currentPage?: number;
  itemsPerPage?: number;
  filters?: Record<string, unknown>;
  order?: OrderCondition[];
  fields?: string[];
}

export interface BuiltDocument {
  query: string;
  variables: Record<string, unknown>;
}

export function buildItemQuery(entity: EntitySchema): BuiltDocument {
  if (!entity.queryItem) throw new Error(`[documents] "${entity.name}" no expone query item`);
  const selection = buildSelection(entity, { includeRelations: true });
  return {
    query: `query Item($id: ID!) {\n  ${entity.queryItem}(id: $id) {\n${indent(selection, 2)}\n  }\n}`,
    variables: {},
  };
}

export function buildCollectionQuery(
  entity: EntitySchema,
  spec: CollectionQuerySpec = {},
): BuiltDocument {
  if (!entity.queryCollection)
    throw new Error(`[documents] "${entity.name}" no expone query collection`);
  const variables: Record<string, unknown> = {};
  const argDecls: string[] = [];
  const callArgs: string[] = [];

  if (entity.collectionKind === "page-connection") {
    const currentPage = spec.currentPage ?? 1;
    const itemsPerPage = spec.itemsPerPage ?? 10;
    variables.currentPage = currentPage;
    variables.itemsPerPage = itemsPerPage;
    argDecls.push("$currentPage: Int", "$itemsPerPage: Int");
    callArgs.push("currentPage: $currentPage", "itemsPerPage: $itemsPerPage");
  }

  for (const arg of entity.filterArgs) {
    const value = spec.filters?.[arg.name];
    if (value === undefined || value === null) continue;
    const variable = `$f_${arg.name}`;
    variables[`f_${arg.name}`] = value;
    argDecls.push(`${variable}: ${arg.type}`);
    callArgs.push(`${arg.name}: ${variable}`);
  }

  if (entity.orderInput && spec.order && spec.order.length > 0) {
    variables.order = spec.order;
    argDecls.push(`$order: [${entity.orderInput}]`);
    callArgs.push("order: $order");
  }

  const selection = buildSelection(entity, { fields: spec.fields });
  let body: string;
  if (entity.collectionKind === "page-connection") {
    body = `collection {\n${indent(selection, 2)}\n}\npaginationInfo {\n  itemsPerPage\n  lastPage\n  totalCount\n  currentPage\n  hasNextPage\n}`;
  } else if (entity.collectionKind === "cursor-connection") {
    body = `edges {\n  node {\n${indent(selection, 4)}\n  }\n}\ntotalCount`;
  } else {
    body = selection;
  }

  const args = callArgs.length > 0 ? `(${callArgs.join(", ")})` : "";
  if (args.length) {
    return {
      query: `query Collection(${argDecls.join(", ")}) {\n  ${entity.queryCollection}${args} {\n${indent(body, 2)}\n  }\n}`,
      variables,
    };
  }
  return {
    query: `query Collection {\n  ${entity.queryCollection}${args} {\n${indent(body, 2)}\n  }\n}`,
    variables,
  };
}

export function buildMutation(entity: EntitySchema, mutation: MutationSchema): BuiltDocument {
  if (!mutation.returnsField) {
    throw new Error(`[documents] no se pudo inferir el campo de retorno de "${mutation.field}"`);
  }
  const operation = mutation.kind[0]!.toUpperCase() + mutation.kind.slice(1);
  const selection =
    mutation.kind === "delete" ? "id" : buildSelection(entity, { includeRelations: true });
  return {
    query: `mutation ${operation}($input: ${mutation.inputType}!) {\n  ${mutation.field}(input: $input) {\n    ${mutation.returnsField} {\n${indent(selection, 4)}\n    }\n  }\n}`,
    variables: {},
  };
}

/**
 * Convierte datos de formulario/UI al payload del input GraphQL: descarta
 * claves que no existen en el input, y resuelve relaciones a IRIs de API
 * Platform (`/api/{plural}/{id}`) cuando el valor es un objeto con `id`.
 */
export function toMutationInput(
  entity: EntitySchema,
  mutation: MutationSchema,
  data: Record<string, unknown>,
  entities: Record<string, EntitySchema>,
): Record<string, unknown> {
  const input: Record<string, unknown> = {};
  for (const field of mutation.inputFields) {
    if (field.name === "clientMutationId") continue;
    if (field.name === "id" && mutation.kind === "create") continue;
    const value = data[field.name];
    if (value === undefined) continue;
    input[field.name] = normalizeInputValue(field, value, entities);
  }
  return input;
}

function normalizeInputValue(
  field: SchemaInputField,
  value: unknown,
  entities: Record<string, EntitySchema>,
): unknown {
  if (!field.isRelation) return value;
  const target = entities[field.namedType];
  const toIri = (item: unknown): unknown => {
    if (item === null || item === undefined) return null;
    if (typeof item === "string" || typeof item === "number") return String(item);
    if (typeof item === "object") {
      const record = item as Record<string, unknown>;
      if (record["@id"]) return record["@id"];
      if (record.id !== undefined) {
        if (target?.queryCollection) return `/api/${target.queryCollection}/${String(record.id)}`;
        return String(record.id);
      }
    }
    return item;
  };
  if (field.isList && Array.isArray(value)) return value.map(toIri);
  return toIri(value);
}
