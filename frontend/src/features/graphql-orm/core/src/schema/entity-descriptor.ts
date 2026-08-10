import {
  GraphQLSchema,
  GraphQLObjectType,
  GraphQLInputObjectType,
  type GraphQLField,
  type GraphQLArgument,
  isNonNullType,
  isListType,
  isScalarType,
  isEnumType,
  isObjectType,
  isInputObjectType,
  getNamedType,
} from 'graphql';
import type {
  CollectionShape,
  EntityDescriptor,
  FieldDescriptor,
  FieldKind,
  OperationArg,
  OperationDescriptor,
} from '../../../types';

function unwrap(type: any): { named: any; isNonNull: boolean; isList: boolean } {
  let isNonNull = false;
  let isList = false;
  let t = type;
  if (isNonNullType(t)) {
    isNonNull = true;
    t = t.ofType;
  }
  if (isListType(t)) {
    isList = true;
    t = t.ofType;
    if (isNonNullType(t)) {
      t = t.ofType;
    }
  }
  return { named: getNamedType(t), isNonNull, isList };
}

export function describeField(field: GraphQLField<any, any> | { name: string; type: any }): FieldDescriptor {
  const { named, isNonNull, isList } = unwrap(field.type);
  const kind: FieldKind = isScalarType(named)
    ? 'scalar'
    : isEnumType(named)
      ? 'enum'
      : isObjectType(named)
        ? 'object'
        : isInputObjectType(named)
          ? 'input-object'
          : 'unknown';
  return { name: field.name, typeName: named.name, kind, isList, isNonNull };
}

function describeArgs(args: readonly GraphQLArgument[]): OperationArg[] {
  return args.map((a) => {
    const { named, isNonNull, isList } = unwrap(a.type);
    return {
      name: a.name,
      typeName: named.name,
      printedType: a.type.toString(),
      isNonNull,
      isList,
    };
  });
}

function describeReturnType(
  schema: GraphQLSchema,
  field: GraphQLField<any, any>,
): { returnTypeName: string; returnFields: FieldDescriptor[] } {
  const { named } = unwrap(field.type);
  if (!isObjectType(named)) return { returnTypeName: named.name, returnFields: [] };
  const fields = Object.values(named.getFields()).map(describeField);
  return { returnTypeName: named.name, returnFields: fields };
}

/** Detecta la forma real de una colección inspeccionando sus campos (sección 3.3). No asume Relay. */
function describeCollectionOperation(schema: GraphQLSchema, field: GraphQLField<any, any>): OperationDescriptor {
  const { returnTypeName, returnFields } = describeReturnType(schema, field);
  const fieldNames = new Set(returnFields.map((f) => f.name));
  let collectionShape: CollectionShape = 'flat';
  let nodeTypeName: string | undefined;

  if (fieldNames.has('edges') && fieldNames.has('pageInfo')) {
    collectionShape = 'relay';
    const edgesField = returnFields.find((f) => f.name === 'edges');
    if (edgesField) {
      const edgesType = schema.getType(edgesField.typeName);
      if (edgesType && isObjectType(edgesType)) {
        const nodeField = edgesType.getFields()['node'];
        if (nodeField) nodeTypeName = unwrap(nodeField.type).named.name;
      }
    }
  } else if (fieldNames.has('collection') && fieldNames.has('paginationInfo')) {
    collectionShape = 'page';
    const collField = returnFields.find((f) => f.name === 'collection')!;
    nodeTypeName = collField.typeName;
  } else {
    collectionShape = 'flat';
    nodeTypeName = unwrap(field.type).named.name;
  }

  return {
    fieldName: field.name,
    kind: 'query-collection',
    args: describeArgs(field.args),
    returnTypeName,
    returnFields,
    collectionShape,
    nodeTypeName,
  };
}

function describeMutationOperation(
  schema: GraphQLSchema,
  field: GraphQLField<any, any>,
  entityTypeName: string,
): OperationDescriptor {
  const { returnTypeName, returnFields } = describeReturnType(schema, field);

  // El payload envuelve la entidad en un campo lower-camel-case con el nombre del tipo, p.ej. book
  const payloadEntityField = returnFields.find(
    (f) => f.kind === 'object' && f.name.toLowerCase() === entityTypeName.toLowerCase(),
  )?.name;

  const inputArg = field.args.find((a) => a.name === 'input');
  let inputTypeName: string | undefined;
  let inputFields: FieldDescriptor[] = [];

  if (inputArg) {
    const { named } = unwrap(inputArg.type);
    inputTypeName = named.name;
    if (isInputObjectType(named)) {
      inputFields = Object.values((named as GraphQLInputObjectType).getFields()).map(describeField);
    }
  }

  return {
    fieldName: field.name,
    kind: 'mutation',
    args: describeArgs(field.args),
    returnTypeName,
    returnFields,
    inputTypeName,
    inputFields,
    payloadEntityField,
  };
}

export function buildEntityDescriptor(schema: GraphQLSchema, typeName: string): EntityDescriptor {
  const objectType = schema.getType(typeName);
  if (!objectType || !isObjectType(objectType)) {
    throw new Error(`"${typeName}" no existe como GraphQLObjectType en el schema.`);
  }

  const queryType = schema.getQueryType();
  const mutationType = schema.getMutationType();
  const lower = typeName.charAt(0).toLowerCase() + typeName.slice(1);

  const queryFields = queryType?.getFields() ?? {};
  const mutationFields = mutationType?.getFields() ?? {};

  const itemField = queryFields[lower];

  // La query de colección no siempre se llama "books" a secas si hay renombres custom;
  // se identifica por convención de nombre Y, si falla, por heurística de forma de retorno.
  const collectionField =
    queryFields[`${lower}s`] ??
    Object.values(queryFields).find((f) => {
      const { named } = unwrap(f.type);
      return isObjectType(named) && named.name.startsWith(typeName) && f !== itemField;
    });

  const createField = mutationFields[`create${typeName}`];
  const updateField = mutationFields[`update${typeName}`];
  const deleteField = mutationFields[`delete${typeName}`];

  const knownFieldNames = new Set(
    [itemField, collectionField, createField, updateField, deleteField]
      .filter(Boolean)
      .map((f) => f!.name),
  );

  const customOperations: OperationDescriptor[] = [
    ...Object.values(queryFields),
    ...Object.values(mutationFields),
  ]
    .filter((f) => !knownFieldNames.has(f.name) && unwrap(f.type).named.name.startsWith(typeName))
    .map((f) =>
      mutationFields[f.name]
        ? describeMutationOperation(schema, f, typeName)
        : describeCollectionOperation(schema, f),
    );

  return {
    typeName,
    queries: {
      item: itemField
        ? {
            fieldName: itemField.name,
            kind: 'query-item',
            args: describeArgs(itemField.args),
            ...describeReturnType(schema, itemField),
          }
        : undefined,
      collection: collectionField ? describeCollectionOperation(schema, collectionField) : undefined,
    },
    mutations: {
      create: createField ? describeMutationOperation(schema, createField, typeName) : undefined,
      update: updateField ? describeMutationOperation(schema, updateField, typeName) : undefined,
      delete: deleteField ? describeMutationOperation(schema, deleteField, typeName) : undefined,
    },
    customOperations,
  };
}
