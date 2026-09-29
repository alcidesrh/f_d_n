import { describe, expect, it } from 'vitest'
import type { AsientoCroquis } from '@/core/croquis/types'
import { desdeISO, diaISO, estadoEnMapa, expiraValida, luhn, marca, restante } from '../modelo'

describe('tarjeta', () => {
  it('luhn y marca', () => {
    expect(luhn('4242 4242 4242 4242')).toBe(true)
    expect(luhn('4242 4242 4242 4241')).toBe(false)
    expect(marca('4242')).toBe('visa')
    expect(marca('5555555555554444')).toBe('mastercard')
    expect(marca('378282246310005')).toBeNull()
  })
  it('vencimiento', () => {
    const hoy = new Date(2026, 8, 29)
    expect(expiraValida('09/26', hoy)).toBe(true)
    expect(expiraValida('08/26', hoy)).toBe(false)
    expect(expiraValida('13/30', hoy)).toBe(false)
  })
})

it('tiempo restante', () => {
  const ahora = Date.parse('2026-09-29T10:00:00Z')
  expect(restante('2026-09-29T10:14:05Z', ahora)).toEqual({ segundos: 845, texto: '14:05' })
  expect(restante('2026-09-29T09:00:00Z', ahora).segundos).toBe(0)
})

it('estado del mapa: propio = elegido; vendido y reservado = ocupado', () => {
  const a = (id: number): AsientoCroquis => ({ tipo: 'asiento', id, numero: id, clase: 'A', planta: 1, fila: 1, columna: 1 })
  const e = estadoEnMapa([
    { asiento: 1, estado: 'propio' },
    { asiento: 2, estado: 'vendido' },
    { asiento: 3, estado: 'reservado' },
  ])
  expect([1, 2, 3, 4].map((id) => e(a(id)))).toEqual(['seleccionado', 'ocupado', 'ocupado', 'disponible'])
})

it('fechas locales', () => {
  expect(diaISO(new Date(2026, 0, 5))).toBe('2026-01-05')
  expect(desdeISO('2026-01-05')?.getDate()).toBe(5)
  expect(desdeISO('x')).toBeNull()
})
