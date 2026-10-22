import { describe, expect, it } from 'vitest'
import { diaVecino, filasPorHora, nivelOcupacion, reloj, subidaDe } from '@/core/venta/tresDias'
import type { SalidaResumen } from '@/core/venta/types'

const salida = (id: number, iso: string, extra: Partial<SalidaResumen> = {}): SalidaResumen => ({
  id,
  salida: iso,
  salidaEstacion: null,
  estado: 'programada',
  empresa: null,
  bus: null,
  ruta: { id: 1, nombre: 'GUA-FLO', origen: { id: 1, nombre: 'Guatemala', alias: 'GUA' }, destino: { id: 2, nombre: 'Flores', alias: 'FLO' } },
  vendidos: 0,
  capacidad: 40,
  ...extra,
})

describe('tresDias', () => {
  it('sube a la hora de la parada del cliente, si no de la estación, si no del origen', () => {
    const viaje = { sube: { id: 5, nombre: 'Sanarate', hora: '2026-10-22T07:40:00-06:00', aprox: true }, baja: { id: 2, nombre: 'Flores', hora: null, aprox: false }, intermedias: 0, inicio: { id: 1, nombre: 'Guatemala', hora: null, aprox: false }, fin: { id: 2, nombre: 'Flores', hora: null, aprox: false } }
    expect(subidaDe(salida(1, '2026-10-22T06:00:00-06:00', { viaje }))).toBe('2026-10-22T07:40:00-06:00')
    expect(subidaDe(salida(1, '2026-10-22T06:00:00-06:00', { salidaEstacion: '2026-10-22T06:30:00-06:00' }))).toBe('2026-10-22T06:30:00-06:00')
    expect(reloj('2026-10-22T07:05:00-06:00')).toBe('07:05')
  })

  it('agrupa los tres días por hora, solo las horas con salidas y por minuto', () => {
    const filas = filasPorHora([
      [salida(1, '2026-10-21T06:30:00-06:00')],
      [salida(3, '2026-10-22T06:45:00-06:00'), salida(2, '2026-10-22T06:10:00-06:00'), salida(4, '2026-10-22T22:00:00-06:00')],
      [],
    ])
    expect(filas.map((f) => f.hora)).toEqual([6, 22])
    expect(filas[0]!.dias.map((d) => d.map((r) => r.id))).toEqual([[1], [2, 3], []])
    expect(filas[1]!.dias.map((d) => d.map((r) => r.id))).toEqual([[], [4], []])
  })

  it('mueve el día a medianoche y da el nivel de ocupación', () => {
    expect(diaVecino(new Date(2026, 9, 31, 15), 1)).toEqual(new Date(2026, 10, 1))
    expect(nivelOcupacion(salida(1, '', { vendidos: 38 }))).toBe('lleno')
    expect(nivelOcupacion(salida(1, '', { vendidos: 25 }))).toBe('alto')
    expect(nivelOcupacion(salida(1, '', { vendidos: 2 }))).toBeNull()
    expect(nivelOcupacion(salida(1, '', { capacidad: null }))).toBeNull()
  })
})
