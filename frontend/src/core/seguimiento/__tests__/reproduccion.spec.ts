import { describe, expect, it } from 'vitest'
import { SIN_SENAL_SEGUNDOS, avanzar, distanciaKm, indiceEn, kmAcumulados, partirPorHuecos, posicionEn, recorridoHasta } from '../reproduccion'
import type { PuntoGps } from '../types'

// Hacia el este por el paralelo 14.6: 0.01° de longitud ≈ 1.08 km.
const p = (t: number, lng: number, vel = 60, rumbo = 90): PuntoGps => [t, 14.6, lng, vel, rumbo]
const puntos: PuntoGps[] = [p(100, -90.5), p(110, -90.49), p(120, -90.48), p(120 + SIN_SENAL_SEGUNDOS + 60, -90.3, 30), p(120 + SIN_SENAL_SEGUNDOS + 120, -90.29, 0, 45)]
const km = kmAcumulados(puntos)

describe('distancias', () => {
  it('distanciaKm de un grado de latitud', () => expect(distanciaKm(14, -90, 15, -90)).toBeCloseTo(111.2, 0))
  it('kmAcumulados empieza en 0 y crece', () => {
    expect(km[0]).toBe(0)
    expect(km[2]).toBeCloseTo(2 * distanciaKm(14.6, -90.5, 14.6, -90.49), 6)
  })
})

describe('indiceEn', () => {
  it('antes de la primera', () => expect(indiceEn(puntos, 50)).toBe(-1))
  it('justo en una lectura', () => expect(indiceEn(puntos, 110)).toBe(1))
  it('entre lecturas, la anterior', () => expect(indiceEn(puntos, 115)).toBe(1))
  it('después de la última', () => expect(indiceEn(puntos, 5000)).toBe(4))
  it('sin lecturas', () => expect(indiceEn([], 5)).toBe(-1))
})

describe('posicionEn', () => {
  it('sin lecturas no hay posición', () => expect(posicionEn([], [], 100)).toBeNull())

  it('interpola entre dos lecturas', () => {
    const x = posicionEn(puntos, km, 105)!
    expect(x.lng).toBeCloseTo(-90.495, 6)
    expect(x.km).toBeCloseTo(km[1]! / 2, 6)
    expect(x.rumbo).toBeCloseTo(90, 0)
    expect(x.sinSenal).toBe(false)
    expect(x.indice).toBe(0)
  })

  it('antes de la primera y después de la última se queda en la punta', () => {
    expect(posicionEn(puntos, km, 0)!.lng).toBe(-90.5)
    const fin = posicionEn(puntos, km, 9999)!
    expect(fin.lng).toBe(-90.29)
    expect(fin.rumbo).toBe(45)
  })

  it('en un hueco sin señal espera en la última lectura', () => {
    const x = posicionEn(puntos, km, 200)!
    expect(x.lng).toBe(-90.48)
    expect(x.sinSenal).toBe(true)
    expect(x.velocidadKmh).toBe(0)
  })

  it('quieto, conserva el rumbo del GPS', () => {
    const quietos: PuntoGps[] = [p(0, -90.5, 0, 200), p(10, -90.5, 0, 210)]
    expect(posicionEn(quietos, kmAcumulados(quietos), 5)!.rumbo).toBe(200)
  })
})

describe('recorridoHasta', () => {
  it('lecturas pasadas más la posición', () => {
    const linea = recorridoHasta(puntos, posicionEn(puntos, km, 115)!)
    expect(linea).toHaveLength(3)
    expect(linea[2]![1]).toBeCloseTo(-90.485, 6)
  })
})

describe('partirPorHuecos', () => {
  it('separa los tramos con señal y los huecos', () => {
    const { tramos, huecos } = partirPorHuecos(puntos)
    expect(tramos.map((t) => t.length)).toEqual([3, 2])
    expect(huecos).toEqual([
      [
        [14.6, -90.48],
        [14.6, -90.3],
      ],
    ])
  })
  it('sin lecturas', () => expect(partirPorHuecos([])).toEqual({ tramos: [], huecos: [] }))
})

describe('avanzar', () => {
  it('avanza lo pedido', () => expect(avanzar(puntos, 100, 5)).toBe(105))
  it('salta el hueco sin señal', () => expect(avanzar(puntos, 118, 10)).toBe(puntos[3]![0]))
  it('no pasa de la última lectura', () => expect(avanzar(puntos, puntos[4]![0] - 10, 60)).toBe(puntos[4]![0]))
  it('sin lecturas no se mueve', () => expect(avanzar([], 10, 60)).toBe(10))
})
