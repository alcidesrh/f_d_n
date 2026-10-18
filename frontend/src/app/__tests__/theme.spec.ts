import { describe, expect, it } from 'vitest'
import { completarConBase, invertPalette, presetCompleto, themeColors } from '../theme'

const ramp = { 50: 'a', 100: 'b', 200: 'c', 300: 'd', 400: 'e', 500: 'f', 600: 'g', 700: 'h', 800: 'i', 900: 'j', 950: 'k' }

describe('invertPalette', () => {
  it('reverses each ramp of a collection and keeps plain values', () => {
    const out = invertPalette({ blue: ramp, white: '#fff', corta: { 50: 'a' } })
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

describe('presetCompleto', () => {
  it('conserva del base las partes que las modificaciones no tocan', () => {
    const preset = presetCompleto('lara', 'blue', 'slate', 'light')
    expect(preset.components.button.root).toBeDefined()
    expect(preset.semantic.colorScheme.light).toBeUndefined()
    expect(preset.semantic.colorScheme.surface[0]).toBe('#ffffff')
  })
})

describe('themeColors', () => {
  it('uses the same surface ramp in light and mirrored in dark', () => {
    const light = themeColors('blue', 'slate', 'light')
    const dark = themeColors('blue', 'slate', 'dark')
    expect(light.semantic.colorScheme.surface[0]).toBe('#ffffff')
    expect(dark.semantic.colorScheme.surface[0]).toBe('#000000')
    expect(dark.semantic.colorScheme.surface[50]).toBe(light.semantic.colorScheme.surface[950])
    expect(dark.semantic.colorScheme.surface[950]).toBe(light.semantic.colorScheme.surface[50])
    expect(dark.semantic.primary[50]).toBe(light.semantic.primary[950])
  })
})
