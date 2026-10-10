import { describe, expect, it } from 'vitest'
import { invertPalette, invertRamp, themeColors } from '../theme'

const ramp = { 50: 'a', 100: 'b', 200: 'c', 300: 'd', 400: 'e', 500: 'f', 600: 'g', 700: 'h', 800: 'i', 900: 'j', 950: 'k' }

describe('invertRamp', () => {
  it('reverses a single ramp', () => {
    const inverted = invertRamp(ramp)
    expect(inverted[50]).toBe('k')
    expect(inverted[500]).toBe('f')
    expect(inverted[950]).toBe('a')
  })

  it('passes through anything that is not a ramp', () => {
    expect(invertRamp('#fff')).toBe('#fff')
    expect(invertRamp({ 50: 'a' })).toEqual({ 50: 'a' })
  })
})

describe('invertPalette', () => {
  it('reverses each ramp of a collection and keeps plain values', () => {
    const out = invertPalette({ blue: ramp, white: '#fff' })
    expect(out.blue[50]).toBe('k')
    expect(out.white).toBe('#fff')
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
