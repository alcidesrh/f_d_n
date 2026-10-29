import { describe, expect, it } from 'vitest'
import { PATRONES, barras, simbolos, svg } from '../code128'

describe('code128', () => {
  it('cada patrón ocupa 11 módulos y la parada 13', () => {
    expect(PATRONES).toHaveLength(107)
    PATRONES.forEach((p, i) => {
      const suma = [...p].reduce((a, c) => a + Number(c), 0)
      expect(suma).toBe(i === 106 ? 13 : 11)
    })
  })

  it('igual que el backend: dígitos en C y texto en B', () => {
    expect(simbolos('1234')).toEqual([105, 12, 34, 82, 106])
    expect(simbolos('A')).toEqual([104, 33, 34, 106])
  })

  it('ancho total = margen + símbolos + margen', () => {
    // inicio + 4 datos + checksum = 6 símbolos de 11, parada 13, márgenes 20
    expect(barras('00000042').modulos).toBe(6 * 11 + 13 + 20)
    expect(svg('00000042')).toContain('<svg')
  })
})
