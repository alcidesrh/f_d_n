import { describe, expect, it } from 'vitest'
import { elegirEmpresas } from '../empresas'

describe('elegirEmpresas', () => {
  const opciones = [8, 2, 4, 1]

  it('sin marcar ninguna es "Todas"', () => {
    expect(elegirEmpresas([], opciones)).toEqual([])
  })

  it('las marcadas, sin repetir y en orden', () => {
    expect(elegirEmpresas([4, 1, 4], opciones)).toEqual([1, 4])
  })

  it('una que ya no está entre las opciones se descarta', () => {
    expect(elegirEmpresas([4, 99], opciones)).toEqual([4])
  })

  it('marcarlas todas es lo mismo que "Todas"', () => {
    expect(elegirEmpresas([1, 2, 4, 8], opciones)).toEqual([])
  })
})
