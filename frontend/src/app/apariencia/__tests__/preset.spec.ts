import { describe, expect, it } from 'vitest'
import { coloresDelTema, conectarParametros, presetCompleto } from '../preset'

describe('presetCompleto', () => {
  it('conserva del base las partes que las modificaciones no tocan', async () => {
    const preset = await presetCompleto({ estilo: 'lara', primario: 'blue', superficie: 'slate', modo: 'light' })
    expect(preset.components.button.root).toBeDefined()
    expect(preset.semantic.colorScheme.light).toBeUndefined()
    expect(preset.semantic.colorScheme.surface[0]).toBe('#ffffff')
  })

  it('el borde de los campos es el de cada estilo, más marcado con contraste alto', async () => {
    const nora = await presetCompleto({ estilo: 'nora', primario: 'blue', superficie: 'slate', modo: 'light' })
    expect(nora.semantic.colorScheme.formField.borderColor).toBe(
      'color-mix(in oklch, {surface.700} calc(var(--apariencia-contraste)*100%), {surface.500})',
    )
  })

  it('queda conectado a los parámetros de apariencia', async () => {
    const preset = await presetCompleto({ estilo: 'aura', primario: 'blue', superficie: 'slate', modo: 'light' })
    expect(preset.primitive.borderRadius.md).toContain('var(--apariencia-esquinas)')
    expect(preset.semantic.transitionDuration).toContain('var(--apariencia-movimiento)')
    expect(preset.components.button.root.iconOnlyWidth).toContain('var(--apariencia-densidad)')
  })
})

describe('coloresDelTema', () => {
  it('usa la misma rampa de superficie en claro y reflejada en oscuro', () => {
    const light = coloresDelTema('blue', 'slate', 'light')
    const dark = coloresDelTema('blue', 'slate', 'dark')
    expect(light.semantic.colorScheme.surface[0]).toBe('#ffffff')
    expect(dark.semantic.colorScheme.surface[0]).toBe('#000000')
    expect(dark.semantic.colorScheme.surface[50]).toBe(light.semantic.colorScheme.surface[950])
    expect(dark.semantic.colorScheme.surface[950]).toBe(light.semantic.colorScheme.surface[50])
    expect(dark.semantic.primary[50]).toBe(light.semantic.primary[950])
  })

  it('los textos siguen al contraste', () => {
    expect(coloresDelTema('blue', 'slate', 'light').semantic.colorScheme.text.color).toContain('--apariencia-contraste')
  })
})

describe('conectarParametros', () => {
  const preset = {
    primitive: { borderRadius: { none: '0', md: '6px', xl: '12px' } },
    semantic: {
      transitionDuration: '0.2s',
      formField: { paddingX: '0.75rem', paddingY: '0.5rem', borderRadius: '{border.radius.md}' },
      overlay: { popover: { shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0,0,0,.1)' } },
    },
    components: {
      button: {
        root: { paddingY: '{form.field.padding.y}', iconOnlyWidth: '2.5rem', sm: { paddingY: '0.375rem', iconOnlyWidth: '2rem' } },
      },
      chip: { root: { borderRadius: '16px' } },
      toggleswitch: { root: { borderRadius: '30px' } },
      tag: { root: { roundedBorderRadius: '2rem' } },
      card: { root: { padding: '1rem 1.25rem', gap: '0.5rem' } },
    },
  }
  const out = conectarParametros(preset)

  it('escala la escala de radios y los radios chicos con «Esquinas»; no los de pastilla', () => {
    expect(out.primitive.borderRadius.none).toBe('0')
    expect(out.primitive.borderRadius.md).toBe('calc(6px * var(--apariencia-esquinas))')
    expect(out.primitive.borderRadius.xl).toBe('calc(12px * var(--apariencia-esquinas))')
    expect(out.semantic.formField.borderRadius, 'una referencia ya sigue a su origen').toBe('{border.radius.md}')
    expect(out.components.chip.root.borderRadius).toBe('16px')
    expect(out.components.toggleswitch.root.borderRadius).toBe('30px')
    expect(out.components.tag.root.roundedBorderRadius).toBe('2rem')
  })

  it('escala los rellenos con «Densidad», cada longitud por separado', () => {
    expect(out.semantic.formField.paddingX).toBe('calc(0.75rem * var(--apariencia-densidad))')
    expect(out.components.card.root.padding).toBe('calc(1rem * var(--apariencia-densidad)) calc(1.25rem * var(--apariencia-densidad))')
    expect(out.components.card.root.gap, 'solo rellenos').toBe('0.5rem')
  })

  it('el botón de solo ícono crece en ancho lo mismo que en alto', () => {
    expect(out.components.button.root.iconOnlyWidth).toBe('calc(2.5rem + 2 * 0.5rem * (var(--apariencia-densidad) - 1))')
    expect(out.components.button.root.sm.iconOnlyWidth).toBe('calc(2rem + 2 * 0.375rem * (var(--apariencia-densidad) - 1))')
  })

  it('escala la opacidad de las sombras y las duraciones', () => {
    expect(out.semantic.overlay.popover.shadow).toBe(
      '0 4px 6px -1px rgb(0 0 0 / calc(0.1 * var(--apariencia-sombras))), 0 2px 4px -2px rgb(0 0 0 / calc(.1 * var(--apariencia-sombras)))',
    )
    expect(out.semantic.transitionDuration).toBe('calc(0.2s * var(--apariencia-movimiento))')
  })

  it('no muta el preset', () => {
    expect(preset.primitive.borderRadius.md).toBe('6px')
    expect(preset.components.button.root.iconOnlyWidth).toBe('2.5rem')
  })
})
