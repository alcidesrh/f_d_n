import { describe, expect, it } from 'vitest'
import { SIN_SENAL_SEGUNDOS, avanzar, distanciaKm, enMarchaAcumulado, indiceEn, kmAcumulados, partirPorHuecos, posicionEn, promediosEn, recorridoHasta } from '../reproduccion'
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

describe('promedios', () => {
  // Un tramo de 0.01° de longitud (~1.08 km).
  const d = distanciaKm(14.6, -90.5, 14.6, -90.49)
  const kmh = (km: number, s: number) => (km / s) * 3600
  // Espera 2 min, 2 tramos de 1 min, 2 min detenido, 1 tramo de 1 min.
  const viaje: PuntoGps[] = [p(0, -90.5, 0), p(120, -90.5, 0), p(180, -90.49, 60), p(240, -90.48, 0), p(360, -90.48, 0), p(420, -90.47, 60)]
  const kmViaje = kmAcumulados(viaje)
  const enMarcha = enMarchaAcumulado(viaje)
  const en = (t: number, pts = viaje) => promediosEn(pts, kmAcumulados(pts), enMarchaAcumulado(pts), t)

  it('acumula solo los tramos en marcha y arranca en la última lectura detenida', () => {
    expect(enMarcha.arranque).toBe(1)
    expect(enMarcha.segundos).toEqual([0, 0, 60, 120, 120, 180])
    expect(enMarcha.km[5]).toBeCloseTo(3 * d, 6)
  })

  it('antes de arrancar no hay promedio', () => expect(promediosEn(viaje, kmViaje, enMarcha, 60)).toEqual({ desdeArranqueKmh: null, enMarchaKmh: null }))

  it('dentro de un tramo interpola', () => {
    const x = en(150)
    expect(x.desdeArranqueKmh).toBeCloseTo(kmh(d / 2, 30), 6)
    expect(x.enMarchaKmh).toBeCloseTo(kmh(d / 2, 30), 6)
  })

  it('detenido, baja el promedio desde que arrancó y no el de en marcha', () => {
    const x = en(300)
    expect(x.desdeArranqueKmh).toBeCloseTo(kmh(2 * d, 180), 6)
    expect(x.enMarchaKmh).toBeCloseTo(kmh(2 * d, 120), 6)
  })

  it('después de la última lectura se queda en el de la última', () => {
    expect(en(9999)).toEqual(en(420))
    expect(en(420).desdeArranqueKmh).toBeCloseTo(kmh(3 * d, 300), 6)
    expect(en(420).enMarchaKmh).toBeCloseTo(kmh(3 * d, 180), 6)
  })

  it('un hueco sin señal cuenta en el promedio desde que arrancó y no en el de en marcha', () => {
    expect(en(200, puntos).desdeArranqueKmh).toBeCloseTo(kmh(km[2]!, 100), 6)
    expect(en(200, puntos).enMarchaKmh).toBeCloseTo(kmh(2 * d, 20), 6)
    const fin = en(puntos[4]![0], puntos)
    expect(fin.desdeArranqueKmh).toBeCloseTo(kmh(km[4]!, puntos[4]![0] - 100), 6)
    expect(fin.enMarchaKmh).toBeCloseTo(kmh(3 * d, 80), 6)
  })

  it('una parada corta, de la lectura detenida a la que arranca, no va en marcha', () => {
    // Llega y se detiene; 100 s después arranca unos 10 m más allá.
    const parada: PuntoGps[] = [p(0, -90.5, 60), p(60, -90.49, 0), p(160, -90.4899, 20), p(220, -90.48, 60)]
    expect(enMarchaAcumulado(parada).segundos).toEqual([0, 60, 60, 120])
    expect(en(220, parada).desdeArranqueKmh!).toBeLessThan(en(220, parada).enMarchaKmh!)
  })

  it('sin velocidad del GPS, se mueve por la distancia', () => expect(en(60, [p(0, -90.5, 0), p(60, -90.49, 0)]).enMarchaKmh).toBeCloseTo(kmh(d, 60), 6))

  it('el ruido del GPS detenido no es arrancar', () => {
    const quieto: PuntoGps[] = [p(0, -90.5, 0), p(120, -90.5003, 0), p(240, -90.5, 0)]
    expect(enMarchaAcumulado(quieto).arranque).toBeNull()
    expect(en(240, quieto)).toEqual({ desdeArranqueKmh: null, enMarchaKmh: null })
  })

  it('sin lecturas', () => expect(en(0, [])).toEqual({ desdeArranqueKmh: null, enMarchaKmh: null }))
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
