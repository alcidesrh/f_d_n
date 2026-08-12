import type { GraphQLSchema } from 'graphql';
import { isObjectType } from 'graphql';
import { buildEntityDescriptor, describeField, type FieldDescriptor } from '@graphql-orm/core';
import type { ColumnKind, CrudColumn } from '../types';

/** Campos sensibles que nunca se muestran como columna ni se editan en el formulario. */
export const SENSITIVE_FIELDS = new Set([
  'plainPassword',
  'password',
  'token',
  'apiToken',
  'accessToken',
  'refreshToken',
  'clientSecret',
  'secret',
  'apiKey',
  'authorization',
]);

const SCALAR_KIND: Record<string, ColumnKind> = {
  ID: 'string',
  String: 'string',
  Int: 'number',
  Float: 'number',
  Decimal: 'number',
  BigInt: 'number',
  Numeric: 'number',
  Boolean: 'boolean',
  DateTime: 'date',
  Date: 'date',
  Time: 'date',
  Timestamp: 'date',
};

export function humanize(field: string): string {
  const words = field
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/[_-]+/g, ' ')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1));
  return words.join(' ') || field;
}

export function scalarKind(typeName: string): ColumnKind {
  return SCALAR_KIND[typeName] ?? 'string';
}

export function descriptorToColumn(desc: FieldDescriptor): CrudColumn {
  const isRelation = desc.kind === 'object';
  const kind: ColumnKind = desc.kind === 'enum' ? 'enum' : isRelation ? 'relation' : scalarKind(desc.typeName);
  return {
    field: desc.name,
    label: humanize(desc.name),
    kind,
    isList: desc.isList,
    isRelation,
    sortable: !isRelation && !desc.isList,
    filterable: !desc.isList,
    typeName: desc.typeName,
  };
}

/** Nombre del tipo que devuelve la query de colección (grupos de serialización). */
export function getNodeTypeName(schema: GraphQLSchema, entity: string): string {
  const desc = buildEntityDescriptor(schema, entity);
  return desc.queries.collection?.nodeTypeName ?? entity;
}

/** Campos del nodo de colección (scalars, enums y relaciones to-one/to-many). */
export function getNodeFields(schema: GraphQLSchema, entity: string): FieldDescriptor[] {
  const nodeTypeName = getNodeTypeName(schema, entity);
  const nodeType = schema.getType(nodeTypeName);
  if (!nodeType || !isObjectType(nodeType)) return [];
  return Object.values(nodeType.getFields()).map(describeField);
}

export function buildColumns(schema: GraphQLSchema, entity: string): CrudColumn[] {
  return getNodeFields(schema, entity)
    .filter((f) => !SENSITIVE_FIELDS.has(f.name))
    .filter((f) => f.name !== '_id')
    .filter((f) => !(f.kind === 'object' && f.isList)) // sub-colecciones
    .map(descriptorToColumn);
}
