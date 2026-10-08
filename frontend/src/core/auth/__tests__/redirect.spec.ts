import { describe, expect, it } from 'vitest'
import { destinoSeguro, loginHacia } from '../redirect'

describe('destinoSeguro', () => {
  it('acepta rutas internas con query y hash', () => {
    expect(destinoSeguro('/lista/bus')).toBe('/lista/bus')
    expect(destinoSeguro('/salidas?fecha=2026-10-08#x')).toBe('/salidas?fecha=2026-10-08#x')
  })

  it('descarta lo que no es una ruta interna', () => {
    for (const malo of [
      'https://malo.com',
      '//malo.com',
      '/\\malo.com',
      'salidas',
      '',
      null,
      undefined,
      42,
    ]) {
      expect(destinoSeguro(malo)).toBeNull()
    }
  })

  it('no vuelve al inicio ni al login (no aportan)', () => {
    expect(destinoSeguro('/')).toBeNull()
    expect(destinoSeguro('/?a=1')).toBeNull()
    expect(destinoSeguro('/login')).toBeNull()
    expect(destinoSeguro('/login?redirect=/x')).toBeNull()
  })

  it('toma el primero si la query viene repetida', () => {
    expect(destinoSeguro(['/chat', '/otra'])).toBe('/chat')
  })
})

describe('loginHacia', () => {
  it('recuerda la ruta pedida', () => {
    expect(loginHacia('/lista/bus?page=2')).toEqual({
      name: 'login',
      query: { redirect: '/lista/bus?page=2' },
    })
  })

  it('sin ruta que recordar va al login a secas', () => {
    expect(loginHacia('/')).toEqual({ name: 'login' })
  })
})
