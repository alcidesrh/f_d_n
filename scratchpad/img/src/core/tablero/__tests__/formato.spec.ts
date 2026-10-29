import { describe, expect, it } from 'vitest'
import { cuentaRegresiva, dinero, escala, etiquetaCubo, nivelOcupacion, porciento, rango, variacion } from '../formato'

describe('formato del tablero', () => {
  it('formatea dinero completo y compacto', () => {
    expect(dinero(123450)).toBe('Q 1,234.50')
    expect(dinero(281544150, true)).toBe('Q 2.8 M')
    expect(dinero(84512300, true)).toBe('Q 845 mil')
    expect(dinero(98000, true)).toBe('Q 980')
    expect(dinero(null)).toBe('—')
  })

  it('porciento', () => {
    expect(porciento(0.4065)).toBe('41%')
    expect(porciento(0.4071, 1)).toBe('40.7%')
    expect(porciento(null)).toBe('—')
  })

  it('variación relativa con valoración', () => {
    expect(variacion(120, 100)).toEqual({ texto: '+20%', tendencia: 'sube', valoracion: 'buena' })
    expect(variacion(95, 100)).toEqual({ texto: '−5%', tendencia: 'baja', valoracion: 'mala' })
    expect(variacion(95, 100, { inverso: true })?.valoracion).toBe('buena')
    expect(variacion(100, 0)).toBeNull()
    expect(variacion(100, null)).toBeNull()
    expect(variacion(281544150, 693000)?.texto).toBe('×406')
    expect(variacion(1000, 1001)).toEqual({ texto: '0%', tendencia: 'igual', valoracion: 'neutra' })
  })

  it('las tasas varían en puntos porcentuales', () => {
    expect(variacion(0.03, 0.02, { puntos: true, inverso: true })).toEqual({ texto: '+1 pp', tendencia: 'sube', valoracion: 'mala' })
    expect(variacion(0.4, 0.45, { puntos: true })?.texto).toBe('−5 pp')
    expect(variacion(0.4, 0, { puntos: true })?.texto).toBe('+40 pp')
  })

  it('escala redonda del eje', () => {
    expect(escala(87)).toEqual({ max: 100, ticks: [0, 25, 50, 75, 100] })
    expect(escala(1)).toEqual({ max: 1, ticks: [0, 0.25, 0.5, 0.75, 1] })
    expect(escala(0)).toEqual({ max: 1, ticks: [0, 1] })
    expect(escala(13_000_000).max).toBe(15_000_000)
  })

  it('etiquetas de fecha sin pasar por UTC', () => {
    expect(etiquetaCubo('2026-10-12', false)).toBe('lun 12')
    expect(etiquetaCubo('2026-10-12 14:00', true)).toBe('14 h')
    expect(rango('2026-10-06', '2026-10-12')).toBe('6 – 12 oct')
    expect(rango('2026-09-14', '2026-10-12')).toBe('14 sep – 12 oct')
  })

  it('nivel de ocupación con los umbrales del backend', () => {
    expect(nivelOcupacion(0.9)).toBe('alta')
    expect(nivelOcupacion(0.5)).toBe('media')
    expect(nivelOcupacion(0.2)).toBe('baja')
    expect(nivelOcupacion(null)).toBeNull()
  })

  it('cuenta regresiva', () => {
    const ahora = new Date(2026, 9, 12, 14, 0)
    expect(cuentaRegresiva('2026-10-12T14:25', ahora)).toBe('en 25 min')
    expect(cuentaRegresiva('2026-10-12T13:50', ahora)).toBe('hace 10 min')
    expect(cuentaRegresiva('2026-10-12T16:05', ahora)).toBe('en 2 h 05')
  })
})
