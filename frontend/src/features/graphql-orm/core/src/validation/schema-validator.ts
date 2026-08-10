import { z, type ZodTypeAny } from 'zod';
import { isInputObjectType, type GraphQLInputObjectType, type GraphQLInputField } from 'graphql';
import { describeField } from '../schema/entity-descriptor';
import type { SchemaRegistry } from '../schema/schema-registry';
import type { FieldDescriptor } from '../../../types';

const SCALAR_MAP: Record<string, () => ZodTypeAny> = {
  String: () => z.string(),
  ID: () => z.string(),
  Int: () => z.number().int(),
  Float: () => z.number(),
  Boolean: () => z.boolean(),
  DateTime: () => z.string().refine((v) => !Number.isNaN(Date.parse(v)), 'Fecha/hora inválida (ISO 8601)'),
  Date: () => z.string().refine((v) => !Number.isNaN(Date.parse(v)), 'Fecha inválida'),
};

export function registerScalar(name: string, factory: () => ZodTypeAny): void {
  SCALAR_MAP[name] = factory;
}

function zodForField(registry: SchemaRegistry, field: FieldDescriptor, guard: Set<string>): ZodTypeAny {
  let base: ZodTypeAny;

  if (field.kind === 'scalar') {
    base = (SCALAR_MAP[field.typeName] ?? (() => z.unknown()))();
  } else if (field.kind === 'enum') {
    const enumType = registry.schema.getType(field.typeName) as any;
    const values = enumType?.getValues?.().map((v: any) => v.value) ?? [];
    base = values.length ? z.enum(values as [string, ...string[]]) : z.string();
  } else if (field.kind === 'input-object') {
    // Relación anidada permitida (sección 3.7) — se valida recursivamente contra el InputObject real
    const inputType = registry.schema.getType(field.typeName);
    if (isInputObjectType(inputType)) {
      if (guard.has(field.typeName)) {
        // Ciclo de tipos de entrada (p. ej. input autorreferente) — no se puede expandir infinito
        base = z.unknown();
      } else {
        guard.add(field.typeName);
        const shape: Record<string, ZodTypeAny> = {};
        for (const f of Object.values((inputType as GraphQLInputObjectType).getFields()) as GraphQLInputField[]) {
          shape[f.name] = zodForField(registry, describeField(f), guard);
        }
        guard.delete(field.typeName);
        base = z.object(shape);
      }
    } else {
      base = z.unknown();
    }
  } else {
    // Relación como IRI (string) — caso más común (sección 3.7)
    base = z.string().refine((v) => v.startsWith('/'), 'Se esperaba un IRI (ej. "/books/1")');
  }

  if (field.isList) base = z.array(base);
  return field.isNonNull ? base : base.nullish();
}

export function createInputValidator(registry: SchemaRegistry, inputFields: FieldDescriptor[]) {
  const shape: Record<string, ZodTypeAny> = {};
  const guard = new Set<string>();
  for (const f of inputFields) {
    if (f.name === 'clientMutationId') continue; // gestionado internamente
    shape[f.name] = zodForField(registry, f, guard);
  }

  const schema = z.object(shape);
  return {
    schema,
    validate(input: unknown) {
      return schema.safeParse(input);
    },
  };
}
