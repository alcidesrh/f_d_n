import { describe, expect, it } from 'vitest'
import { resolveListFeatures } from '../listFeatures'

const base = { selector: false, canUpdate: true, canDelete: true, readOnly: false }

describe('resolveListFeatures', () => {
  it('en la página todo está disponible', () => {
    const features = resolveListFeatures(base)
    expect(Object.values(features).every(Boolean)).toBe(true)
  })

  it('el selector no edita ni opera, pero siempre selecciona', () => {
    const features = resolveListFeatures({ ...base, selector: true, overrides: { selection: false } })
    expect(features.selection).toBe(true)
    expect(features.inlineEdit).toBe(false)
    expect(features.rowActions).toBe(false)
    expect(features.bulkDelete).toBe(false)
    expect(features.filter).toBe(true)
  })

  it('las opciones de la entidad apagan, y la prop manda sobre ellas', () => {
    expect(resolveListFeatures({ ...base, options: { selectable: false } }).selection).toBe(false)
    expect(resolveListFeatures({ ...base, options: { selectable: false }, overrides: { selection: true } }).selection).toBe(true)
    expect(resolveListFeatures({ ...base, overrides: { toolbar: false } }).toolbar).toBe(false)
  })

  it('lo que la entidad no permite no se enciende ni con la prop', () => {
    const features = resolveListFeatures({ ...base, canUpdate: false, readOnly: true, overrides: { inlineEdit: true, bulkDelete: true } })
    expect(features.inlineEdit).toBe(false)
    expect(features.bulkDelete).toBe(false)
  })
})
