// @vitest-environment node
// Puro: sin DOM.
import { describe, it, expect } from 'vitest'
import { diferenciasEsquema, huellaEntidad, huellasDe } from '@/core/metadata/huellas'
import type { EntitySchema } from '@/core/graphql/types'

const bus = {
  name: 'Bus',
  queryCollection: 'buses',
  scalarFields: ['id', 'codigo'],
  filterArgs: [{ name: 'codigo', type: 'String', namedType: 'String', required: false, isList: false }],
} as unknown as EntitySchema

describe('huellaEntidad', () => {
  it('da lo mismo para el mismo contenido aunque cambie el orden de las claves', () => {
    const reordenado = {
      filterArgs: [{ isList: false, required: false, namedType: 'String', type: 'String', name: 'codigo' }],
      scalarFields: ['id', 'codigo'],
      queryCollection: 'buses',
      name: 'Bus',
    } as unknown as EntitySchema
    expect(huellaEntidad(reordenado)).toBe(huellaEntidad(bus))
  })

  it('cambia si cambia un campo o el orden de una lista', () => {
    expect(huellaEntidad({ ...bus, scalarFields: ['id', 'codigo', 'activo'] })).not.toBe(huellaEntidad(bus))
    expect(huellaEntidad({ ...bus, scalarFields: ['codigo', 'id'] })).not.toBe(huellaEntidad(bus))
    expect(huellaEntidad({ ...bus, queryCollection: null })).not.toBe(huellaEntidad(bus))
  })

  it('ignora las claves sin valor (como JSON)', () => {
    expect(huellaEntidad({ ...bus, extra: undefined } as unknown as EntitySchema)).toBe(huellaEntidad(bus))
  })
})

describe('diferenciasEsquema', () => {
  it('separa las entidades nuevas, las que cambiaron y las que ya no están', () => {
    const antes = huellasDe({ Bus: bus, Piloto: { ...bus, name: 'Piloto' }, Viejo: { ...bus, name: 'Viejo' } })
    const despues = huellasDe({
      Bus: bus,
      Piloto: { ...bus, name: 'Piloto', scalarFields: ['id'] },
      Copiloto: { ...bus, name: 'Copiloto' },
    })
    expect(diferenciasEsquema(antes, despues)).toEqual({
      agregadas: ['Copiloto'],
      modificadas: ['Piloto'],
      eliminadas: ['Viejo'],
    })
  })

  it('sin cambios no hay diferencias', () => {
    const huellas = huellasDe({ Bus: bus })
    expect(diferenciasEsquema(huellas, { ...huellas })).toEqual({ agregadas: [], modificadas: [], eliminadas: [] })
  })
})
