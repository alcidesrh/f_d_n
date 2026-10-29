import { describe, expect, it } from 'vitest'
import { FILTRO_VACIO, filtrarBuses, filtroVigente, salidasDisponibles } from '../filtro'
import type { BusEnRecorrido } from '../types'

const b = (salidaId: number, empresaId: number, partida: number) => ({ salidaId, empresaId, partida }) as BusEnRecorrido

const buses = [b(3, 2, 300), b(1, 1, 100), b(2, 2, 200)]

describe('filtrarBuses', () => {
  it('sin filtro devuelve todos', () => expect(filtrarBuses(buses, FILTRO_VACIO)).toHaveLength(3))
  it('por empresa', () => expect(filtrarBuses(buses, { empresaId: 2, salidaId: null }).map((x) => x.salidaId)).toEqual([3, 2]))
  it('por salida', () => expect(filtrarBuses(buses, { empresaId: null, salidaId: 1 }).map((x) => x.salidaId)).toEqual([1]))
  it('empresa y salida a la vez', () => expect(filtrarBuses(buses, { empresaId: 1, salidaId: 2 })).toEqual([]))
})

describe('salidasDisponibles', () => {
  it('ordena por hora y respeta la empresa', () => {
    expect(salidasDisponibles(buses, null).map((x) => x.salidaId)).toEqual([1, 2, 3])
    expect(salidasDisponibles(buses, 2).map((x) => x.salidaId)).toEqual([2, 3])
  })
})

describe('filtroVigente', () => {
  it('conserva lo que sigue existiendo', () => expect(filtroVigente(buses, { empresaId: 2, salidaId: 3 })).toEqual({ empresaId: 2, salidaId: 3 }))
  it('descarta la salida que ya no está', () => expect(filtroVigente(buses, { empresaId: 2, salidaId: 9 })).toEqual({ empresaId: 2, salidaId: null }))
  it('descarta empresa y salida si la empresa desapareció', () => expect(filtroVigente(buses, { empresaId: 7, salidaId: 3 })).toEqual({ empresaId: null, salidaId: null }))
  it('descarta la salida que no es de la empresa elegida', () => expect(filtroVigente(buses, { empresaId: 1, salidaId: 3 })).toEqual({ empresaId: 1, salidaId: null }))
})
