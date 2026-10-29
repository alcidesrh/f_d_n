import { describe, it, expect } from 'vitest'
import { describirCambios, hayCambios, notaLimpia, puedePublicar, type CambiosMetadata } from '@/core/metadata/publicacion'

const sinCambios: CambiosMetadata = { esquema: false, agregadas: [], modificadas: [], eliminadas: [] }

describe('publicación de la metadata', () => {
  it('sin cambios solo se publica para avisar o recargar todo', () => {
    expect(hayCambios(sinCambios)).toBe(false)
    expect(puedePublicar(sinCambios, { aviso: false, completa: false })).toBe(false)
    expect(puedePublicar(sinCambios, { aviso: true, completa: false })).toBe(true)
    expect(puedePublicar(sinCambios, { aviso: false, completa: true })).toBe(true)
    expect(puedePublicar({ ...sinCambios, modificadas: ['Bus'] }, { aviso: false, completa: false })).toBe(true)
    expect(puedePublicar({ ...sinCambios, esquema: true }, { aviso: false, completa: false })).toBe(true)
  })

  it('describe cada tipo de cambio en una línea y abrevia las listas largas', () => {
    const lineas = describirCambios(
      { esquema: true, agregadas: ['A', 'B', 'C', 'D'], modificadas: ['Bus'], eliminadas: ['Viejo'] },
      2,
    )
    expect(lineas.map((l) => l.texto)).toEqual([
      'Schema de la API',
      'Configuración nueva: A, B y 2 más',
      'Configuración modificada: Bus',
      'Sin configuración: Viejo',
    ])
    expect(describirCambios(sinCambios)).toEqual([])
  })

  it('la nota vacía es ninguna', () => {
    expect(notaLimpia('  Copiloto en buses \n')).toBe('Copiloto en buses')
    expect(notaLimpia('   ')).toBeNull()
    expect(notaLimpia(undefined)).toBeNull()
  })
})
