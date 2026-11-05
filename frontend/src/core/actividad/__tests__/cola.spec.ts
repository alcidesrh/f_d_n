import { describe, expect, it } from 'vitest'
import { crearCola, crearSeguimientoDePantalla, MAXIMO_POR_LOTE } from '../cola'

const evento = (n: number) => ({ accion: `a${n}`, pantalla: '/venta', fecha: n })

describe('actividad: cola de acciones de pantalla', () => {
  it('saca por lotes, de lo más viejo a lo más nuevo', () => {
    const cola = crearCola()
    for (let i = 0; i < MAXIMO_POR_LOTE + 5; i++) cola.anotar(evento(i))
    const lote = cola.tomar()
    expect(lote).toHaveLength(MAXIMO_POR_LOTE)
    expect(lote[0]!.accion).toBe('a0')
    expect(cola.tamano).toBe(5)
  })

  it('lo que no se pudo mandar vuelve al frente', () => {
    const cola = crearCola()
    cola.anotar(evento(1))
    const lote = cola.tomar()
    cola.anotar(evento(2))
    cola.devolver(lote)
    expect(cola.tomar().map((e) => e.accion)).toEqual(['a1', 'a2'])
  })

  it('sin red no crece sin fin: descarta lo más viejo', () => {
    const cola = crearCola(3)
    for (let i = 0; i < 5; i++) cola.anotar(evento(i))
    expect(cola.tomar().map((e) => e.accion)).toEqual(['a2', 'a3', 'a4'])
  })

  it('acota el nombre de la acción', () => {
    const cola = crearCola()
    cola.anotar({ ...evento(1), accion: 'x'.repeat(100) })
    expect(cola.tomar()[0]!.accion).toHaveLength(60)
  })
})

describe('actividad: tiempo en cada pantalla', () => {
  it('al navegar anota de dónde viene y cuánto estuvo ahí', () => {
    const p = crearSeguimientoDePantalla()
    expect(p.navegar('/venta', 'Venta', 1000)).toEqual({ accion: 'navegar', pantalla: '/venta', fecha: 1000, detalle: { titulo: 'Venta' } })
    expect(p.navegar('/venta', 'Venta', 2000)).toBeNull()
    expect(p.navegar('/boletos', undefined, 61_000)).toEqual({ accion: 'navegar', pantalla: '/boletos', fecha: 61_000, duracionMs: 60_000, detalle: { desde: '/venta' } })
  })
})
