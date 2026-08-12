import { isObjectType, type GraphQLObjectType } from 'graphql';
import type { GraphQLSchema } from 'graphql';
import { describeField, type CollectionShape } from '@graphql-orm/core';
import type { CrudEntityInfo } from '../types';

const WRAPPER_SUFFIXES = [
  'Connection',
  'CursorConnection',
  'PageConnection',
  'Edge',
  'PageInfo',
  'PaginationInfo',
  'Payload',
];

function isWrapperTypeName(name: string): boolean {
  if (name === 'Node' || name === 'Query' || name === 'Mutation' || name === 'Subscription') return true;
  return WRAPPER_SUFFIXES.some((suffix) => name.endsWith(suffix));
}

function nodeTypeFromEdges(schema: GraphQLSchema, connectionType: GraphQLObjectType): string | undefined {
  const edgesField = connectionType.getFields()['edges'];
  if (!edgesField) return undefined;
  const edgesType = schema.getType(describeField(edgesField).typeName);
  if (!edgesType || !isObjectType(edgesType)) return undefined;
  const nodeField = edgesType.getFields()['node'];
  return nodeField ? describeField(nodeField).typeName : undefined;
}

/** Enumera las entidades listables: todo campo de Query cuya forma sea una colección. */
export function listEntities(schema: GraphQLSchema): CrudEntityInfo[] {
  const queryType = schema.getQueryType();
  if (!queryType) return [];

  const found = new Map<string, CrudEntityInfo>();

  for (const field of Object.values(queryType.getFields())) {
    const ret = describeField(field);
    const returnType = schema.getType(ret.typeName);
    if (!returnType || !isObjectType(returnType)) continue;

    const names = new Set(Object.keys(returnType.getFields()));
    let shape: CollectionShape;
    let nodeTypeName: string | undefined;

    if (names.has('edges') && names.has('pageInfo')) {
      shape = 'relay';
      nodeTypeName = nodeTypeFromEdges(schema, returnType);
    } else if (names.has('collection') && names.has('paginationInfo')) {
      shape = 'page';
      const collField = returnType.getFields()['collection'];
      nodeTypeName = collField ? describeField(collField).typeName : undefined;
    } else if (ret.isList) {
      shape = 'flat';
      nodeTypeName = ret.typeName;
    } else {
      continue; // query de ítem (objeto único sin hull de paginación)
    }

    if (!nodeTypeName || isWrapperTypeName(nodeTypeName)) continue;

    const info: CrudEntityInfo = { typeName: nodeTypeName, collectionField: field.name, shape };
    if (!found.has(nodeTypeName)) found.set(nodeTypeName, info);
  }

  return [...found.values()].sort((a, b) => a.typeName.localeCompare(b.typeName));
}
