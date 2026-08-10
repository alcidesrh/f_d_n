import { getNamedType, isObjectType, isScalarType, isEnumType } from 'graphql';
import type { SchemaRegistry } from '../schema/schema-registry';
import type { FieldDescriptor, FieldKind, SelectionOptions } from '../../../types';

function getFieldsForType(registry: SchemaRegistry, typeName: string): FieldDescriptor[] {
  const rawType = registry.schema.getType(typeName);
  if (!rawType || !isObjectType(rawType)) return [];

  return Object.values(rawType.getFields()).map((f) => {
    const named = getNamedType(f.type);
    const kind: FieldKind = isScalarType(named)
      ? 'scalar'
      : isEnumType(named)
        ? 'enum'
        : isObjectType(named)
          ? 'object'
          : 'unknown';
    return {
      name: f.name,
      typeName: named.name,
      kind,
      isList: f.type.toString().includes('['),
      isNonNull: f.type.toString().includes('!'),
    };
  });
}

export function buildSelectionSet(
  registry: SchemaRegistry,
  typeName: string,
  options: SelectionOptions = {},
  depth = 0,
): string {
  const fields = getFieldsForType(registry, typeName);
  const maxDepth = options.maxDepth ?? 1;
  const parts: string[] = [];

  for (const field of fields) {
    if (field.kind === 'scalar' || field.kind === 'enum' || field.kind === 'unknown') {
      parts.push(field.name);
      continue;
    }

    if (field.kind === 'object') {
      const override = options.include?.[field.name];
      if (override === true || depth < maxDepth || override) {
        const nested = buildSelectionSet(
          registry,
          field.typeName,
          typeof override === 'object' ? override : {},
          depth + 1,
        );
        parts.push(nested ? `${field.name} { ${nested} }` : `${field.name} { id }`);
      } else {
        // If max depth reached, check if the nested object has an 'id' field
        const nestedFields = getFieldsForType(registry, field.typeName);
        const hasId = nestedFields.some((f) => f.name === 'id');
        if (hasId) {
          parts.push(`${field.name} { id }`);
        } else {
          // If object has no 'id' field (like paginationInfo), select its scalar fields
          const scalarParts = nestedFields
            .filter((f) => f.kind === 'scalar' || f.kind === 'enum')
            .map((f) => f.name);
          if (scalarParts.length) {
            parts.push(`${field.name} { ${scalarParts.join(' ')} }`);
          }
        }
      }
    }
  }

  return parts.join('\n');
}
