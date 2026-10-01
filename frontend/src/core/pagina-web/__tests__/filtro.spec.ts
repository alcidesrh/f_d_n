import { describe, expect, it } from 'vitest'
import { aQuery, filtroInicial, filtrosActivos, rango, sumaSeleccion } from '../filtro'
import type { CompraWeb } from '../types'

describe('filtro de compras de la página', () => {
  it('por defecto: completadas, recientes primero', () => {
    const q = new URLSearchParams(aQuery(filtroInicial()))
    expect(q.get('estado')).toBe('completado')
    expect(q.get('orden')).toBe('creado')
    expect(q.get('direccion')).toBe('desc')
    expect(q.has('q')).toBe(false)
  })

  it('solo manda lo que tiene valor', () => {
    const f = { ...filtroInicial(), estados: [], empresa: 7, idaVuelta: 'si' as const, montoMinimo: 125.5, texto: '  ana ' }
    const q = new URLSearchParams(aQuery(f))
    expect(q.get('estado')).toBe('')
    expect(q.get('empresa')).toBe('7')
    expect(q.get('idaVuelta')).toBe('si')
    expect(q.get('montoMinimo')).toBe('125.5')
    expect(q.get('q')).toBe('ana')
    expect(q.has('origen')).toBe(false)
    expect(filtrosActivos(f)).toBe(3)
  })

  it('rangos rápidos de fecha', () => {
    const hoy = new Date(2026, 9, 1)
    expect(rango('hoy', hoy)).toEqual({ desde: '2026-10-01', hasta: '2026-10-01' })
    expect(rango('7d', hoy)).toEqual({ desde: '2026-09-25', hasta: '2026-10-01' })
    expect(rango('mesAnterior', hoy)).toEqual({ desde: '2026-09-01', hasta: '2026-09-30' })
    expect(rango('todo', hoy)).toEqual({ desde: null, hasta: null })
  })

  it('suma de la selección', () => {
    const c = (centavos: number, asientos: number[]) =>
      ({ monto: { centavos }, ventas: asientos.map((cantidad) => ({ cantidad })) }) as unknown as CompraWeb
    expect(sumaSeleccion([c(119900, [2, 2]), c(27500, [1])])).toEqual({ compras: 2, asientos: 5, centavos: 147400 })
  })
})
