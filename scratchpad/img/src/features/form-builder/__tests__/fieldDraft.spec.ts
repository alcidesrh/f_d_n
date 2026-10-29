import { describe, expect, it } from 'vitest'
import { draftFromField, emptyDraft, propsFromDraft } from '../fieldDraft'

describe('fieldDraft', () => {
  it('ida y vuelta: props de un campo → borrador → mismas props', () => {
    const props = {
      name: 'estado',
      label: 'Estado',
      validation: 'required',
      options: [
        { label: 'Activo', value: 'a' },
        { label: 'Inactivo', value: 'i' },
      ],
    }
    const draft = draftFromField({ inputType: 'Select', props })
    expect(propsFromDraft(draft)).toEqual({ props })
  })

  it('solo incluye props que aplican al tipo y descarta vacías', () => {
    const draft = {
      ...emptyDraft(),
      inputType: 'InputText' as const,
      name: ' nombre ',
      mask: '99',
      rows: 5,
    }
    expect(propsFromDraft(draft)).toEqual({ props: { name: 'nombre' } })
  })

  it('mezcla las props avanzadas y reporta JSON inválido', () => {
    const base = { ...emptyDraft(), name: 'x' }
    expect(propsFromDraft({ ...base, advancedJson: '{"disabled": true}' }).props).toEqual({
      name: 'x',
      disabled: true,
    })
    const invalid = propsFromDraft({ ...base, advancedJson: '[1]' })
    expect(invalid.props).toBeNull()
    expect(invalid.error).toContain('debe ser un objeto')
  })
})
