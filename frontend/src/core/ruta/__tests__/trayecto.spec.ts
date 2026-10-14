import { describe, expect, it } from 'vitest'
import { distanciaASegmentoKm, RADIO_TRAYECTO_KM, sobreElTrayecto } from '../trayecto'

type Lugar = { nombre: string; latitud: number | null; longitud: number | null }

// Trayecto de ejemplo: de (14.6, -90.5) a (14.6, -89.5), recto sobre el paralelo 14.6.
const paradas: [number, number][] = [
  [14.6, -90.5],
  [14.6, -89.5],
]

describe('distanciaASegmentoKm', () => {
  it('es 0 sobre el segmento y se proyecta al punto medio', () => {
    expect(distanciaASegmentoKm([14.6, -90.0], [14.6, -90.5], [14.6, -89.5])).toBeCloseTo(0, 6)
  })

  it('mide la perpendicular cuando cae dentro del segmento', () => {
    // 0.1° de latitud ≈ 11.1 km.
    expect(distanciaASegmentoKm([14.7, -90.0], [14.6, -90.5], [14.6, -89.5])).toBeCloseTo(11.1, 0)
  })

  it('usa el extremo más cercano cuando la proyección cae fuera', () => {
    const d = distanciaASegmentoKm([14.6, -89.0], [14.6, -90.5], [14.6, -89.5])
    expect(d).toBeCloseTo(0.5 * Math.cos((14.6 * Math.PI) / 180) * 111.32, 1)
  })

  it('es 0 si el segmento tiene largo cero', () => {
    expect(distanciaASegmentoKm([14.6, -90.0], [14.6, -90.0], [14.6, -90.0])).toBeCloseTo(0, 6)
  })
})

describe('sobreElTrayecto', () => {
  it('muestra solo los enclaves cerca de algún tramo, en su orden', () => {
    const lugares: Lugar[] = [
      { nombre: 'Sobre la ruta', latitud: 14.6, longitud: -90.2 },
      { nombre: 'Lejos', latitud: 15.5, longitud: -90.0 },
      { nombre: 'Otro sobre la ruta', latitud: 14.61, longitud: -89.7 },
    ]
    expect(sobreElTrayecto(lugares, paradas).map((l) => l.nombre)).toEqual(['Sobre la ruta', 'Otro sobre la ruta'])
  })

  it('descarta los enclaves sin coordenadas', () => {
    const lugares: Lugar[] = [
      { nombre: 'Sin GPS', latitud: null, longitud: null },
      { nombre: 'Solo latitud', latitud: 14.6, longitud: null },
    ]
    expect(sobreElTrayecto(lugares, paradas)).toEqual([])
  })

  it('considera cada tramo entre paradas, no la recta origen–destino', () => {
    // Ruta en "L": sube hasta (15.0, -90.5) y luego va al este. El punto (14.8, -90.5) cae sobre el primer tramo.
    const enL: [number, number][] = [
      [14.6, -90.5],
      [15.0, -90.5],
      [15.0, -89.5],
    ]
    const lugares: Lugar[] = [{ nombre: 'En el primer tramo', latitud: 14.8, longitud: -90.5 }]
    expect(sobreElTrayecto(lugares, enL).map((l) => l.nombre)).toEqual(['En el primer tramo'])
    // El centro de la "L" queda dentro de la caja pero lejos de ambos tramos.
    const dentroDeLaCaja: Lugar[] = [{ nombre: 'Dentro de la caja', latitud: 14.8, longitud: -90.0 }]
    expect(sobreElTrayecto(dentroDeLaCaja, enL)).toEqual([])
  })

  it('respeta el radio indicado y por defecto usa RADIO_TRAYECTO_KM', () => {
    // 0.04° de latitud ≈ 4.4 km: dentro del radio por defecto; 0.1° ≈ 11 km: fuera.
    const cerca: Lugar[] = [{ nombre: 'Cerca', latitud: 14.64, longitud: -90.0 }]
    const lejos: Lugar[] = [{ nombre: 'Lejos', latitud: 14.7, longitud: -90.0 }]
    expect(RADIO_TRAYECTO_KM).toBe(5)
    expect(sobreElTrayecto(cerca, paradas)).toHaveLength(1)
    expect(sobreElTrayecto(lejos, paradas)).toHaveLength(0)
    expect(sobreElTrayecto(lejos, paradas, 20)).toHaveLength(1)
  })

  it('con una sola parada ubicada, el trayecto es ese punto', () => {
    const lugares: Lugar[] = [
      { nombre: 'Misma parada', latitud: 14.6, longitud: -90.5 },
      { nombre: 'A 20 km', latitud: 14.6, longitud: -90.3 },
    ]
    expect(sobreElTrayecto(lugares, [[14.6, -90.5]]).map((l) => l.nombre)).toEqual(['Misma parada'])
  })

  it('sin paradas ubicadas no hay ningún enclave en el trayecto', () => {
    const lugares: Lugar[] = [{ nombre: 'Cualquiera', latitud: 14.6, longitud: -90.5 }]
    expect(sobreElTrayecto(lugares, [])).toEqual([])
  })
})
