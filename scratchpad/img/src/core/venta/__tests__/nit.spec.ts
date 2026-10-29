import { describe, expect, it } from 'vitest'
import { nitValido, normalizarNit } from '../nit'

describe('NIT', () => {
  it('valida el dígito verificador (mismos casos que el backend)', () => {
    expect(nitValido('28119266')).toBe(true)
    expect(nitValido('4397700-6')).toBe(true)
    expect(nitValido('4150686')).toBe(true)
    expect(nitValido('28119267')).toBe(false)
    expect(nitValido('ABC')).toBe(false)
  })

  it('CF y vacío son consumidor final', () => {
    expect(normalizarNit(' c/f ')).toBe('CF')
    expect(normalizarNit('')).toBe('CF')
    expect(nitValido('cf')).toBe(true)
  })
})
