import { describe, expect, it } from 'vitest'
import { claveEnBytes } from '../push'

describe('claveEnBytes', () => {
  it('decodifica base64url sin relleno', () => {
    // "-_8" en base64url es 0xfb 0xff en bytes ("+/8=" en base64).
    expect(Array.from(claveEnBytes('-_8'))).toEqual([0xfb, 0xff])
  })

  it('la clave VAPID pública mide 65 bytes y empieza con 0x04 (punto sin comprimir)', () => {
    const clave = claveEnBytes('BO' + 'A'.repeat(85))
    expect(clave.length).toBe(65)
    expect(clave[0]).toBe(0x04)
  })
})
