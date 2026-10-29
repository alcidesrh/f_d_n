import { describe, expect, it } from 'vitest'
import { estilosPorRuta, posicionEnArco, puntoEnArco, trazadoArqueado } from '../arco'
import { posicionEn } from '../posicion'
import type { BusEnRecorrido } from '../types'

const A = { lat: 14, lng: -90 }
const B = { lat: 16, lng: -90 }

describe('puntoEnArco', () => {
  it('los extremos son los de la recta y k=0 no curva', () => {
    expect(puntoEnArco(A, B, 0, 0.2)).toEqual([14, -90])
    expect(puntoEnArco(A, B, 1, 0.2)).toEqual([16, -90])
    expect(puntoEnArco(A, B, 0.5, 0)).toEqual([15, -90])
  })

  it('el centro se separa k × largo / 2 y los signos opuestos van a lados opuestos', () => {
    const [lat1, lng1] = puntoEnArco(A, B, 0.5, 0.2)
    const [, lng2] = puntoEnArco(A, B, 0.5, -0.2)
    expect(lat1).toBeCloseTo(15, 9)
    expect(Math.abs(lng1 - -90)).toBeCloseTo(0.2 * 2 * 0.5 / Math.cos((15 * Math.PI) / 180), 6)
    expect(Math.sign(lng1 + 90)).toBe(-Math.sign(lng2 + 90))
  })
})

describe('trazadoArqueado', () => {
  it('recto devuelve los mismos puntos; arqueado pasa por todas las estaciones', () => {
    const pts: [number, number][] = [[14, -90], [15, -89], [16, -90]]
    expect(trazadoArqueado(pts, 0)).toBe(pts)
    const arco = trazadoArqueado(pts, 0.14, 8)
    expect(arco).toHaveLength(2 * 8 + 1)
    expect(arco[0]).toEqual([14, -90])
    expect(arco[8]).toEqual([15, -89])
    expect(arco[16]).toEqual([16, -90])
  })
})

describe('estilosPorRuta', () => {
  it('reparte curvaturas distintas y estables por orden alfabético', () => {
    const e = estilosPorRuta(['RUT019', 'RUT203', 'RUT019', 'RUT021'])
    expect(e.get('RUT019')!.k).toBe(0)
    expect(new Set([...e.values()].map((x) => x.k)).size).toBe(3)
    expect(estilosPorRuta(['RUT203', 'RUT021', 'RUT019']).get('RUT203')).toEqual(e.get('RUT203'))
  })
})

describe('posicionEnArco', () => {
  const T0 = 1000
  const bus = {
    paradas: [
      { nombre: 'A', lat: 14, lng: -90, km: 0, llegada: T0, salida: T0, origen: 'gps' },
      { nombre: 'B', lat: 16, lng: -90, km: 100, llegada: T0 + 100, salida: T0 + 100, origen: 'gps' },
    ],
    kilometros: 100,
  } as unknown as BusEnRecorrido

  it('en marcha sigue el mismo arco que la línea', () => {
    const p = posicionEn(bus, T0 + 50)
    expect(posicionEnArco(bus, p, 0.2)).toEqual(puntoEnArco(bus.paradas[0]!, bus.paradas[1]!, 0.5, 0.2))
    expect(posicionEnArco(bus, p, 0)).toEqual([p.lat, p.lng])
  })

  it('en una estación o llegado no se desplaza', () => {
    const p = posicionEn(bus, T0 + 500)
    expect(posicionEnArco(bus, p, 0.2)).toEqual([16, -90])
  })
})
