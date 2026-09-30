import { describe, expect, it } from 'vitest'
import type { AsientoCroquis } from '@/core/croquis/types'
import { deOrigen, desdeISO, diaISO, esRetorno3ds, estadoEnMapa, exigeCodigoPostal, expiraValida, luhn, marca, paises, restante } from '../modelo'

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

describe('pago: país y 3-D Secure', () => {
  it('lista los países con Guatemala primero', () => {
    const lista = paises()
    expect(lista[0]).toEqual({ label: 'Guatemala', value: 'GT' })
    expect(lista.some((p) => p.value === 'US')).toBe(true)
  })

  it('exige código postal solo en EE. UU. y Canadá', () => {
    expect(exigeCodigoPostal('US')).toBe(true)
    expect(exigeCodigoPostal('CA')).toBe(true)
    expect(exigeCodigoPostal('GT')).toBe(false)
  })

  it('acepta avisos solo de los orígenes esperados', () => {
    const origenes = ['https://centinelapistag.cardinalcommerce.com']
    expect(deOrigen('https://centinelapistag.cardinalcommerce.com', origenes)).toBe(true)
    expect(deOrigen('https://evil.example', origenes)).toBe(false)
  })

  it('reconoce el aviso de retorno del banco', () => {
    expect(esRetorno3ds({ tipo: 'fdn-3ds', datos: { resultado: 'Y' } })).toBe(true)
    expect(esRetorno3ds({ tipo: 'otro', datos: {} })).toBe(false)
    expect(esRetorno3ds('fdn-3ds')).toBe(false)
  })
})
