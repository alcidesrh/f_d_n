/**
 * Ajustes por componente sobre el preset de PrimeVue (los `components` del
 * preset; ver `preset.ts`). Solo lo que el diseño de la app quiere distinto
 * del preset base, en cualquiera de los cuatro estilos.
 *
 * Solo el claro (o sin `colorScheme`): PrimeVue no tiene modo oscuro propio
 * (`darkModeSelector: false`, main.ts), el oscuro sale de invertir las rampas
 * y un bloque `dark` aquí nunca se usaría. Los rellenos, radios, sombras y
 * duraciones literales de aquí también siguen a los parámetros de apariencia
 * (`conectarParametros` en `preset.ts`); un color que deba seguir al
 * contraste va con `contrastado`.
 */
import { contrastado, type Tokens } from './tokens'

/** Borde de los campos de texto y de los select: más marcado que el del resto del contenido. */
const BORDE_DE_CAMPO = contrastado('{surface.400}', '{surface.600}')

export const componentesPreset = (): Tokens => ({
  inputtext: {
    root: { borderColor: BORDE_DE_CAMPO },
  },
  select: {
    root: { borderColor: BORDE_DE_CAMPO },
    overlay: { background: '{surface.0}' },
    optionGroup: {
      fontWeight: 500,
      color: '{surface.600}',
      padding: '0px 5px',
    },
  },
  multiselect: {
    overlay: { background: '{surface.50}' },
  },
  autocomplete: {
    overlay: {
      background: '{overlay.select.background}',
      borderColor: '{overlay.select.border.color}',
      borderRadius: '{overlay.select.border.radius}',
      color: '{overlay.select.color}',
      shadow: '{overlay.select.shadow}',
    },
  },
  datatable: {
    header: {
      cell: {
        padding: '0 10px',
        sm: { padding: '0 10px' },
      },
    },
    colorScheme: {
      row: { background: '{surface.0}' },
    },
  },
  // El de Lara en los cuatro estilos.
  datepicker: {
    root: { transitionDuration: '{transition.duration}' },
    panel: {
      background: '{content.background}',
      borderColor: '{content.border.color}',
      color: '{content.color}',
      borderRadius: '{content.border.radius}',
      shadow: '{overlay.popover.shadow}',
      padding: '{overlay.popover.padding}',
    },
    header: {
      background: '{content.background}',
      borderColor: '{content.border.color}',
      color: '{content.color}',
      padding: '0 0 0.75rem 0',
    },
    title: { gap: '0.5rem', fontWeight: '700' },
    dropdown: {
      width: '2.5rem',
      sm: { width: '2rem' },
      lg: { width: '3rem' },
      borderColor: '{form.field.border.color}',
      hoverBorderColor: '{form.field.border.color}',
      activeBorderColor: '{form.field.border.color}',
      borderRadius: '{form.field.border.radius}',
      focusRing: {
        width: '{form.field.focus.ring.width}',
        style: '{form.field.focus.ring.style}',
        color: '{form.field.focus.ring.color}',
        offset: '{form.field.focus.ring.offset}',
        shadow: '{form.field.focus.ring.shadow}',
      },
    },
    inputIcon: { color: '{form.field.icon.color}' },
    selectMonth: {
      hoverBackground: '{content.hover.background}',
      color: '{content.color}',
      hoverColor: '{content.hover.color}',
      padding: '0.375rem 0.625rem',
      borderRadius: '{content.border.radius}',
    },
    selectYear: {
      hoverBackground: '{content.hover.background}',
      color: '{content.color}',
      hoverColor: '{content.hover.color}',
      padding: '0.375rem 0.625rem',
      borderRadius: '{content.border.radius}',
    },
    group: { borderColor: '{content.border.color}', gap: '{overlay.popover.padding}' },
    dayView: { margin: '0.75rem 0 0 0' },
    weekDay: { padding: '0.375rem', fontWeight: '700', color: '{content.color}' },
    date: {
      hoverBackground: '{content.hover.background}',
      selectedBackground: '{primary.color}',
      rangeSelectedBackground: '{highlight.background}',
      color: '{content.color}',
      hoverColor: '{content.hover.color}',
      selectedColor: '{primary.contrast.color}',
      rangeSelectedColor: '{highlight.color}',
      width: '2.5rem',
      height: '2.5rem',
      borderRadius: '50%',
      padding: '0.375rem',
      focusRing: {
        width: '{form.field.focus.ring.width}',
        style: '{form.field.focus.ring.style}',
        color: '{form.field.focus.ring.color}',
        offset: '{form.field.focus.ring.offset}',
        shadow: '{form.field.focus.ring.shadow}',
      },
    },
    monthView: { margin: '0.75rem 0 0 0' },
    month: { padding: '0.5rem', borderRadius: '{content.border.radius}' },
    yearView: { margin: '0.75rem 0 0 0' },
    year: { padding: '0.5rem', borderRadius: '{content.border.radius}' },
    buttonbar: { padding: '0.75rem 0 0 0', borderColor: '{content.border.color}' },
    timePicker: { padding: '0.75rem 0 0 0', borderColor: '{content.border.color}', gap: '0.5rem', buttonGap: '0.25rem' },
    colorScheme: {
      light: {
        dropdown: {
          background: '{surface.50}',
          hoverBackground: '{surface.100}',
          activeBackground: '{surface.200}',
          color: '{surface.600}',
          hoverColor: '{surface.700}',
          activeColor: '{surface.800}',
        },
        today: { background: '{surface.200}', color: '{surface.900}' },
      },
    },
  },
  popover: {
    root: { background: '{surface.50}', borderColor: '{surface.300}' },
  },
  divider: {
    root: { borderColor: '{surface.200}' },
  },
  dialog: {
    root: { background: '{overlay.modal.background}' },
  },
  progressbar: {
    root: { background: '{surface.300}', height: '5px' },
    value: { background: '{surface.500}' },
    label: { color: '{surface.0}' },
  },
  button: {
    colorScheme: {
      light: {
        outlined: {
          secondary: {
            hoverBackground: '{surface.50}',
            activeBackground: '{surface.100}',
            borderColor: '{surface.400}',
            color: '{surface.600}',
          },
        },
      },
    },
  },
  message: {
    text: { fontSize: '.85rem' },
    colorScheme: {
      light: {
        warn: { background: '{orange.500}', color: '{surface.0}' },
      },
    },
  },
  tabs: {
    colorScheme: {
      light: {
        tab: { activeBackground: '{surface.50}' },
      },
    },
  },
  tag: {
    root: {
      fontSize: '0.875rem',
      fontWeight: '600',
      padding: '0.25rem 0.5rem',
      gap: '0.25rem',
      borderRadius: '{content.border.radius}',
      roundedBorderRadius: '{border.radius.xl}',
    },
    colorScheme: {
      light: {
        success: { background: '{emerald.500}', color: '{surface.0}' },
      },
    },
  },
  toggleswitch: {
    root: { height: '15px' },
    colorScheme: {
      light: {
        handle: { background: '{surface.100}', checkedBackground: '{primary.100}' },
      },
    },
  },
  // Leyenda como rótulo sobre el borde, no como una caja encima de otra caja.
  fieldset: {
    legend: {
      fontWeight: 500,
      borderWidth: '0',
      padding: '0 0.5rem',
    },
    colorScheme: {
      light: {
        legend: { background: 'transparent', hoverBackground: 'transparent' },
      },
    },
  },
})
