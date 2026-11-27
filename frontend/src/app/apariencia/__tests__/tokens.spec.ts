import { describe, expect, it } from 'vitest'
import { completarConBase, contrastado, invertirPaleta, invertirRampa, masMarcado, resolverReferencia } from '../tokens'

const ramp = { 50: 'a', 100: 'b', 200: 'c', 300: 'd', 400: 'e', 500: 'f', 600: 'g', 700: 'h', 800: 'i', 900: 'j', 950: 'k' }

describe('invertirRampa / invertirPaleta', () => {
  it('invierte cada rampa (50 ↔ 950; el 500 se queda) y deja lo que no es rampa', () => {
    expect(invertirRampa(ramp)[50]).toBe('k')
    const out = invertirPaleta({ blue: ramp, white: '#fff', corta: { 50: 'a' } })
    expect(out.blue[50]).toBe('k')
    expect(out.blue[500]).toBe('f')
    expect(out.blue[950]).toBe('a')
    expect(out.white).toBe('#fff')
    expect(out.corta).toEqual({ 50: 'a' })
  })
})

describe('completarConBase', () => {
  it('rellena con el base lo que la modificación no trae, y gana la modificación', () => {
    const base = { a: { x: 1, y: 2 }, b: 'base' }
    const mods = { a: { y: 9 }, b: 'mod', c: 3 }
    expect(completarConBase(base, mods)).toEqual({ a: { x: 1, y: 9 }, b: 'mod', c: 3 })
  })

  it('no muta ni el base ni las modificaciones', () => {
    const base = { a: { x: 1 } }
    const mods = { a: { y: 2 } }
    completarConBase(base, mods)
    expect(base).toEqual({ a: { x: 1 } })
    expect(mods).toEqual({ a: { y: 2 } })
  })
})

describe('contrastado', () => {
  it('mezcla el normal con el alto según el parámetro, sin espacios en la cuenta (PrimeVue la envolvería en calc)', () => {
    const valor = contrastado('{surface.500}', '{surface.700}')
    expect(valor).toBe('color-mix(in oklch, {surface.700} calc(var(--apariencia-contraste)*100%), {surface.500})')
    expect(valor).not.toMatch(/\s\*\s/)
  })
})

describe('resolverReferencia', () => {
  const preset = {
    primitive: { borderRadius: { md: '6px' } },
    semantic: { formField: { paddingY: '0.5rem', borderRadius: '{border.radius.md}' }, alias: '{form.field.border.radius}' },
  }

  it('sigue la referencia en semantic y primitive, con claves camelCase como PrimeVue', () => {
    expect(resolverReferencia(preset, '{form.field.padding.y}')).toBe('0.5rem')
    expect(resolverReferencia(preset, '{alias}')).toBe('6px')
  })

  it('devuelve lo que no es referencia y `undefined` si no la encuentra', () => {
    expect(resolverReferencia(preset, '1rem')).toBe('1rem')
    expect(resolverReferencia(preset, '{no.existe}')).toBeUndefined()
  })
})

describe('masMarcado', () => {
  it('avanza en la rampa hasta el 950 y deja lo que no es un paso', () => {
    expect(masMarcado('{surface.300}')).toBe('{surface.500}')
    expect(masMarcado('{surface.900}')).toBe('{surface.950}')
    expect(masMarcado('{primary.color}')).toBe('{primary.color}')
    expect(masMarcado('#ccc')).toBe('#ccc')
  })
})
