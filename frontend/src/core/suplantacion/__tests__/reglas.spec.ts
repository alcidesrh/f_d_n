import { describe, expect, it } from 'vitest'
import { minutosRestantes, motivoValido, textoVencimiento } from '../reglas'

describe('minutosRestantes', () => {
  const ahora = Date.parse('2026-11-06T10:00:00Z')
  it('redondea hacia arriba y no baja de cero', () => {
    expect(minutosRestantes('2026-11-06T10:44:10Z', ahora)).toBe(45)
    expect(minutosRestantes('2026-11-06T09:00:00Z', ahora)).toBe(0)
  })
  it('sin fecha o con una inválida, null', () => {
    expect(minutosRestantes(null, ahora)).toBeNull()
    expect(minutosRestantes('mañana', ahora)).toBeNull()
  })
})

describe('textoVencimiento', () => {
  it('en minutos, horas o vencida', () => {
    expect(textoVencimiento(45)).toBe('vence en 45 min')
    expect(textoVencimiento(60)).toBe('vence en 1 h')
    expect(textoVencimiento(65)).toBe('vence en 1 h 5 min')
    expect(textoVencimiento(0)).toBe('vencida')
    expect(textoVencimiento(null)).toBe('')
  })
})

describe('motivoValido', () => {
  it('exige el mínimo solo si la configuración lo pide', () => {
    expect(motivoValido('  ab  ', true)).toBe(false)
    expect(motivoValido('Revisar su caja', true)).toBe(true)
    expect(motivoValido('', false)).toBe(true)
  })
})
