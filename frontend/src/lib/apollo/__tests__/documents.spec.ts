import { describe, it, expect } from 'vitest'
import {
  buildCollectionQuery,
  buildItemQuery,
  buildMutation,
  buildSelection,
  toMutationInput,
} from '@/lib/apollo/documents'
import type { EntitySchema } from '@/lib/apollo/types'

const boletoSchema: EntitySchema = {
  name: 'Boleto',
  queryItem: 'boleto',
  queryCollection: 'boletos',
  collectionKind: 'page-connection',
  collectionType: 'BoletoPageConnection',
  paginationType: 'BoletoPaginationInfo',
  orderInput: 'BoletoFilter_order',
  orderFields: ['numero', 'total'],
  itemArgs: [{ name: 'id', type: 'ID!', namedType: 'ID', required: true, isList: false }],
  collectionArgs: [
    { name: 'currentPage', type: 'Int', namedType: 'Int', required: false, isList: false },
    { name: 'itemsPerPage', type: 'Int', namedType: 'Int', required: false, isList: false },
    { name: 'numero', type: 'String', namedType: 'String', required: false, isList: false },
    {
      name: 'order',
      type: '[BoletoFilter_order]',
      namedType: 'BoletoFilter_order',
      required: false,
      isList: true,
    },
  ],
  filterArgs: [
    { name: 'numero', type: 'String', namedType: 'String', required: false, isList: false },
  ],
  fields: [
    {
      name: 'id',
      type: 'ID!',
      namedType: 'ID',
      kind: 'SCALAR',
      required: true,
      isList: false,
      isRelation: false,
      isSubcollection: false,
      enumValues: [],
    },
    {
      name: 'numero',
      type: 'String!',
      namedType: 'String',
      kind: 'SCALAR',
      required: true,
      isList: false,
      isRelation: false,
      isSubcollection: false,
      enumValues: [],
    },
    {
      name: 'total',
      type: 'Float',
      namedType: 'Float',
      kind: 'SCALAR',
      required: false,
      isList: false,
      isRelation: false,
      isSubcollection: false,
      enumValues: [],
    },
    {
      name: 'label',
      type: 'String',
      namedType: 'String',
      kind: 'SCALAR',
      required: false,
      isList: false,
      isRelation: false,
      isSubcollection: false,
      enumValues: [],
    },
    {
      name: 'ruta',
      type: 'Ruta',
      namedType: 'Ruta',
      kind: 'OBJECT',
      required: false,
      isList: false,
      isRelation: true,
      isSubcollection: false,
      enumValues: [],
    },
    {
      name: 'boletas',
      type: '[Boleta]',
      namedType: 'Boleta',
      kind: 'OBJECT',
      required: false,
      isList: true,
      isRelation: true,
      isSubcollection: false,
      enumValues: [],
    },
    {
      name: 'ventas',
      type: 'VentaPageConnection',
      namedType: 'VentaPageConnection',
      kind: 'OBJECT',
      required: false,
      isList: false,
      isRelation: false,
      isSubcollection: true,
      enumValues: [],
    },
  ],
  scalarFields: ['id', 'numero', 'total', 'label'],
  relations: [
    {
      name: 'ruta',
      type: 'Ruta',
      namedType: 'Ruta',
      kind: 'OBJECT',
      required: false,
      isList: false,
      isRelation: true,
      isSubcollection: false,
      enumValues: [],
    },
    {
      name: 'boletas',
      type: '[Boleta]',
      namedType: 'Boleta',
      kind: 'OBJECT',
      required: false,
      isList: true,
      isRelation: true,
      isSubcollection: false,
      enumValues: [],
    },
  ],
  subcollections: [
    {
      name: 'ventas',
      type: 'VentaPageConnection',
      namedType: 'VentaPageConnection',
      kind: 'OBJECT',
      required: false,
      isList: false,
      isRelation: false,
      isSubcollection: true,
      enumValues: [],
    },
  ],
  create: {
    kind: 'create',
    field: 'createBoleto',
    inputType: 'createBoletoInput',
    payloadType: 'createBoletoPayload',
    returnsField: 'boleto',
    inputFields: [
      {
        name: 'clientMutationId',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'numero',
        type: 'String!',
        namedType: 'String',
        kind: 'SCALAR',
        required: true,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'total',
        type: 'Float',
        namedType: 'Float',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'ruta',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
    ],
  },
  update: {
    kind: 'update',
    field: 'updateBoleto',
    inputType: 'updateBoletoInput',
    payloadType: 'updateBoletoPayload',
    returnsField: 'boleto',
    inputFields: [
      {
        name: 'clientMutationId',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'id',
        type: 'ID!',
        namedType: 'ID',
        kind: 'SCALAR',
        required: true,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'numero',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'ruta',
        type: 'Ruta',
        namedType: 'Ruta',
        kind: 'OBJECT',
        required: false,
        isList: false,
        isRelation: true,
        enumValues: [],
      },
    ],
  },
  delete: {
    kind: 'delete',
    field: 'deleteBoleto',
    inputType: 'deleteBoletoInput',
    payloadType: 'deleteBoletoPayload',
    returnsField: 'boleto',
    inputFields: [
      {
        name: 'clientMutationId',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'id',
        type: 'ID!',
        namedType: 'ID',
        kind: 'SCALAR',
        required: true,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
    ],
  },
}

describe('buildSelection', () => {
  it('selecciona scalars y relaciones a un nivel', () => {
    const selection = buildSelection(boletoSchema, { includeRelations: true })
    expect(selection).toContain('numero')
    expect(selection).toContain('ruta {\n  id\n  label\n}')
    expect(selection).not.toContain('ventas')
  })

  it('con fields solo selecciona los pedidos (scalars y relaciones)', () => {
    const selection = buildSelection(boletoSchema, { fields: ['numero', 'ruta'] })
    expect(selection).toContain('numero')
    expect(selection).not.toContain('total')
    expect(selection).not.toContain('boletas')
    expect(selection).toContain('ruta {\n  id\n  label\n}')
    expect(selection.split('\n').filter((line) => line === 'label')).toHaveLength(0)
  })

  it('con fields vacíos cae a id', () => {
    expect(buildSelection(boletoSchema, { fields: [] })).toBe('id')
  })
})

describe('buildItemQuery', () => {
  it('construye la query de item con variable id', () => {
    const { query, variables } = buildItemQuery(boletoSchema)
    expect(query).toContain('query Item($id: ID!)')
    expect(query).toContain('boleto(id: $id)')
    expect(query).toContain('numero')
    expect(variables).toEqual({})
  })

  it('lanza si no hay query item', () => {
    expect(() => buildItemQuery({ ...boletoSchema, queryItem: null })).toThrow(
      /no expone query item/,
    )
  })
})

describe('buildCollectionQuery', () => {
  it('incluye paginación, filtros y orden con sus variables', () => {
    const { query, variables } = buildCollectionQuery(boletoSchema, {
      currentPage: 2,
      itemsPerPage: 25,
      filters: { numero: 'AB' },
      order: [{ numero: 'ASC' }],
    })
    expect(variables).toEqual({
      currentPage: 2,
      itemsPerPage: 25,
      f_numero: 'AB',
      order: [{ numero: 'ASC' }],
    })
    expect(query).toContain(
      '$currentPage: Int, $itemsPerPage: Int, $f_numero: String, $order: [BoletoFilter_order]',
    )
    expect(query).toContain(
      'boletos(currentPage: $currentPage, itemsPerPage: $itemsPerPage, numero: $f_numero, order: $order)',
    )
    expect(query).toContain('collection {')
    expect(query).toContain('paginationInfo {')
  })

  it('omite filtros sin valor', () => {
    const { variables } = buildCollectionQuery(boletoSchema, { filters: { numero: undefined } })
    expect(variables.f_numero).toBeUndefined()
  })

  it('para list no emite args de paginación pero sí filtros y orden', () => {
    const rutaSchema: EntitySchema = {
      ...boletoSchema,
      collectionKind: 'list',
      collectionType: null,
      paginationType: null,
    }
    const { query, variables } = buildCollectionQuery(rutaSchema, {
      currentPage: 2,
      itemsPerPage: 25,
      filters: { numero: 'AB' },
      order: [{ numero: 'ASC' }],
    })
    expect(variables.currentPage).toBeUndefined()
    expect(variables.itemsPerPage).toBeUndefined()
    expect(variables.f_numero).toBe('AB')
    expect(variables.order).toEqual([{ numero: 'ASC' }])
    expect(query).not.toContain('currentPage')
    expect(query).not.toContain('itemsPerPage')
    expect(query).not.toContain('collection {')
    expect(query).not.toContain('paginationInfo {')
    expect(query).toContain('numero: $f_numero')
  })

  it('para cursor-connection selecciona edges/totalCount sin paginación', () => {
    const cursorSchema: EntitySchema = {
      ...boletoSchema,
      collectionKind: 'cursor-connection',
    }
    const { query } = buildCollectionQuery(cursorSchema, { currentPage: 2, itemsPerPage: 25 })
    expect(query).toContain('edges {')
    expect(query).toContain('totalCount')
    expect(query).not.toContain('currentPage')
    expect(query).not.toContain('itemsPerPage')
  })

  it('para single selecciona el objeto directo sin paginación', () => {
    const singleSchema: EntitySchema = {
      ...boletoSchema,
      collectionKind: 'single',
    }
    const { query } = buildCollectionQuery(singleSchema, { currentPage: 2 })
    expect(query).not.toContain('currentPage')
    expect(query).not.toContain('collection {')
  })

  it('lanza si no hay query collection', () => {
    expect(() => buildCollectionQuery({ ...boletoSchema, queryCollection: null })).toThrow(
      /no expone query collection/,
    )
  })
})

describe('buildMutation', () => {
  it('construye la mutación relay con input y selección del retorno', () => {
    const { query } = buildMutation(boletoSchema, boletoSchema.create!)
    expect(query).toContain('mutation Create($input: createBoletoInput!)')
    expect(query).toContain('createBoleto(input: $input)')
    expect(query).toContain('boleto {')
    expect(query).toContain('numero')
  })

  it('selecciona solo id para delete', () => {
    const { query } = buildMutation(boletoSchema, boletoSchema.delete!)
    expect(query).toContain('mutation Delete($input: deleteBoletoInput!)')
    expect(query).toContain('boleto {\n    id\n    }')
  })
})

describe('toMutationInput', () => {
  const entities: Record<string, EntitySchema> = {
    Boleto: boletoSchema,
    Ruta: {
      ...boletoSchema,
      name: 'Ruta',
      queryCollection: 'rutas',
      create: null,
      update: null,
      delete: null,
    },
  }

  it('descarta claves ajenas al input y clientMutationId', () => {
    const input = toMutationInput(
      boletoSchema,
      boletoSchema.create!,
      { numero: 'AB', extra: 'x', clientMutationId: 'c' },
      entities,
    )
    expect(input).toEqual({ numero: 'AB' })
  })

  it('no envía id en create', () => {
    const input = toMutationInput(
      boletoSchema,
      boletoSchema.create!,
      { id: 7, numero: 'AB' },
      entities,
    )
    expect(input.id).toBeUndefined()
  })

  it('resuelve relaciones a IRIs de API Platform', () => {
    const input = toMutationInput(
      boletoSchema,
      boletoSchema.update!,
      { id: 7, ruta: { id: 5 } },
      entities,
    )
    expect(input).toEqual({ id: 7, ruta: '/api/rutas/5' })
  })
})
