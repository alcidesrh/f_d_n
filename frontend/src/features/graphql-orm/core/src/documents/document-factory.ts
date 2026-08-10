import type { SchemaRegistry } from '../schema/schema-registry';
import { buildSelectionSet } from './selection-set-builder';
import type { OperationDescriptor, SelectionOptions } from '../../../types';

function argsDeclaration(op: OperationDescriptor): string {
  if (!op.args.length) return '';
  return `(${op.args.map((a) => `$${a.name}: ${a.printedType}`).join(', ')})`;
}

function argsUsage(op: OperationDescriptor): string {
  if (!op.args.length) return '';
  return `(${op.args.map((a) => `${a.name}: $${a.name}`).join(', ')})`;
}

export function buildItemQuery(registry: SchemaRegistry, typeName: string, options?: SelectionOptions) {
  const op = registry.describe(typeName).queries.item;
  if (!op) throw new Error(`"${typeName}" no tiene query de ítem en el schema.`);
  const selection = buildSelectionSet(registry, typeName, options);
  return {
    op,
    document: `query ${op.fieldName}Item${argsDeclaration(op)} {
${op.fieldName}${argsUsage(op)} { ${selection} }
}`,
  };
}

export function buildCollectionQuery(registry: SchemaRegistry, typeName: string, options?: SelectionOptions) {
  const op = registry.describe(typeName).queries.collection;
  if (!op) throw new Error(`"${typeName}" no tiene query de colección en el schema.`);
  const nodeSelection = buildSelectionSet(registry, op.nodeTypeName ?? typeName, options);

  let body: string;
  switch (op.collectionShape) {
    case 'relay':
      body = `edges { cursor node { ${nodeSelection} } } pageInfo { startCursor endCursor hasNextPage hasPreviousPage } totalCount`;
      break;
    case 'page':
      body = `collection { ${nodeSelection} } paginationInfo { itemsPerPage lastPage totalCount hasNextPage }`;
      break;
    default:
      body = nodeSelection; // 'flat': la query YA devuelve la lista de nodos
  }

  return {
    op,
    document: `query ${op.fieldName}Collection${argsDeclaration(op)} {
${op.fieldName}${argsUsage(op)} { ${body} }
}`,
  };
}

function buildMutation(registry: SchemaRegistry, typeName: string, op?: OperationDescriptor) {
  if (!op) throw new Error(`"${typeName}" no tiene mutación en el schema.`);
  const payloadSelection = op.payloadEntityField
    ? `${op.payloadEntityField} { ${buildSelectionSet(registry, typeName)} }`
    : buildSelectionSet(registry, typeName);

  return {
    op,
    document: `mutation ${op.fieldName}${argsDeclaration(op)} {
${op.fieldName}${argsUsage(op)} { ${payloadSelection} clientMutationId }
}`,
  };
}

export const buildCreateMutation = (registry: SchemaRegistry, typeName: string) =>
  buildMutation(registry, typeName, registry.describe(typeName).mutations.create);

export const buildUpdateMutation = (registry: SchemaRegistry, typeName: string) =>
  buildMutation(registry, typeName, registry.describe(typeName).mutations.update);

export const buildDeleteMutation = (registry: SchemaRegistry, typeName: string) => {
  const op = registry.describe(typeName).mutations.delete;
  if (!op) throw new Error(`"${typeName}" no admite borrado (mutación "delete" no definida).`);
  const payloadSelection = op.payloadEntityField ? `${op.payloadEntityField} { id }` : '';
  return {
    op,
    document: `mutation ${op.fieldName}${argsDeclaration(op)} {
${op.fieldName}${argsUsage(op)} { ${payloadSelection} clientMutationId }
}`,
  };
};

/** Escape hatch genérico — cualquier query/mutation detectada en customOperations */
export function buildCustomOperation(
  registry: SchemaRegistry,
  typeName: string,
  operationName: string,
  options?: SelectionOptions,
) {
  const op = registry.describe(typeName).customOperations.find((o) => o.fieldName === operationName);
  if (!op) throw new Error(`Operación custom "${operationName}" no encontrada para "${typeName}".`);
  const keyword = op.kind === 'mutation' ? 'mutation' : 'query';
  const body = op.returnFields.length ? buildSelectionSet(registry, op.returnTypeName, options) : '';
  return {
    op,
    document: `${keyword} ${op.fieldName}${argsDeclaration(op)} { ${op.fieldName}${argsUsage(op)} ${body ? `{ ${body} }` : ''} }`,
  };
}
