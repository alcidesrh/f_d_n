import { describe, expect, it } from 'vitest'
import { FILTRO_VACIO, filtrarBuses, filtroVigente, salidasDisponibles } from '../filtro'
import type { BusEnRecorrido, FuentePosicion } from '../types'

const b = (salidaId: number, empresaId: number, partida: number, fuente: FuentePosicion = 'simulada') => ({ salidaId, empresaId, partida, posicion: { fuente } }) as BusEnRecorrido

const buses = [b(3, 2, 300), b(1, 1, 100, 'gps'), b(2, 2, 200)]
const TODOS = { ...FILTRO_VACIO, soloGps: false }

describe('filtrarBuses', () => {
  it('por defecto solo los que envían GPS', () => expect(filtrarBuses(buses, FILTRO_VACIO).map((x) => x.salidaId)).toEqual([1]))
  it('con todos, también los simulados', () => expect(filtrarBuses(buses, TODOS)).toHaveLength(3))
  it('por empresa', () => expect(filtrarBuses(buses, { ...TODOS, empresaId: 2 }).map((x) => x.salidaId)).toEqual([3, 2]))
  it('por empresa sin GPS', () => expect(filtrarBuses(buses, { ...FILTRO_VACIO, empresaId: 2 })).toEqual([]))
  it('por salida', () => expect(filtrarBuses(buses, { ...TODOS, salidaId: 1 }).map((x) => x.salidaId)).toEqual([1]))
  it('empresa y salida a la vez', () => expect(filtrarBuses(buses, { ...TODOS, empresaId: 1, salidaId: 2 })).toEqual([]))
})

describe('salidasDisponibles', () => {
  it('ordena por hora y respeta la empresa', () => {
    expect(salidasDisponibles(buses, null).map((x) => x.salidaId)).toEqual([1, 2, 3])
    expect(salidasDisponibles(buses, 2).map((x) => x.salidaId)).toEqual([2, 3])
  })
  it('solo las que envían GPS', () => expect(salidasDisponibles(buses, null, true).map((x) => x.salidaId)).toEqual([1]))
})

describe('filtroVigente', () => {
  it('conserva lo que sigue existiendo', () => expect(filtroVigente(buses, { ...TODOS, empresaId: 2, salidaId: 3 })).toEqual({ empresaId: 2, salidaId: 3, soloGps: false }))
  it('descarta la salida que ya no está', () => expect(filtroVigente(buses, { ...TODOS, empresaId: 2, salidaId: 9 })).toEqual({ empresaId: 2, salidaId: null, soloGps: false }))
  it('descarta empresa y salida si la empresa desapareció', () => expect(filtroVigente(buses, { ...TODOS, empresaId: 7, salidaId: 3 })).toEqual({ empresaId: null, salidaId: null, soloGps: false }))
  it('descarta la salida que no es de la empresa elegida', () => expect(filtroVigente(buses, { ...TODOS, empresaId: 1, salidaId: 3 })).toEqual({ empresaId: 1, salidaId: null, soloGps: false }))
  it('descarta la salida que dejó de enviar GPS', () => expect(filtroVigente(buses, { ...FILTRO_VACIO, salidaId: 3 })).toEqual({ empresaId: null, salidaId: null, soloGps: true }))
})
