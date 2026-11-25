import { describe, expect, it } from 'vitest'
import { anguloDe, arco, cunaEn, cunas, lectura, nivel, OTROS, REGLAS, TODAS, type Porcion } from '../porciones'

const p = (clave: string, valor: number): Porcion => ({ clave, etiqueta: clave, nombre: clave, valor })
const claves = (l: { clave: string }[]) => l.map((x) => x.clave)

describe('nivel', () => {
  it('ordena de mayor a menor y deja fuera lo que no vendió', () => {
    const n = nivel([p('b', 2), p('a', 5), p('c', 0), p('d', 3)])
    expect(claves(n.porciones)).toEqual(['a', 'd', 'b'])
    expect(n.resto).toEqual([])
    expect(n.total).toBe(10)
  })

  it('a igual valor, por etiqueta', () => {
    expect(claves(nivel([p('SEP', 4), p('GUA', 4)]).porciones)).toEqual(['GUA', 'SEP'])
  })

  it('agrupa en "Otros" lo que pasa del máximo o no llega al mínimo', () => {
    const n = nivel([p('a', 40), p('b', 30), p('c', 20), p('d', 5), p('e', 2), p('f', 2), p('g', 1)], { maximo: 6, minimo: 0.03 })
    expect(claves(n.porciones)).toEqual(['a', 'b', 'c', 'd'])
    expect(claves(n.resto)).toEqual(['e', 'f', 'g'])

    const muchas = nivel(Array.from({ length: 10 }, (_, i) => p(`x${i}`, 10)), { maximo: 6, minimo: 0.03 })
    expect(muchas.porciones).toHaveLength(6)
    expect(muchas.resto).toHaveLength(4)
  })

  it('"Otros" nunca agrupa una sola', () => {
    const n = nivel([p('a', 97), p('b', 2), p('c', 1)], { maximo: 6, minimo: 0.03 })
    expect(claves(n.porciones)).toEqual(['a'])
    expect(claves(n.resto)).toEqual(['b', 'c'])
    expect(claves(nivel([p('a', 98), p('b', 2)], { maximo: 6, minimo: 0.03 }).porciones)).toEqual(['a', 'b'])
  })

  it('en orden fijo conserva el orden y los ceros (sin cuña)', () => {
    const n = nivel([p('cobrado', 90), p('anulado', 0), p('cancelado', 1), p('voucher', 9)], { ...TODAS, fijo: true })
    expect(claves(n.porciones)).toEqual(['cobrado', 'anulado', 'cancelado', 'voucher'])
    expect(n.resto).toEqual([])
    expect(claves(cunas(n))).toEqual(['cobrado', 'cancelado', 'voucher'])
  })

  it('en orden fijo también agrupa, sin cambiar el orden', () => {
    const tipos = [p('cobrado', 75), p('anulado', 3), p('reasignado', 1.5), p('cortesia', 2.6), p('cancelado', 0), p('voucher', 17)]
    const n = nivel(tipos, { ...REGLAS, fijo: true })
    expect(claves(n.porciones)).toEqual(['cobrado', 'cancelado', 'voucher'])
    expect(claves(n.resto)).toEqual(['anulado', 'reasignado', 'cortesia'])
    expect(claves(cunas(n))).toEqual(['cobrado', 'voucher', OTROS])
    expect(claves(nivel(n.resto, { ...REGLAS, fijo: true }).porciones)).toEqual(['anulado', 'reasignado', 'cortesia'])
  })

  it('abrir "Otros" es otro nivel con sus propias partes', () => {
    // Octubre: las 3 primeras estaciones hacen 55% y 30 quedan bajo 2%.
    const datos = [24, 19, 13, 6, 3.5, 3.3, 3, 2.6, ...Array.from({ length: 30 }, (_, i) => 2.3 - i * 0.07)].map((v, i) => p(`e${i}`, Math.round(v * 100)))
    const primero = nivel(datos)
    expect(claves(primero.porciones)).toEqual(['e0', 'e1', 'e2', 'e3'])
    expect(primero.resto).toHaveLength(34)
    const segundo = nivel(primero.resto)
    expect(claves(segundo.porciones)).toEqual(['e4', 'e5', 'e6', 'e7'])
    expect(segundo.porciones.every((x) => x.valor / segundo.total >= REGLAS.minimo)).toBe(true)
    expect(segundo.total).toBe(primero.resto.reduce((s, x) => s + x.valor, 0))
  })
})

describe('lectura', () => {
  it('dice cuánto suman los tres primeros y cuántos quedan bajo 2%', () => {
    const datos = [30, 20, 10, ...Array.from({ length: 10 }, () => 3), ...Array.from({ length: 10 }, () => 1)].map((v, i) => p(`e${i}`, v))
    expect(lectura(datos, 'puntos de venta')).toBe('Los 3 primeros puntos de venta suman 60%; 10 de 23 quedan bajo 2% cada uno.')
  })

  it('con pocas no hace falta', () => {
    expect(lectura([p('a', 5), p('b', 3)], 'usuarios')).toBeNull()
  })
})

describe('cunas', () => {
  it('empiezan a las 12, van con el reloj y "Otros" va al final', () => {
    const c = cunas(nivel([p('a', 50), p('b', 25), p('c', 5), p('d', 5), p('e', 5), p('f', 5), p('g', 3), p('h', 2)]))
    expect(c[0]).toMatchObject({ clave: 'a', inicio: 0, parte: 0.5 })
    expect(c[0]!.fin).toBeCloseTo(Math.PI)
    expect(c.at(-1)!.clave).toBe(OTROS)
    expect(c.at(-1)!.fin).toBeCloseTo(2 * Math.PI)
  })
})

describe('arco', () => {
  it('una cuña de toda la vuelta son dos medias', () => {
    expect(arco(0, 2 * Math.PI, 100, 60).match(/M/g)).toHaveLength(2)
    expect(arco(0, Math.PI / 2, 100, 60)).toBe('M0.000,-100.000A100,100 0 0 1 100.000,-0.000L60.000,-0.000A60,60 0 0 0 0.000,-60.000Z')
  })
})

describe('cunaEn', () => {
  const lista = cunas(nivel([p('a', 990), p('b', 5), p('c', 5)], { maximo: 6, minimo: 0 }))

  it('encuentra la cuña por el ángulo', () => {
    expect(cunaEn(lista, 1)!.clave).toBe('a')
    expect(anguloDe(1, 0)).toBeCloseTo(Math.PI / 2)
    expect(anguloDe(0, -1)).toBeCloseTo(0)
  })

  it('una cuña angosta se toca en el ángulo mínimo', () => {
    const b = lista.find((c) => c.clave === 'b')!
    const afuera = b.inicio - 0.05
    expect(cunaEn(lista, afuera)!.clave).toBe('a')
    expect(cunaEn(lista, afuera, 0.2)!.clave).toBe('b')
  })
})
