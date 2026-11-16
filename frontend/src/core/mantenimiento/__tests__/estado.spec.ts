import { describe, expect, it } from 'vitest'
import { conteo, detalle, filtrar, intervalo, pendientes, type BusEnMantenimiento, type EstadoDePlan } from '../estado'

const plan = (p: Partial<EstadoDePlan>): EstadoDePlan => ({ plan: 1, estado: 'al_dia', faltanKm: null, faltanDias: null, ultimaFecha: '2026-06-01', ultimoKm: 40000, ...p })
const bus = (b: Partial<BusEnMantenimiento>): BusEnMantenimiento => ({ id: 1, codigo: '105', matricula: null, empresaId: 7, odometroKm: 50000, estado: 'al_dia', planes: [], ...b })

describe('mantenimiento de flota', () => {
  it('pone el intervalo en palabras', () => {
    expect(intervalo({ cadaKm: 10000, cadaDias: 180 })).toBe('Cada 10,000 km o 180 días')
    expect(intervalo({ cadaKm: null, cadaDias: 1 })).toBe('Cada 1 día')
    expect(intervalo({ cadaKm: null, cadaDias: null })).toBe('Sin intervalo')
  })

  it('dice cuánto falta o por cuánto se pasó', () => {
    expect(detalle(plan({ estado: 'vencido', faltanKm: -1320, faltanDias: 40 }))).toBe('vencido hace 1,320 km')
    expect(detalle(plan({ estado: 'vencido', faltanKm: -320, faltanDias: -1 }))).toBe('vencido hace 320 km y 1 día')
    expect(detalle(plan({ estado: 'vencido', faltanKm: 0 }))).toBe('vence hoy')
    expect(detalle(plan({ estado: 'por_vencer', faltanKm: 800, faltanDias: 12 }))).toBe('le faltan 800 km o 12 días')
    expect(detalle(plan({ estado: 'sin_registro' }))).toBe('nunca se registró')
  })

  it('cuenta los buses por estado', () => {
    expect(conteo([bus({ estado: 'vencido' }), bus({ estado: 'al_dia' }), bus({ estado: 'vencido' })])).toEqual({ vencido: 2, por_vencer: 0, sin_registro: 0, al_dia: 1 })
  })

  it('filtra por estado y texto, primero lo que más urge', () => {
    const buses = [bus({ id: 1, codigo: '105' }), bus({ id: 2, codigo: '20', estado: 'vencido', matricula: 'C-123BBB' }), bus({ id: 3, codigo: '9', estado: 'vencido' }), bus({ id: 4, codigo: '300', estado: 'por_vencer' })]
    expect(filtrar(buses, { estado: null, texto: '' }).map((b) => b.id)).toEqual([3, 2, 4, 1])
    expect(filtrar(buses, { estado: 'vencido', texto: '' }).map((b) => b.id)).toEqual([3, 2])
    expect(filtrar(buses, { estado: null, texto: ' 123bbb ' }).map((b) => b.id)).toEqual([2])
    expect(filtrar(buses, { estado: 'al_dia', texto: '20' })).toEqual([])
  })

  it('lo pendiente de un bus lleva primero lo vencido', () => {
    const b = bus({ planes: [plan({ plan: 1 }), plan({ plan: 2, estado: 'por_vencer' }), plan({ plan: 3, estado: 'vencido' }), plan({ plan: 4, estado: 'sin_registro' })] })
    expect(pendientes(b).map((p) => p.plan)).toEqual([3, 2])
  })
})
