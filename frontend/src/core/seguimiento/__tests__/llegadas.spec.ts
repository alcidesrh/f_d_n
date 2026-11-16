import { describe, expect, it } from 'vitest'
import { etiquetaAtraso, minutosTexto, puntualidad, siguienteParada, type Llegadas } from '../llegadas'

describe('hora estimada de llegada', () => {
  it('clasifica la puntualidad con cinco minutos de tolerancia', () => {
    expect(puntualidad(null)).toBe('sin_dato')
    expect(puntualidad(5)).toBe('a_tiempo')
    expect(puntualidad(-5)).toBe('a_tiempo')
    expect(puntualidad(6)).toBe('atrasado')
    expect(puntualidad(-6)).toBe('adelantado')
  })

  it('escribe el atraso', () => {
    expect(minutosTexto(45)).toBe('45 min')
    expect(minutosTexto(60)).toBe('1 h')
    expect(minutosTexto(80)).toBe('1 h 20 min')
    expect(etiquetaAtraso(null)).toBe('Según horario')
    expect(etiquetaAtraso(2)).toBe('A tiempo')
    expect(etiquetaAtraso(20)).toBe('20 min de atraso')
    expect(etiquetaAtraso(-75)).toBe('1 h 15 min de adelanto')
  })

  it('la siguiente parada solo se conoce con GPS', () => {
    const paradas = [
      { enclave: 1, nombre: 'A', programada: 1, estimada: null, paso: true },
      { enclave: 2, nombre: 'B', programada: 2, estimada: 3, paso: false },
    ]
    const l: Llegadas = { fuente: 'gps', atrasoMinutos: 0, lectura: 1, paradas }
    expect(siguienteParada(l)?.nombre).toBe('B')
    expect(siguienteParada({ ...l, fuente: 'horario' })).toBeNull()
  })
})
