import { describe, it, expect } from 'vitest'
import { parseIntrospection } from '@/lib/apollo/parseIntrospection'
import type { IntrospectionRef, IntrospectionSchemaLike } from '@/lib/apollo/parseIntrospection'

const ref = (
  kind: string,
  name: string | null,
  ofType?: IntrospectionRef | null,
): IntrospectionRef => ({
  kind,
  name,
  ofType,
})

const NAMED = (name: string) => ref('NON_NULL', null, ref('NAMED', name))
const NULLABLE = (name: string) => ref('NAMED', name)
const LIST = (name: string) => ref('LIST', null, NAMED(name))
const ID = () => NAMED('ID')
const STRING = () => NULLABLE('String')
const INT = () => NULLABLE('Int')

const query = {
  kind: 'OBJECT',
  name: 'Query',
  fields: [
    { name: 'node', type: NAMED('Node'), args: [{ name: 'id', type: ID() }] },
    { name: 'boleto', type: NAMED('Boleto'), args: [{ name: 'id', type: ID() }] },
    {
      name: 'boletos',
      type: NAMED('BoletoPageConnection'),
      args: [
        { name: 'currentPage', type: INT() },
        { name: 'itemsPerPage', type: INT() },
        { name: 'numero', type: STRING() },
        { name: 'order', type: NULLABLE('BoletoFilter_order') },
      ],
    },
    { name: 'rutas', type: LIST('Ruta'), args: [] },
    {
      name: 'facturas',
      type: NAMED('FacturaCursorConnection'),
      args: [
        { name: 'first', type: INT() },
        { name: 'after', type: STRING() },
      ],
    },
    // No implementa Node: no debería producirse entidad para Action.
    { name: 'actions', type: LIST('Action'), args: [] },
    {
      name: 'collectionAgnostic',
      type: NAMED('Action'),
      args: [{ name: 'resource', type: STRING() }],
    },
  ],
}

const mutation = {
  kind: 'OBJECT',
  name: 'Mutation',
  fields: [
    {
      name: 'createBoleto',
      type: NAMED('createBoletoPayload'),
      args: [{ name: 'input', type: NAMED('createBoletoInput') }],
    },
    {
      name: 'updateBoleto',
      type: NAMED('updateBoletoPayload'),
      args: [{ name: 'input', type: NAMED('updateBoletoInput') }],
    },
    {
      name: 'deleteBoleto',
      type: NAMED('deleteBoletoPayload'),
      args: [{ name: 'input', type: NAMED('deleteBoletoInput') }],
    },
    // Sin la entidad correspondiente: se ignora.
    {
      name: 'createAction',
      type: NAMED('createActionPayload'),
      args: [{ name: 'input', type: NAMED('createActionInput') }],
    },
  ],
}

function schema(): IntrospectionSchemaLike {
  return {
    queryType: { name: 'Query' },
    mutationType: { name: 'Mutation' },
    types: [
      query as IntrospectionSchemaLike['types'][number],
      mutation as IntrospectionSchemaLike['types'][number],
      // Scalar/enum raíz
      { kind: 'SCALAR', name: 'ID', fields: null },
      { kind: 'SCALAR', name: 'String', fields: null },
      { kind: 'SCALAR', name: 'Int', fields: null },
      { kind: 'SCALAR', name: 'Float', fields: null },
      { kind: 'SCALAR', name: 'DateTime', fields: null },
      {
        kind: 'ENUM',
        name: 'Order',
        fields: null,
        enumValues: [{ name: 'ASC' }, { name: 'DESC' }],
      },
      {
        kind: 'ENUM',
        name: 'EstadoRuta',
        fields: null,
        enumValues: [{ name: 'ACTIVA' }, { name: 'INACTIVA' }],
      },
      // Entidades (implementan Node)
      {
        kind: 'OBJECT',
        name: 'Boleto',
        interfaces: [{ name: 'Node' }],
        fields: [
          { name: 'id', type: ID(), args: [] },
          { name: '_id', type: INT(), args: [] },
          { name: 'numero', type: NAMED('String'), args: [] },
          { name: 'total', type: NULLABLE('Float'), args: [] },
          { name: 'createdAt', type: NULLABLE('DateTime'), args: [] },
          { name: 'label', type: STRING(), args: [] },
          { name: 'ruta', type: NAMED('Ruta'), args: [] },
          { name: 'boletas', type: LIST('Boleta'), args: [] },
          {
            name: 'ventas',
            type: NAMED('VentaPageConnection'),
            args: [{ name: 'currentPage', type: INT() }],
          },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'Ruta',
        interfaces: [{ name: 'Node' }],
        fields: [
          { name: 'id', type: ID(), args: [] },
          { name: 'codigo', type: NAMED('String'), args: [] },
          { name: 'estado', type: NULLABLE('EstadoRuta'), args: [] },
          { name: 'label', type: STRING(), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'Boleta',
        interfaces: [{ name: 'Node' }],
        fields: [
          { name: 'id', type: ID(), args: [] },
          { name: 'monto', type: NULLABLE('Float'), args: [] },
          { name: 'label', type: STRING(), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'Factura',
        interfaces: [{ name: 'Node' }],
        fields: [
          { name: 'id', type: ID(), args: [] },
          { name: 'numero', type: NAMED('String'), args: [] },
          { name: 'label', type: STRING(), args: [] },
          { name: 'items', type: LIST('FacturaItem'), args: [] },
        ],
      },
      // Conexiones/payloads/inputs (objetos, no entidades)
      {
        kind: 'OBJECT',
        name: 'BoletoPageConnection',
        fields: [
          { name: 'collection', type: LIST('Boleto'), args: [] },
          { name: 'paginationInfo', type: NAMED('BoletoPaginationInfo'), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'BoletoPaginationInfo',
        fields: [
          { name: 'currentPage', type: INT(), args: [] },
          { name: 'itemsPerPage', type: INT(), args: [] },
          { name: 'lastPage', type: INT(), args: [] },
          { name: 'totalCount', type: INT(), args: [] },
          { name: 'hasNextPage', type: NAMED('Boolean'), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'FacturaCursorConnection',
        fields: [
          { name: 'edges', type: LIST('FacturaEdge'), args: [] },
          { name: 'pageInfo', type: NAMED('PageInfo'), args: [] },
          { name: 'totalCount', type: INT(), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'FacturaEdge',
        fields: [
          { name: 'node', type: NAMED('Factura'), args: [] },
          { name: 'cursor', type: STRING(), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'PageInfo',
        fields: [
          { name: 'startCursor', type: STRING(), args: [] },
          { name: 'endCursor', type: STRING(), args: [] },
          { name: 'hasNextPage', type: NAMED('Boolean'), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'VentaPageConnection',
        fields: [
          { name: 'collection', type: LIST('Venta'), args: [] },
          { name: 'paginationInfo', type: NAMED('BoletoPaginationInfo'), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'Venta',
        interfaces: [{ name: 'Node' }],
        fields: [
          { name: 'id', type: ID(), args: [] },
          { name: 'total', type: NULLABLE('Float'), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'Action',
        fields: [
          { name: 'id', type: ID(), args: [] },
          { name: 'name', type: STRING(), args: [] },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'FacturaItem',
        fields: [{ name: 'codigo', type: STRING(), args: [] }],
      },
      {
        kind: 'INPUT_OBJECT',
        name: 'BoletoFilter_order',
        inputFields: [
          { name: 'numero', type: NULLABLE('Order') },
          { name: 'total', type: NULLABLE('Order') },
        ],
      },
      {
        kind: 'INPUT_OBJECT',
        name: 'createBoletoInput',
        inputFields: [
          { name: 'clientMutationId', type: STRING() },
          { name: 'numero', type: NAMED('String') },
          { name: 'total', type: NULLABLE('Float') },
          { name: 'ruta', type: STRING() },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'createBoletoPayload',
        fields: [
          { name: 'boleto', type: NAMED('Boleto'), args: [] },
          { name: 'clientMutationId', type: STRING(), args: [] },
        ],
      },
      {
        kind: 'INPUT_OBJECT',
        name: 'updateBoletoInput',
        inputFields: [
          { name: 'clientMutationId', type: STRING() },
          { name: 'id', type: ID() },
          { name: 'numero', type: STRING() },
          { name: 'total', type: NULLABLE('Float') },
          { name: 'ruta', type: STRING() },
          { name: 'estado', type: NULLABLE('EstadoRuta') },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'updateBoletoPayload',
        fields: [{ name: 'boleto', type: NAMED('Boleto'), args: [] }],
      },
      {
        kind: 'INPUT_OBJECT',
        name: 'deleteBoletoInput',
        inputFields: [
          { name: 'clientMutationId', type: STRING() },
          { name: 'id', type: ID() },
        ],
      },
      {
        kind: 'OBJECT',
        name: 'deleteBoletoPayload',
        fields: [{ name: 'boleto', type: NAMED('Boleto'), args: [] }],
      },
      {
        kind: 'INPUT_OBJECT',
        name: 'createActionInput',
        inputFields: [{ name: 'clientMutationId', type: STRING() }],
      },
      {
        kind: 'OBJECT',
        name: 'createActionPayload',
        fields: [{ name: 'action', type: NAMED('Action'), args: [] }],
      },
    ],
  }
}

describe('parseIntrospection', () => {
  it('detecta entidades solo por interface Node', () => {
    const result = parseIntrospection(schema())
    expect(Object.keys(result).sort()).toEqual(['Boleta', 'Boleto', 'Factura', 'Ruta', 'Venta'])
  })

  it('mapea query item y colección page-connection con paginación y orden', () => {
    const boleto = parseIntrospection(schema()).Boleto!
    expect(boleto.queryItem).toBe('boleto')
    expect(boleto.itemArgs).toEqual([
      { name: 'id', type: 'ID!', namedType: 'ID', required: true, isList: false },
    ])
    expect(boleto.queryCollection).toBe('boletos')
    expect(boleto.collectionKind).toBe('page-connection')
    expect(boleto.collectionType).toBe('BoletoPageConnection')
    expect(boleto.paginationType).toBe('BoletoPaginationInfo')
    expect(boleto.orderInput).toBe('BoletoFilter_order')
    expect(boleto.orderFields).toEqual(['numero', 'total'])
    // paginación/orden quedan fuera de los filtros
    expect(boleto.filterArgs.map((a) => a.name)).toEqual(['numero'])
  })

  it('clasifica campos en scalars, relaciones y subcolecciones', () => {
    const boleto = parseIntrospection(schema()).Boleto!
    expect(boleto.scalarFields).toEqual(['id', '_id', 'numero', 'total', 'createdAt', 'label'])
    expect(boleto.relations.map((r) => r.name)).toEqual(['ruta', 'boletas'])
    expect(boleto.subcollections.map((s) => s.name)).toEqual(['ventas'])
  })

  it('detecta conexión por cursor', () => {
    const factura = parseIntrospection(schema()).Factura!
    expect(factura.queryCollection).toBe('facturas')
    expect(factura.collectionKind).toBe('cursor-connection')
    expect(factura.collectionType).toBe('FacturaCursorConnection')
    expect(factura.paginationType).toBe('PageInfo')
  })

  it('detecta colección plana (lista de scalars de entidad)', () => {
    const ruta = parseIntrospection(schema()).Ruta!
    expect(ruta.queryCollection).toBe('rutas')
    expect(ruta.collectionKind).toBe('list')
    expect(ruta.queryItem).toBeNull()
  })

  it('parsea mutaciones create/update/delete con sus inputs', () => {
    const boleto = parseIntrospection(schema()).Boleto!
    expect(boleto.create).toMatchObject({
      kind: 'create',
      field: 'createBoleto',
      inputType: 'createBoletoInput',
      payloadType: 'createBoletoPayload',
      returnsField: 'boleto',
    })
    expect(boleto.create?.inputFields.map((f) => f.name)).toEqual([
      'clientMutationId',
      'numero',
      'total',
      'ruta',
    ])
    expect(boleto.update?.inputFields.map((f) => f.name)).toContain('id')
    expect(boleto.delete?.returnsField).toBe('boleto')
  })

  it('no crea entidades para queries de tipos sin Node', () => {
    const result = parseIntrospection(schema())
    expect(result.Action).toBeUndefined()
  })

  it('captura enumValues en campos de entidad y de inputs de mutación', () => {
    const result = parseIntrospection(schema())
    const ruta = result.Ruta!
    expect(ruta.fields.find((f) => f.name === 'estado')).toMatchObject({
      namedType: 'EstadoRuta',
      kind: 'ENUM',
      enumValues: ['ACTIVA', 'INACTIVA'],
    })
    expect(ruta.scalarFields).toContain('estado')
    expect(result.Boleto?.update?.inputFields.find((f) => f.name === 'estado')?.enumValues).toEqual(
      ['ACTIVA', 'INACTIVA'],
    )
  })

  it('resuelve relaciones del input de mutación desde los campos de la entidad', () => {
    const boleto = parseIntrospection(schema()).Boleto!
    // API Platform tipa las relaciones del input como IRI (String/[String]);
    // el parser debe cruzarlas con los campos de la entidad para marcarlas.
    for (const mutation of [boleto.create!, boleto.update!]) {
      const ruta = mutation.inputFields.find((f) => f.name === 'ruta')
      expect(ruta).toMatchObject({
        type: 'String',
        namedType: 'Ruta',
        kind: 'OBJECT',
        isRelation: true,
        isList: false,
        enumValues: [],
      })
    }
    const numero = boleto.create?.inputFields.find((f) => f.name === 'numero')
    expect(numero).toMatchObject({ namedType: 'String', kind: 'SCALAR', isRelation: false })
    const clientMutationId = boleto.create?.inputFields.find((f) => f.name === 'clientMutationId')
    expect(clientMutationId?.isRelation).toBe(false)
  })
})
