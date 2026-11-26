import { describe, expect, it } from 'vitest'
import { SECCIONES, seccionDe } from '../secciones'

describe('seccionDe', () => {
  it('abre la sección pedida', () => {
    expect(seccionDe('apariencia')).toBe('apariencia')
  })

  it('sin sección o con una que no existe, la primera', () => {
    expect(seccionDe(undefined)).toBe(SECCIONES[0].id)
    expect(seccionDe('otra')).toBe(SECCIONES[0].id)
    expect(seccionDe(['apariencia'])).toBe(SECCIONES[0].id)
  })
})
