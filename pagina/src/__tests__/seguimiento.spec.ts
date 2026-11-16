import { describe, expect, it } from 'vitest'
import { consultable, enlaceMapa, extremos, horaMostrada, puntualidad, type TramoSeguimiento } from '@/seguimiento'

const parada = (enclave: number, estimada: number | null, paso = false) => ({ enclave, nombre: `P${enclave}`, programada: 100 * enclave, estimada, paso })
const tramo = (cambios: Partial<TramoSeguimiento> = {}): TramoSeguimiento => ({
  salida: 1,
  estado: 'iniciada',
  bus: 'B1',
  asientos: [3],
  visible: true,
  fuente: 'gps',
  atrasoMinutos: 0,
  lectura: 1,
  posicion: { lat: 15.5, lng: -90.25 },
  paradas: [parada(1, null, true), parada(2, 250)],
  ...cambios,
})

describe('seguimiento del viaje', () => {
  it('se consulta desde tres horas antes hasta treinta después de la salida', () => {
    const salida = '2026-11-20T10:00:00-06:00'
    const t = new Date(salida).getTime()
    expect(consultable(salida, t - 3 * 3600_000 - 1)).toBe(false)
    expect(consultable(salida, t - 3 * 3600_000)).toBe(true)
    expect(consultable(salida, t + 30 * 3600_000)).toBe(true)
    expect(consultable(salida, t + 30 * 3600_000 + 1)).toBe(false)
  })

  it('la puntualidad solo se dice con GPS', () => {
    expect(puntualidad(tramo({ atrasoMinutos: 5 }))).toBe('aTiempo')
    expect(puntualidad(tramo({ atrasoMinutos: 6 }))).toBe('atrasado')
    expect(puntualidad(tramo({ atrasoMinutos: -6 }))).toBe('adelantado')
    expect(puntualidad(tramo({ fuente: 'horario', atrasoMinutos: null }))).toBe('horario')
  })

  it('muestra la hora estimada y, si no hay, la del horario', () => {
    const e = extremos(tramo())!
    expect(horaMostrada(e.sube)).toBe(100)
    expect(horaMostrada(e.baja)).toBe(250)
    expect(extremos(tramo({ paradas: [] }))).toBeNull()
  })

  it('arma el enlace al mapa', () => {
    expect(enlaceMapa({ lat: 15.5, lng: -90.25 })).toBe('https://www.google.com/maps/search/?api=1&query=15.5,-90.25')
  })
})
