import { describe, expect, it } from 'vitest'
import { invertPalette, themeColors } from '../theme'

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

describe('themeColors', () => {
  it('uses the same surface ramp in light and mirrored in dark', () => {
    const light = themeColors('lara', 'blue', 'slate', 'light')
    const dark = themeColors('lara', 'blue', 'slate', 'dark')
    expect(light.semantic.colorScheme.surface[0]).toBe('#ffffff')
    expect(dark.semantic.colorScheme.surface[0]).toBe('#000000')
    expect(dark.semantic.colorScheme.surface[50]).toBe(light.semantic.colorScheme.surface[950])
    expect(dark.semantic.colorScheme.surface[950]).toBe(light.semantic.colorScheme.surface[50])
    expect(dark.semantic.primary[50]).toBe(light.semantic.primary[950])
  })
})
