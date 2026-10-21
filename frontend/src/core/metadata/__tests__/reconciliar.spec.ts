// @vitest-environment node
// Puro: sin DOM.
import { describe, it, expect } from 'vitest'
import { reconciliarEstado } from '@/core/metadata/reconciliar'
import type { CustomListFilterState } from '@/core/entities/listCustomFilter'
import type { PaginationState } from '@/core/entities/types'
import type { EntitySchema, OrderCondition, SchemaArg } from '@/core/graphql/types'

const arg = (name: string, isList = false): SchemaArg => ({
  name,
  type: isList ? '[String]' : 'String',
  namedType: 'String',
  required: false,
  isList,
})

const bus = {
  name: 'Bus',
  filterArgs: [arg('codigo_contains'), arg('piloto'), arg('empresa_list', true), arg('_combinar')],
  orderInput: 'BusFilter_order',
  orderFields: ['codigo', 'createdAt'],
} as unknown as EntitySchema

const estado = () => ({
  filters: {
    codigo_contains: 'A-1',
    pilotoAux: '/api/pilotos/3',
    empresa_list: ['/api/empresas/1'],
    _combinar: 'or',
  } as Record<string, unknown>,
  order: [{ codigo: 'ASC' }, { pilotoAux: 'DESC' }, { codigo: 'ASC', createdAt: 'DESC' }] as OrderCondition[],
  customFilter: null as CustomListFilterState | null,
  pagination: { itemsPerPage: 25, currentPage: 4, totalCount: 100, lastPage: 4, hasNextPage: false } as PaginationState | undefined,
})

describe('reconciliarEstado', () => {
  it('quita los filtros cuyo argumento ya no existe y conserva los demás', () => {
    expect(reconciliarEstado(estado(), bus).filters).toEqual({
      codigo_contains: 'A-1',
      empresa_list: ['/api/empresas/1'],
      _combinar: 'or',
    })
  })

  it('quita el filtro cuyo argumento cambió de forma (lista o valor suelto)', () => {
    const conPilotoEnLista = { ...bus, filterArgs: [arg('piloto', true)] } as EntitySchema
    const anterior = { ...estado(), filters: { piloto: '/api/pilotos/1' } }
    expect(reconciliarEstado(anterior, conPilotoEnLista).filters).toEqual({})
  })

  it('deja solo las condiciones de orden sobre campos que se pueden ordenar', () => {
    expect(reconciliarEstado(estado(), bus).order).toEqual([{ codigo: 'ASC' }, { codigo: 'ASC', createdAt: 'DESC' }])
    expect(reconciliarEstado(estado(), { ...bus, orderInput: null } as EntitySchema).order).toEqual([])
  })

  it('quita el filtro a la medida entero si perdió un argumento', () => {
    const vigente: CustomListFilterState = { values: { codigo: 'A' }, args: { codigo_contains: 'A' }, chips: [] }
    const roto: CustomListFilterState = { values: { aux: 1 }, args: { codigo_contains: 'A', pilotoAux: '/api/pilotos/3' }, chips: [] }
    expect(reconciliarEstado({ ...estado(), customFilter: vigente }, bus).customFilter).toBe(vigente)
    expect(reconciliarEstado({ ...estado(), customFilter: roto }, bus).customFilter).toBeNull()
  })

  it('descarta los datos en caché y vuelve a la primera página', () => {
    const reconciliado = reconciliarEstado(estado(), bus)
    expect(reconciliado).toMatchObject({ items: [], item: null, fullList: [] })
    expect(reconciliado.pagination).toEqual({ itemsPerPage: 25, currentPage: 1, totalCount: 100, lastPage: 4, hasNextPage: false })
    expect('pagination' in reconciliarEstado({ ...estado(), pagination: undefined }, bus)).toBe(false)
  })
})
