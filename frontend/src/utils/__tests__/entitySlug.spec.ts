import { describe, it, expect } from 'vitest'
import { entitySlug, entityNameFromSlug } from '@/utils/entitySlug'

describe('entitySlug', () => {
  it('convierte PascalCase a kebab-case', () => {
    expect(entitySlug('BoletoAsiento')).toBe('boleto-asiento')
    expect(entitySlug('MenuLayoutAssignment')).toBe('menu-layout-assignment')
    expect(entitySlug('BoletoTarifa')).toBe('boleto-tarifa')
  })

  it('deja intactos los nombres sin mayúsculas internas', () => {
    expect(entitySlug('Ruta')).toBe('ruta')
    expect(entitySlug('Usuario')).toBe('usuario')
  })
})

describe('entityNameFromSlug', () => {
  it('convierte kebab-case a PascalCase', () => {
    expect(entityNameFromSlug('boleto-asiento')).toBe('BoletoAsiento')
    expect(entityNameFromSlug('menu-layout-assignment')).toBe('MenuLayoutAssignment')
  })

  it('acepta guiones bajos y espacios como separadores', () => {
    expect(entityNameFromSlug('boleto_asiento')).toBe('BoletoAsiento')
    expect(entityNameFromSlug('boleto asiento')).toBe('BoletoAsiento')
  })

  it('es idempotente con nombres ya en PascalCase', () => {
    expect(entityNameFromSlug('BoletoAsiento')).toBe('BoletoAsiento')
  })

  it('es inverso de entitySlug', () => {
    for (const name of ['BoletoAsiento', 'MenuLayoutAssignment', 'Ruta', 'BoletoTarifa']) {
      expect(entityNameFromSlug(entitySlug(name))).toBe(name)
    }
  })
})