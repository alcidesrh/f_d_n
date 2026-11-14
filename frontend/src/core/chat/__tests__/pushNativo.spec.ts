import { describe, expect, it } from 'vitest'
import { esAppNativa, estadoNativo, rutaDeAviso } from '../pushNativo'

describe('estadoNativo', () => {
  const base = { disponible: true, permiso: 'granted', token: 'abc', mio: true } as const

  it('sin Firebase configurado no se ofrecen', () => {
    expect(estadoNativo({ ...base, disponible: false })).toBe('no-disponible')
  })

  it('el permiso negado en el teléfono manda sobre el registro', () => {
    expect(estadoNativo({ ...base, permiso: 'denied' })).toBe('bloqueadas')
  })

  it('activas solo con permiso y el teléfono registrado a nombre del usuario', () => {
    expect(estadoNativo(base)).toBe('activas')
    expect(estadoNativo({ ...base, mio: false })).toBe('apagadas')
    expect(estadoNativo({ ...base, token: null })).toBe('apagadas')
    expect(estadoNativo({ ...base, permiso: 'prompt' })).toBe('apagadas')
  })
})

describe('rutaDeAviso', () => {
  it('acepta rutas de la app', () => {
    expect(rutaDeAviso({ url: '/chat/7', etiqueta: 'chat-7' })).toBe('/chat/7')
    expect(rutaDeAviso({ url: '/correo/3?x=1' })).toBe('/correo/3?x=1')
  })

  it('rechaza lo que saldría de la app o no es una ruta', () => {
    expect(rutaDeAviso({ url: 'https://otro.sitio/chat' })).toBeNull()
    expect(rutaDeAviso({ url: '//otro.sitio/chat' })).toBeNull()
    expect(rutaDeAviso({ url: '/\\otro.sitio' })).toBeNull()
    expect(rutaDeAviso({ url: 7 })).toBeNull()
    expect(rutaDeAviso({})).toBeNull()
    expect(rutaDeAviso(null)).toBeNull()
  })
})

describe('esAppNativa', () => {
  it('en el navegador no hay puente de Capacitor', () => {
    expect(esAppNativa()).toBe(false)
  })

  it('dentro del shell lo dice el puente', () => {
    const mundo = globalThis as { Capacitor?: unknown }
    mundo.Capacitor = { isNativePlatform: () => true }
    try {
      expect(esAppNativa()).toBe(true)
    } finally {
      delete mundo.Capacitor
    }
  })
})
