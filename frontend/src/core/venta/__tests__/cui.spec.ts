import { describe, expect, it } from 'vitest'
import { cuiValido } from '../cui'

describe('CUI', () => {
  it('valida el dígito verificador (mismos casos que el backend)', () => {
    expect(cuiValido('1234567890101')).toBe(true)
    expect(cuiValido('1234 56789 0101')).toBe(true)
    expect(cuiValido('1234567800101')).toBe(false)
    expect(cuiValido('123456789010')).toBe(false)
    expect(cuiValido('123456789010A')).toBe(false)
  })
})
