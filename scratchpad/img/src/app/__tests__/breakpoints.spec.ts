import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { BREAKPOINTS, mediaUp } from '../breakpoints'

describe('breakpoints', () => {
  it('arma la media query mobile-first', () => {
    expect(mediaUp('lg')).toBe('(width >= 64rem)')
  })

  it('coincide con los tokens CSS (`--breakpoint-*` en assets/tokens.css)', () => {
    // Vitest es la raíz del frontend (vitest.config.ts).
    const tokens = readFileSync(resolve(process.cwd(), 'src/assets/tokens.css'), 'utf8')
    for (const [name, rem] of Object.entries(BREAKPOINTS))
      expect(tokens).toContain(`--breakpoint-${name}: ${rem}rem;`)
  })
})
