import { describe, expect, it } from 'vitest'
import { toggleRows } from '../listSelection'

type Row = { id: number; v?: string }
const key = (row: Row) => String(row.id)

describe('toggleRows', () => {
  it('agrega al final sin duplicar y conserva el orden de elección', () => {
    const current = [{ id: 3 }, { id: 1 }]
    expect(toggleRows(current, [{ id: 1 }, { id: 2 }], true, key).map((r) => r.id)).toEqual([3, 1, 2])
  })

  it('quita por clave y deja lo de otras páginas', () => {
    expect(toggleRows([{ id: 3 }, { id: 1 }, { id: 2 }], [{ id: 1 }], false, key).map((r) => r.id)).toEqual([3, 2])
  })

  it('las filas de la página reemplazan a su versión guardada', () => {
    const result = toggleRows([{ id: 1, v: 'vieja' }], [{ id: 1, v: 'nueva' }], true, key)
    expect(result).toEqual([{ id: 1, v: 'nueva' }])
  })
})
