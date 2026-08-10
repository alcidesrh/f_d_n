import type { GraphQLSchema } from 'graphql';
import { isEnumType, isInputObjectType, type GraphQLInputObjectType } from 'graphql';
import type { FormKitSchemaNode } from '@formkit/core';
import { buildEntityDescriptor, describeField, type FieldDescriptor } from '@graphql-orm/core';
import { getNodeFields, humanize, SENSITIVE_FIELDS, scalarKind } from './entity-meta';

export type FormMode = 'create' | 'update';

interface RelationMarker {
  typeName: string;
  multiple: boolean;
}

const IGNORED_INPUT_FIELDS = new Set(['clientMutationId', 'id', '_id']);

function enumOptions(schema: GraphQLSchema, typeName: string): Array<{ label: string; value: string }> {
  const type = schema.getType(typeName);
  if (!type || !isEnumType(type)) return [];
  return type.getValues().map((v) => ({ label: v.name, value: v.name }));
}

function baseAttrs(f: FieldDescriptor, label: string) {
  return { name: f.name, label, placeholder: label };
}

function scalarNode(f: FieldDescriptor, label: string, mode: FormMode): FormKitSchemaNode {
  const kind = scalarKind(f.typeName);
  const node: Record<string, unknown> = { ...baseAttrs(f, label) };
  if (kind === 'number') {
    node.$formkit = 'InputNumber';
  } else if (kind === 'boolean') {
    node.$formkit = 'ToggleSwitch';
  } else if (kind === 'date') {
    node.$formkit = 'DatePicker';
    node.dateFormat = 'dd/mm/yy';
    node.showIcon = true;
  } else {
    node.$formkit = 'InputText';
  }
  if (mode === 'create' && f.isNonNull) node.validation = 'required';
  return node as unknown as FormKitSchemaNode;
}

function enumNode(
  f: FieldDescriptor,
  label: string,
  mode: FormMode,
  options: Array<{ label: string; value: string }>,
): FormKitSchemaNode {
  const node: Record<string, unknown> = {
    ...baseAttrs(f, label),
    $formkit: 'Select',
    options,
    optionLabel: 'label',
    optionValue: 'value',
    showClear: true,
  };
  if (mode === 'create' && f.isNonNull) node.validation = 'required';
  return node as unknown as FormKitSchemaNode;
}

function relationNode(f: FieldDescriptor, rel: FieldDescriptor, label: string, mode: FormMode): FormKitSchemaNode {
  const multiple = f.isList || rel.isList;
  const node: Record<string, unknown> = {
    ...baseAttrs(f, label),
    $formkit: multiple ? 'MultiSelect' : 'Select',
    typeName: rel.typeName,
    relation: true,
    options: [] as Array<{ label: string; value: string }>,
    optionLabel: 'label',
    optionValue: 'value',
    showClear: true,
    ...(multiple ? { display: 'chip' } : {}),
  };
  if (mode === 'create' && f.isNonNull) node.validation = 'required';
  return node as unknown as FormKitSchemaNode;
}

function inputObjectNode(
  schema: GraphQLSchema,
  f: FieldDescriptor,
  label: string,
  mode: FormMode,
  seen: Set<string>,
): FormKitSchemaNode | null {
  const type = schema.getType(f.typeName);
  if (!type || !isInputObjectType(type) || seen.has(f.typeName)) return null;
  seen.add(f.typeName);
  const children = buildInputFields(schema, type, mode, seen, new Map());
  seen.delete(f.typeName);
  return { $formkit: 'group', name: f.name, label, children };
}

function buildInputFields(
  schema: GraphQLSchema,
  inputType: GraphQLInputObjectType,
  mode: FormMode,
  seen: Set<string>,
  relMap: Map<string, FieldDescriptor>,
): FormKitSchemaNode[] {
  return Object.values(inputType.getFields())
    .map(describeField)
    .filter((f) => !IGNORED_INPUT_FIELDS.has(f.name) && !SENSITIVE_FIELDS.has(f.name))
    .map((f) => {
      const label = humanize(f.name);

      if (f.kind === 'input-object') return inputObjectNode(schema, f, label, mode, seen);
      if (f.kind === 'enum') return enumNode(f, label, mode, enumOptions(schema, f.typeName));
      if (f.kind === 'object') return relationNode(f, f, label, mode);

      const rel = relMap.get(f.name);
      if (rel && rel.kind === 'object' && !SENSITIVE_FIELDS.has(rel.name)) {
        return relationNode(f, rel, label, mode);
      }

      return scalarNode(f, label, mode);
    })
    .filter((n): n is FormKitSchemaNode => n !== null);
}

/** Relaciones to-one del nodo de colección, para resolver IRIs de texto en los inputs. */
function relationFieldMap(schema: GraphQLSchema, entity: string): Map<string, FieldDescriptor> {
  return new Map(
    getNodeFields(schema, entity)
      .filter((f) => f.kind === 'object')
      .map((f) => [f.name, f] as const),
  );
}

export function buildFormSchema(schema: GraphQLSchema, entity: string, mode: FormMode): FormKitSchemaNode[] {
  const desc = buildEntityDescriptor(schema, entity);
  const op = mode === 'create' ? desc.mutations.create : desc.mutations.update;
  if (!op || !op.inputTypeName) return [];

  const inputType = schema.getType(op.inputTypeName);
  if (!inputType || !isInputObjectType(inputType)) return [];

  return buildInputFields(schema, inputType, mode, new Set(), relationFieldMap(schema, entity));
}

/** Marca si un nodo generado es una relación (para que la vista cargue las opciones). */
export function isRelationNode(node: FormKitSchemaNode): node is FormKitSchemaNode & RelationMarker {
  return (
    typeof node === 'object' && node !== null && '$formkit' in node && (node as Record<string, unknown>).relation === true
  );
}
