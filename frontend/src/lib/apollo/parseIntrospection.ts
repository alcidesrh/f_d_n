/**
 * Traduce la introspección GraphQL cruda de API Platform a la estructura
 * semántica plana de `EntitySchema`. Evita recursar el schema: las relaciones
 * se aplanan a un nivel y los subrecursos tipo connection se etiquetan.
 */

import type { EntityFieldSchema, EntitySchema, MutationSchema, SchemaArg } from "./types";

/** Subconjunto mínimo del JSON de introspección que necesita el parser. */
export interface IntrospectionRef {
  kind: string;
  name?: string | null;
  ofType?: IntrospectionRef | null;
}

export interface IntrospectionArg {
  name: string;
  type: IntrospectionRef;
  defaultValue?: string | null;
}

export interface IntrospectionField {
  name: string;
  type: IntrospectionRef;
  args: IntrospectionArg[];
}

export interface IntrospectionType {
  kind: string;
  name?: string | null;
  fields?: IntrospectionField[] | null;
  inputFields?: IntrospectionArg[] | null;
  interfaces?: Array<{ name: string }> | null;
  enumValues?: Array<{ name: string }> | null;
}

export interface IntrospectionSchemaLike {
  queryType: { name: string };
  mutationType: { name: string } | null;
  types: IntrospectionType[];
}

const ITEM_ARG = "id";

function unwrap(ref: IntrospectionRef): IntrospectionRef {
  let current = ref;
  while (current.ofType) current = current.ofType;
  return current;
}

function signature(ref: IntrospectionRef): string {
  if (ref.kind === "NON_NULL") return `${signature(ref.ofType!)}!`;
  if (ref.kind === "LIST") return `[${signature(ref.ofType!)}]`;
  return ref.name ?? "unknown";
}
function slugify(text) {
  return text
    .toString() // Convert to string (safeguard)
    .normalize("NFD") // Split accented characters into their base letters
    .replace(/[\u0300-\u036f]/g, "") // Remove the accent marks
    .toLowerCase() // Convert to lowercase
    .trim() // Remove leading and trailing spaces
    .replace(/\s+/g, "-") // Replace spaces with hyphens
    .replace(/[^\w-]+/g, "") // Remove all non-word characters except hyphens
    .replace(/--+/g, "-"); // Replace multiple consecutive hyphens with a single one
}
function describeType(ref: IntrospectionRef): {
  namedType: string;
  required: boolean;
  isList: boolean;
  type: string;
} {
  const named = unwrap(ref);

  console.log(signature(ref));
  return {
    namedType: named.name ?? "",
    required: ref.kind === "NON_NULL" || (ref.ofType?.kind === "NON_NULL" && ref.kind === "LIST"),
    isList: ref.kind === "LIST" || ref.ofType?.kind === "LIST",
    type: signature(ref),
  };
}

function toSchemaArg(arg: IntrospectionArg): SchemaArg {
  const info = describeType(arg.type);
  return {
    name: arg.name,
    type: info.type,
    namedType: info.namedType,
    required: info.required,
    isList: info.isList,
  };
}

const PAGINATION_ARGS = new Set([
  "currentPage",
  "itemsPerPage",
  "first",
  "last",
  "before",
  "after",
  "order",
]);

export function parseIntrospection(schema: IntrospectionSchemaLike): Record<string, EntitySchema> {
  const types = new Map<string, IntrospectionType>();
  for (const type of schema.types) {
    if (type.name) types.set(type.name, type);
  }

  const queryType = types.get(schema.queryType.name);
  const mutationType = schema.mutationType ? types.get(schema.mutationType.name) : undefined;
  const queryFields = queryType?.fields ?? [];
  const mutationFields = mutationType?.fields ?? [];

  const nodeTypes = new Set<string>();
  for (const type of schema.types) {
    if (type.kind !== "OBJECT") continue;
    if (type.interfaces?.some((iface) => iface.name === "Node")) {
      nodeTypes.add(type.name!);
    }
  }

  const detected = new Map<
    string,
    {
      queryItem: string | null;
      queryCollection: string | null;
      collectionKind: EntitySchema["collectionKind"];
      collectionType: string | null;
      paginationType: string | null;
      orderInput: string | null;
      orderFields: string[];
      itemArgs: SchemaArg[];
      collectionArgs: SchemaArg[];
      filterArgs: SchemaArg[];
    }
  >();

  const isConnectionType = (typeName: string): boolean => {
    const type = types.get(typeName);
    const names = new Set((type?.fields ?? []).map((f) => f.name));
    return names.has("collection") || names.has("edges");
  };

  /** Entidad envuelta por un tipo connection (PageConnection/CursorConnection). */
  const connectionEntity = (typeName: string): string | null => {
    const type = types.get(typeName);
    const fields = type?.fields ?? [];
    const collectionField = fields.find((f) => f.name === "collection");
    if (collectionField) return describeType(collectionField.type).namedType;
    const edgesField = fields.find((f) => f.name === "edges");
    if (edgesField) {
      const edgeType = types.get(describeType(edgesField.type).namedType);
      const nodeField = edgeType?.fields?.find((f) => f.name === "node");
      if (nodeField) return describeType(nodeField.type).namedType;
    }
    return null;
  };

  for (const field of queryFields) {
    if (field.name === "node") continue;
    const info = describeType(field.type);
    const isConnectionReturn = isConnectionType(info.namedType);
    // El target puede ser la entidad directa (item/list) o la entidad envuelta
    // en una connection (page/cursor), que no implementa Node por sí misma.
    const targetName = isConnectionReturn ? connectionEntity(info.namedType) : info.namedType;
    if (!targetName || !nodeTypes.has(targetName)) continue;

    const hasIdArg = field.args.some((a) => a.name === ITEM_ARG);
    const returnType = types.get(info.namedType);
    const returnFieldNames = new Set((returnType?.fields ?? []).map((f) => f.name));
    const isPageConnection =
      returnFieldNames.has("collection") && returnFieldNames.has("paginationInfo");
    const isCursorConnection = returnFieldNames.has("edges");
    const isSingle = !info.isList && returnType?.kind === "OBJECT";

    let slot = detected.get(targetName);
    if (!slot) {
      slot = {
        queryItem: null,
        queryCollection: null,
        collectionKind: null,
        collectionType: null,
        paginationType: null,
        orderInput: null,
        orderFields: [],
        itemArgs: [],
        collectionArgs: [],
        filterArgs: [],
      };
      detected.set(targetName, slot);
    }

    if (isSingle && hasIdArg && !slot.queryItem) {
      slot.queryItem = field.name;
      slot.itemArgs = field.args.map(toSchemaArg);
      continue;
    }

    if (isSingle && isConnectionReturn) {
      // Conexión: se registra como colección (la entidad está envuelta).
      if (!slot.queryCollection) {
        slot.queryCollection = field.name;
        slot.collectionKind = isPageConnection
          ? "page-connection"
          : isCursorConnection
            ? "cursor-connection"
            : null;
        slot.collectionType = info.namedType;
        slot.collectionArgs = field.args.map(toSchemaArg);
        slot.filterArgs = slot.collectionArgs.filter((arg) => !PAGINATION_ARGS.has(arg.name));
        const orderArg = field.args.find((a) => a.name === "order");
        if (orderArg) {
          slot.orderInput = describeType(orderArg.type).namedType;
          const orderType = types.get(slot.orderInput);
          slot.orderFields = (orderType?.inputFields ?? []).map((f) => f.name);
        }
        const paginationField = (returnType?.fields ?? []).find(
          (f) => f.name === "paginationInfo" || f.name === "pageInfo",
        );
        slot.paginationType = paginationField ? describeType(paginationField.type).namedType : null;
      }
      continue;
    }

    if (!isSingle) {
      // Lista o conexión de la entidad.
      if (!slot.queryCollection) {
        slot.queryCollection = field.name;
        slot.collectionKind = info.isList
          ? "list"
          : isPageConnection
            ? "page-connection"
            : isCursorConnection
              ? "cursor-connection"
              : "single";
        slot.collectionType = info.namedType;
        slot.collectionArgs = field.args.map(toSchemaArg);
        slot.filterArgs = slot.collectionArgs.filter((arg) => !PAGINATION_ARGS.has(arg.name));
        const orderArg = field.args.find((a) => a.name === "order");
        if (orderArg) {
          slot.orderInput = describeType(orderArg.type).namedType;
          const orderType = types.get(slot.orderInput);
          slot.orderFields = (orderType?.inputFields ?? []).map((f) => f.name);
        }
        const paginationField = (returnType?.fields ?? []).find(
          (f) => f.name === "paginationInfo" || f.name === "pageInfo",
        );
        slot.paginationType = paginationField ? describeType(paginationField.type).namedType : null;
      }
    }
  }

  const empty = (name: string): EntitySchema => ({
    name,
    slug: slugify(name),
    queryItem: null,
    queryCollection: null,
    collectionKind: null,
    collectionType: null,
    paginationType: null,
    orderInput: null,
    orderFields: [],
    itemArgs: [],
    collectionArgs: [],
    filterArgs: [],
    fields: [],
    scalarFields: [],
    relations: [],
    subcollections: [],
    create: null,
    update: null,
    delete: null,
  });

  const entities = new Map<string, EntitySchema>();
  for (const name of nodeTypes) {
    const type = types.get(name);
    if (!type?.fields) continue;
    const schema = empty(name);
    for (const field of type.fields) {
      const info = describeType(field.type);
      const namedInfo = types.get(info.namedType);
      const kind = namedInfo?.kind ?? "OBJECT";
      const isScalar = kind === "SCALAR" || kind === "ENUM";
      const isConnection = !isScalar && isConnectionType(info.namedType);
      const entry: EntityFieldSchema = {
        name: field.name,
        type: info.type,
        namedType: info.namedType,
        kind,
        required: info.required,
        isList: info.isList,
        isRelation: !isScalar && !isConnection,
        isSubcollection: !isScalar && isConnection,
        enumValues: kind === "ENUM" ? (namedInfo?.enumValues ?? []).map((v) => v.name) : [],
      };
      schema.fields.push(entry);
      if (isScalar) schema.scalarFields.push(field.name);
      else if (entry.isSubcollection) schema.subcollections.push(entry);
      else schema.relations.push(entry);
    }

    const slot = detected.get(name);
    if (slot) {
      schema.queryItem = slot.queryItem;
      schema.queryCollection = slot.queryCollection;
      schema.collectionKind = slot.collectionKind;
      schema.collectionType = slot.collectionType;
      schema.paginationType = slot.paginationType;
      schema.orderInput = slot.orderInput;
      schema.orderFields = slot.orderFields;
      schema.itemArgs = slot.itemArgs;
      schema.collectionArgs = slot.collectionArgs;
      schema.filterArgs = slot.filterArgs;
    }
    entities.set(name, schema);
  }

  for (const field of mutationFields) {
    let kind: MutationSchema["kind"] | null = null;
    if (field.name.startsWith("create")) kind = "create";
    else if (field.name.startsWith("update")) kind = "update";
    else if (field.name.startsWith("delete")) kind = "delete";
    if (!kind) continue;

    const entityName = field.name.slice(kind.length);
    if (!nodeTypes.has(entityName)) continue;

    const inputTypeName = field.args[0] ? describeType(field.args[0].type).namedType : "";
    const payloadTypeName = describeType(field.type).namedType;
    const payloadType = types.get(payloadTypeName);
    const inputType = types.get(inputTypeName);
    const schema = entities.get(entityName);
    let returnsField = "";

    for (const payloadField of payloadType?.fields ?? []) {
      if (describeType(payloadField.type).namedType === entityName) {
        returnsField = payloadField.name;
        break;
      }
    }

    const mutation: MutationSchema = {
      kind,
      field: field.name,
      inputType: inputTypeName,
      payloadType: payloadTypeName,
      returnsField,
      inputFields: (inputType?.inputFields ?? []).map((inputField) => {
        const info = describeType(inputField.type);
        const namedKind = types.get(info.namedType)?.kind ?? "SCALAR";
        // API Platform tipa las relaciones del input como IRIs (String/[String]);
        // el tipo real se resuelve contra los campos de la entidad ya parseados.
        const entityField = schema?.fields.find((f) => f.name === inputField.name && f.isRelation);
        return {
          name: inputField.name,
          type: info.type,
          namedType: entityField ? entityField.namedType : info.namedType,
          kind: entityField ? "OBJECT" : namedKind,
          required: info.required,
          isList: info.isList,
          isRelation: Boolean(entityField),
          enumValues:
            entityField || namedKind !== "ENUM"
              ? []
              : (types.get(info.namedType)?.enumValues ?? []).map((v) => v.name),
        };
        return result;
      }),
    };

    if (schema) schema[kind] = mutation;
  }

  const result: Record<string, EntitySchema> = {};
  for (const [name, schema] of entities) result[name] = schema;
  return result;
}
