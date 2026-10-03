import { describe, expect, it } from 'vitest'
import { posicionEn, proximaParada, rumbo } from '../posicion'
import type { BusEnRecorrido, ParadaPlan } from '../types'

const T0 = 1_000_000

const parada = (nombre: string, lat: number, lng: number, km: number, llegada: number, salida: number): ParadaPlan => ({ nombre, lat, lng, km, llegada, salida, origen: 'gps' })

/** A (0 km) → B (100 km, 1h40 después, 10 min de espera) → C (200 km). */
function bus(extra: Partial<BusEnRecorrido> = {}): BusEnRecorrido {
  const paradas = [parada('A', 14, -90, 0, T0, T0), parada('B', 15, -90, 100, T0 + 6000, T0 + 6600), parada('C', 16, -90, 200, T0 + 12600, T0 + 12600)]
  return {
    salidaId: 1,
    marcadaIniciada: true,
    estadoSistema: 'iniciada',
    empresaId: 1,
    empresa: 'E',
    bus: '1',
    placa: null,
    piloto: null,
    ruta: 'R',
    rutaNombre: 'R',
    origen: 'A',
    destino: 'C',
    partida: T0,
    llegadaEstimada: T0 + 12600,
    kilometros: 200,
    progreso: 0,
    proxima: null,
    posicion: { lat: 14, lng: -90, rumbo: 0, velocidadKmh: 0, km: 0, estado: 'por_salir', instante: T0, fuente: 'simulada' },
    paradas,
    trazado: [],
    estaciones: [],
    ...extra,
  }
}

describe('posicionEn', () => {
  it('antes de salir está en el origen', () => {
    const p = posicionEn(bus(), T0 - 600)
    expect(p).toMatchObject({ lat: 14, lng: -90, estado: 'por_salir', km: 0, velocidadKmh: 0, progreso: 0 })
  })

  it('interpola linealmente por tramo con la velocidad media del cronograma', () => {
    const p = posicionEn(bus(), T0 + 3000)
    expect(p.estado).toBe('en_ruta')
    expect(p.lat).toBeCloseTo(14.5, 6)
    expect(p.km).toBeCloseTo(50, 6)
    expect(p.progreso).toBeCloseTo(0.25, 6)
    expect(p.velocidadKmh).toBeCloseTo(60, 6)
    expect(p.rumbo).toBeCloseTo(0, 6)
  })

  it('espera detenido en la estación intermedia', () => {
    const p = posicionEn(bus(), T0 + 6300)
    expect(p).toMatchObject({ lat: 15, estado: 'detenido', velocidadKmh: 0, km: 100 })
  })

  it('después de llegar se queda en el destino', () => {
    const p = posicionEn(bus(), T0 + 99999)
    expect(p).toMatchObject({ lat: 16, estado: 'llego', progreso: 1, km: 200 })
  })

  it('con GPS real (sin cronograma) usa la lectura del servidor', () => {
    const b = bus({ paradas: [], posicion: { lat: 15.2, lng: -89.9, rumbo: 45, velocidadKmh: 80, km: 120, estado: 'en_ruta', instante: T0, fuente: 'gps' } })
    expect(posicionEn(b, T0 + 500)).toMatchObject({ lat: 15.2, lng: -89.9, rumbo: 45, velocidadKmh: 80, km: 120, progreso: 0.6 })
  })
})

describe('proximaParada', () => {
  it('es la siguiente estación a la que llega', () => {
    expect(proximaParada(bus(), T0 + 3000)?.nombre).toBe('B')
    expect(proximaParada(bus(), T0 + 7000)?.nombre).toBe('C')
    expect(proximaParada(bus(), T0 + 99999)).toBeNull()
  })
})

describe('rumbo', () => {
  it('norte, este y sur', () => {
    expect(rumbo(0, 0, 1, 0)).toBeCloseTo(0, 6)
    expect(rumbo(0, 0, 0, 1)).toBeCloseTo(90, 6)
    expect(rumbo(1, 0, 0, 0)).toBeCloseTo(180, 6)
  })
})
