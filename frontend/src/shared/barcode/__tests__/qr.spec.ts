import { describe, expect, it } from 'vitest'
import { codewords, matriz, svg, version } from '../qr'

/** La misma matriz que da `App\\Venta\\Boleto\\CodigoQr` (ver `CodigoQrTest::testMatrizDeReferencia`). */
const REFERENCIA = [
  '#######.#..#####..#######',
  '#.....#.#.####..#.#.....#',
  '#.###.#.##..##..#.#.###.#',
  '#.###.#..#.#....#.#.###.#',
  '#.###.#.#.#.#.###.#.###.#',
  '#.....#..####.#.#.#.....#',
  '#######.#.#.#.#.#.#######',
  '.........#.#.#.#.........',
  '#..#######.#.#####..#.###',
  '.#.##..##.....#....###..#',
  '#.##.##.##..##.########.#',
  '#...#...#.......##....###',
  '..#######..#.####.##....#',
  '#..##....#..#.#.#...#...#',
  '#####.#.#.#...###.###.###',
  '#.###.......####.#.#..#..',
  '#....####.###..########.#',
  '........####....#...##.##',
  '#######.#...###.#.#.##..#',
  '#.....#.##.#.#.##...#...#',
  '#.###.#.####.#.######..##',
  '#.###.#.#...##.####..####',
  '#.###.#....#.#...#..##.##',
  '#.....#..#.###....#.#.###',
  '#######.##.###..#####...#',
]

describe('código QR', () => {
  it('la versión crece con el texto', () => {
    expect(version(14)).toBe(1)
    expect(version(15)).toBe(2)
    expect(version(106)).toBe(6)
    expect(() => version(107)).toThrow()
  })

  it('arma los codewords de la versión 1-M', () => {
    const cw = codewords('A', 1)
    expect(cw).toHaveLength(26)
    expect(cw.slice(0, 6)).toEqual([0x40, 0x14, 0x10, 0xec, 0x11, 0xec])
  })

  it('da la misma matriz que el backend', () => {
    expect(matriz('FDN1.42.abcdefABCDEF').map((f) => f.map((b) => (b ? '#' : '.')).join(''))).toEqual(REFERENCIA)
  })

  it('el SVG lleva cuatro módulos de margen', () => {
    const s = svg('A', 3)
    expect(s.startsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 87 87" width="87" height="87"')).toBe(true)
    expect(s).toContain('<rect x="12" y="12" width="21" height="3"/>')
  })

  it('rechaza lo que no es ASCII', () => {
    expect(() => matriz('ñ')).toThrow()
  })
})
