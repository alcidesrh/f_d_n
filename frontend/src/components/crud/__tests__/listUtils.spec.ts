import { describe, it, expect } from 'vitest'
import {
  cellDisplay,
  cellLabel,
  cellValue,
  fieldKind,
  idDisplay,
  isEmptyFilterValue,
  noServerFilter,
  rangeToIso,
  resolveFilterArgs,
} from '@/components/crud/listUtils'
import type { EntityFieldSchema, EntitySchema, SchemaArg } from '@/lib/apollo/types'

function field(name: string, namedType: string, isRelation = false): EntityFieldSchema {
  return {
    name,
    type: namedType,
    namedType,
    kind: 'SCALAR',
    required: false,
    isList: false,
    isRelation,
    isSubcollection: false,
    enumValues: [],
  }
}

function arg(name: string): SchemaArg {
  return { name, type: 'String', namedType: 'String', required: false, isList: false }
}

const schema: EntitySchema = {
  name: 'Icon',
  queryItem: 'icon',
  queryCollection: 'icons',
  collectionKind: 'list',
  collectionType: null,
  paginationType: null,
  orderInput: null,
  orderFields: [],
  itemArgs: [],
  collectionArgs: [],
  filterArgs: [arg('icon'), arg('name'), arg('createdAt_after'), arg('createdAt_before')],
  fields: [
    field('id', 'ID'),
    field('icon', 'String'),
    field('name', 'String'),
    field('createdAt', 'Date'),
    field('amount', 'Float'),
    field('active', 'Boolean'),
    field('category', 'Category', true),
  ],
  scalarFields: ['id', 'icon', 'name'],
  relations: [field('category', 'Category', true)],
  subcollections: [],
  create: null,
  update: null,
  delete: null,
}

describe('cellLabel', () => {
  it('passthrough de primitivos', () => {
    expect(cellLabel('abc')).toBe('abc')
    expect(cellLabel(42)).toBe('42')
    expect(cellLabel(true)).toBe('true')
    expect(cellLabel(null)).toBe('')
    expect(cellLabel(undefined)).toBe('')
  })

  it('etiqueta objetos por name → label → id', () => {
    expect(cellLabel({ name: 'A' })).toBe('A')
    expect(cellLabel({ label: 'B' })).toBe('B')
    expect(cellLabel({ id: '/api/buses/3' })).toBe('/api/buses/3')
    expect(cellLabel({ name: 'A', id: 'x' })).toBe('A')
    expect(cellLabel({})).toBe('')
  })

  it('une listas con comas y filtra vacíos', () => {
    expect(cellLabel([{ name: 'A' }, { name: 'B' }])).toBe('A, B')
    expect(cellLabel([{ id: 1 }, null, 'C'])).toBe('1, C')
    expect(cellLabel([])).toBe('')
  })
})

describe('cellValue', () => {
  it('lee la propiedad del item', () => {
    expect(cellValue({ name: 'home' }, 'name')).toBe('home')
    expect(cellValue({ category: { label: 'Ventas' } }, 'category')).toBe('Ventas')
    expect(cellValue({ missing: true }, 'otro')).toBe('')
  })
})

describe('idDisplay', () => {
  it('extrae el número de un IRI de resource', () => {
    expect(idDisplay('/api/icons/1')).toBe('1')
    expect(idDisplay('/api/buses/42')).toBe('42')
    expect(idDisplay('/api/categories/1')).toBe('1')
  })

  it('deja valores que no son IRIs numéricos tal cual', () => {
    expect(idDisplay(7)).toBe('7')
    expect(idDisplay('abc')).toBe('abc')
    expect(idDisplay('/api/entities/abc')).toBe('/api/entities/abc')
    expect(idDisplay(null)).toBe('')
    expect(idDisplay(undefined)).toBe('')
  })
})

describe('cellDisplay', () => {
  it('muestra el id como número, no como IRI', () => {
    expect(cellDisplay({ id: '/api/icons/1' }, { field: 'id' })).toBe('1')
    expect(cellDisplay({ _id: '/api/icons/2' }, { field: '_id' })).toBe('2')
  })

  it('delega en cellValue para el resto de campos', () => {
    expect(cellDisplay({ name: 'home' }, { field: 'name' })).toBe('home')
    expect(cellDisplay({ category: { label: 'Ventas' } }, { field: 'category' })).toBe('Ventas')
  })
})

describe('fieldKind', () => {
  it('detecta el tipo por namedType', () => {
    expect(fieldKind(schema, 'createdAt')).toBe('date')
    expect(fieldKind(schema, 'amount')).toBe('number')
    expect(fieldKind(schema, 'active')).toBe('boolean')
    expect(fieldKind(schema, 'name')).toBe('text')
  })

  it('detecta relaciones por delante de scalars', () => {
    expect(fieldKind(schema, 'category')).toBe('relation')
  })

  it('cae a text para campos desconocidos', () => {
    expect(fieldKind(schema, 'nope')).toBe('text')
  })
})

describe('resolveFilterArgs', () => {
  it('matchea el arg exacto por nombre de campo', () => {
    const match = resolveFilterArgs(schema, 'name')
    expect(match).toEqual({ single: 'name', after: null, before: null })
    expect(noServerFilter(match)).toBe(false)
  })

  it('mapea fechas a _after/_before', () => {
    expect(resolveFilterArgs(schema, 'createdAt')).toEqual({
      single: null,
      after: 'createdAt_after',
      before: 'createdAt_before',
    })
  })

  it('devuelve sin match para campos sin args', () => {
    const match = resolveFilterArgs(schema, 'amount')
    expect(match).toEqual({ single: null, after: null, before: null })
    expect(noServerFilter(match)).toBe(true)
  })

  it('cae a {campo}_contains cuando existe', () => {
    const withContains: EntitySchema = {
      ...schema,
      filterArgs: [arg('icon'), arg('nombre_contains')],
    }
    expect(resolveFilterArgs(withContains, 'nombre')).toEqual({
      single: 'nombre_contains',
      after: null,
      before: null,
    })
  })
})

describe('rangeToIso', () => {
  it('normaliza [Date, Date] a after/before ISO', () => {
    const range = rangeToIso([new Date('2026-01-05T10:00:00Z'), new Date('2026-01-31T10:00:00Z')])
    expect(range.after).toBe('2026-01-05')
    expect(range.before).toBe('2026-01-31')
  })

  it('normaliza strings ISO', () => {
    expect(rangeToIso(['2026-01-01T00:00:00Z', '2026-02-01T00:00:00Z'])).toEqual({
      after: '2026-01-01',
      before: '2026-02-01',
    })
  })

  it('soporta { start, end } y descarta inválidos', () => {
    expect(rangeToIso({ start: '2026-03-01', end: new Date('2026-03-15T00:00:00Z') })).toEqual({
      after: '2026-03-01',
      before: '2026-03-15',
    })
    expect(rangeToIso({ start: new Date('invalid') })).toEqual({})
    expect(rangeToIso([])).toEqual({})
    expect(rangeToIso(null)).toEqual({})
  })
})

describe('isEmptyFilterValue', () => {
  it('considera vacío undefined/null/string vacía/array vacío', () => {
    expect(isEmptyFilterValue(undefined)).toBe(true)
    expect(isEmptyFilterValue(null)).toBe(true)
    expect(isEmptyFilterValue('')).toBe(true)
    expect(isEmptyFilterValue([])).toBe(true)
  })

  it('considera lleno 0, false y strings', () => {
    expect(isEmptyFilterValue(0)).toBe(false)
    expect(isEmptyFilterValue(false)).toBe(false)
    expect(isEmptyFilterValue('0')).toBe(false)
    expect(isEmptyFilterValue(['a'])).toBe(false)
  })
})
