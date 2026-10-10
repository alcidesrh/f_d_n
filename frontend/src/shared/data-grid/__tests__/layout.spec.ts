import { describe, expect, it } from 'vitest'
import { dropIndex, gridTemplate, isValidWidth, moveItem, shiftFor, trackFor } from '../layout'
import { splitMatches } from '../highlight'

describe('trackFor', () => {
  it('preset, longitud, fracción y automático', () => {
    expect(trackFor('md')).toEqual({ track: '13rem', min: '13rem', flexible: false })
    expect(trackFor('160px')).toEqual({ track: '160px', min: '160px', flexible: false })
    expect(trackFor('2fr')).toEqual({ track: 'minmax(10rem, 2fr)', min: '10rem', flexible: true })
    expect(trackFor(null)).toEqual({ track: 'minmax(10rem, 1fr)', min: '10rem', flexible: true })
  })

  it('lo que no entiende (o porcentajes) es automático', () => {
    expect(trackFor('ancho').flexible).toBe(true)
    expect(trackFor('50%').flexible).toBe(true)
    expect(isValidWidth('50%')).toBe(false)
    expect(isValidWidth('12rem')).toBe(true)
    expect(isValidWidth('')).toBe(true)
    expect(isValidWidth('lg')).toBe(true)
  })
})

describe('gridTemplate', () => {
  it('suma los mínimos y agrega selección y acciones', () => {
    const t = gridTemplate([{ key: 'a', width: 'sm' }, { key: 'b' }], { selection: '3rem', actions: '6rem' })
    expect(t.columns).toBe('3rem 9rem minmax(10rem, 1fr) 6rem')
    expect(t.minWidth).toBe('calc(3rem + 9rem + 10rem + 6rem)')
  })

  it('sin columnas flexibles, un relleno empuja las acciones a la derecha', () => {
    const t = gridTemplate([{ key: 'a', width: '100px' }], { actions: '6rem' })
    expect(t.columns).toBe('100px minmax(0, 1fr) 6rem')
  })

  it('el ancho en vivo del redimensionado gana', () => {
    expect(gridTemplate([{ key: 'a', width: 'xl' }], { live: { a: 201.6 } }).columns).toBe('202px minmax(0, 1fr)')
  })
})

describe('reordenar', () => {
  it('moveItem mueve sin mutar', () => {
    const list = ['a', 'b', 'c', 'd']
    expect(moveItem(list, 0, 2)).toEqual(['b', 'c', 'a', 'd'])
    expect(moveItem(list, 3, 0)).toEqual(['d', 'a', 'b', 'c'])
    expect(moveItem(list, 0, 9)).toEqual(list)
    expect(list).toEqual(['a', 'b', 'c', 'd'])
  })

  it('dropIndex cuenta los centros que quedaron antes', () => {
    const centers = [50, 150, 250, 350]
    expect(dropIndex(centers, 0, 60)).toBe(0)
    expect(dropIndex(centers, 0, 260)).toBe(2)
    expect(dropIndex(centers, 3, 10)).toBe(0)
  })

  it('shiftFor corre las de en medio', () => {
    expect([0, 1, 2, 3].map((i) => shiftFor(i, 0, 2, 100))).toEqual([0, -100, -100, 0])
    expect([0, 1, 2, 3].map((i) => shiftFor(i, 3, 1, 100))).toEqual([0, 100, 100, 0])
  })
})

describe('splitMatches', () => {
  it('marca todas las coincidencias sin distinguir mayúsculas ni acentos', () => {
    expect(splitMatches('Estación Petén', 'peten')).toEqual([
      { text: 'Estación ', match: false },
      { text: 'Petén', match: true },
    ])
    expect(splitMatches('abcABC', 'b').filter((s) => s.match).map((s) => s.text)).toEqual(['b', 'B'])
  })

  it('sin aguja devuelve el texto entero', () => {
    expect(splitMatches('hola', '')).toEqual([{ text: 'hola', match: false }])
    expect(splitMatches('', 'x')).toEqual([])
    expect(splitMatches('12345', 34)).toEqual([
      { text: '12', match: false },
      { text: '34', match: true },
      { text: '5', match: false },
    ])
  })
})
