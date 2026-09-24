import { describe, expect, it } from 'vitest'
import type { EntityFieldSchema, EntitySchema, MutationSchema, SchemaInputField } from '@/core/graphql/types'
import { formFieldEntries, pickInputFields } from '@/features/entity-crud/form/formFields'

function entityField(name: string, overrides: Partial<EntityFieldSchema> = {}): EntityFieldSchema {
  return {
    name,
    type: 'String',
    namedType: 'String',
    kind: 'SCALAR',
    required: false,
    isList: false,
    isRelation: false,
    isSubcollection: false,
    enumValues: [],
    ...overrides,
  }
}

function input(name: string, overrides: Partial<SchemaInputField> = {}): SchemaInputField {
  return { name, type: 'String', namedType: 'String', kind: 'SCALAR', required: false, isList: false, isRelation: false, enumValues: [], ...overrides }
}

const entity = {
  name: 'Action',
  fields: [
    entityField('id', { namedType: 'ID' }),
    entityField('nombre'),
    entityField('icon', { namedType: 'Icon', kind: 'OBJECT', isRelation: true }),
    entityField('createdAt', { namedType: 'DateTime' }),
    entityField('permisos', { isSubcollection: true }),
  ],
} as EntitySchema

const mutation = {
  kind: 'update',
  inputFields: [input('id', { namedType: 'ID' }), input('nombre'), input('icon', { namedType: 'Icon', isRelation: true }), input('clientMutationId')],
} as MutationSchema

describe('formFieldEntries', () => {
  it('usa los formFields visibles, ordenados por position y con su label', () => {
    const entries = formFieldEntries(
      [
        { field: 'icon', position: 2, visible: true, label: 'Ícono' },
        { field: 'createdAt', position: 3, visible: false },
        { field: 'nombre', position: 1, visible: true, label: null },
      ],
      entity,
    )
    expect(entries).toEqual([{ name: 'nombre' }, { name: 'icon', label: 'Ícono' }])
  })

  it('sin formFields cae a los fields del schema, sin subcolecciones', () => {
    expect(formFieldEntries([], entity).map((e) => e.name)).toEqual(['id', 'nombre', 'icon', 'createdAt'])
  })

  it('con formFields todos ocultos no hay campos (no cae al schema)', () => {
    expect(formFieldEntries([{ field: 'nombre', position: 1, visible: false }], entity)).toEqual([])
  })
})

describe('pickInputFields', () => {
  const entries = formFieldEntries([], entity)

  it('update: conserva id y descarta lo que la mutación no recibe', () => {
    expect(pickInputFields(entries, mutation, 'update').map((f) => f.name)).toEqual(['id', 'nombre', 'icon'])
  })

  it('create: descarta id', () => {
    expect(pickInputFields(entries, mutation, 'create').map((f) => f.name)).toEqual(['nombre', 'icon'])
  })

  it('aplica el label sobre una copia sin mutar el metadata', () => {
    const [picked] = pickInputFields([{ name: 'nombre', label: 'Nombre visible' }], mutation, 'create')
    expect(picked?.label).toBe('Nombre visible')
    expect(mutation.inputFields.find((f) => f.name === 'nombre')?.label).toBeUndefined()
  })
})
