/**
 * Borrador del editor de campos del Form Builder: los valores del formulario
 * del panel ↔ las props del campo FormKit que se guarda en el grid.
 */
import { OPTION_INPUT_TYPES, parseOptionsText, type BuilderField, type BuilderInputType } from './schemaBuilder'

export interface DraftState {
  inputType: BuilderInputType
  name: string
  label: string
  placeholder: string
  help: string
  validation: string
  optionsText: string
  min: number | null
  max: number | null
  rows: number
  mask: string
  dateFormat: string
  toggleMask: boolean
  severity: string
  variant: string
  size: string
  icon: string
  advancedJson: string
}

export function emptyDraft(): DraftState {
  return {
    inputType: 'InputText',
    name: '',
    label: '',
    placeholder: '',
    help: '',
    validation: '',
    optionsText: '',
    min: null,
    max: null,
    rows: 3,
    mask: '',
    dateFormat: 'dd/mm/yy',
    toggleMask: false,
    severity: '',
    variant: '',
    size: '',
    icon: '',
    advancedJson: '',
  }
}

/** Borrador con los valores de un campo existente (para editarlo). */
export function draftFromField(node: Pick<BuilderField, 'inputType' | 'props'>): DraftState {
  const props = node.props
  const options = Array.isArray(props.options)
    ? (props.options as Array<{ label: unknown; value: unknown }>)
    : []
  return Object.assign(emptyDraft(), {
    inputType: node.inputType,
    name: String(props.name ?? ''),
    label: String(props.label ?? ''),
    placeholder: String(props.placeholder ?? ''),
    help: String(props.help ?? ''),
    validation: String(props.validation ?? ''),
    optionsText: options.map((option) => `${option.label} | ${option.value}`).join('\n'),
    min: typeof props.min === 'number' ? props.min : null,
    max: typeof props.max === 'number' ? props.max : null,
    rows: typeof props.rows === 'number' ? props.rows : 3,
    mask: String(props.mask ?? ''),
    dateFormat: String(props.dateFormat ?? 'dd/mm/yy'),
    toggleMask: props.toggleMask === true,
    severity: String(props.severity ?? ''),
    variant: String(props.variant ?? ''),
    size: String(props.size ?? ''),
    icon: String(props.icon ?? ''),
    advancedJson: '',
  })
}

/**
 * Props del campo a partir del borrador: solo las que tienen valor y aplican al
 * tipo de input, más las "props avanzadas" (JSON libre).
 */
export function propsFromDraft(draft: DraftState): { props: Record<string, unknown> | null; error?: string } {
  const props: Record<string, unknown> = {}
  const put = (key: string, value: unknown) => {
    if (typeof value === 'string') {
      if (value.trim() !== '') props[key] = value.trim()
    } else if (value !== null && value !== undefined && value !== false) {
      props[key] = value
    }
  }
  put('name', draft.name)
  put('label', draft.label)
  put('placeholder', draft.placeholder)
  put('help', draft.help)
  if (draft.validation.trim() !== '') props.validation = draft.validation.trim()
  if (OPTION_INPUT_TYPES.includes(draft.inputType) && draft.optionsText.trim() !== '') {
    props.options = parseOptionsText(draft.optionsText)
  }
  if (draft.inputType === 'InputNumber') {
    put('min', draft.min)
    put('max', draft.max)
  }
  if (draft.inputType === 'TextArea') put('rows', draft.rows)
  if (draft.inputType === 'InputMask') put('mask', draft.mask)
  if (draft.inputType === 'DatePicker') put('dateFormat', draft.dateFormat)
  if (draft.inputType === 'Password' && draft.toggleMask) props.toggleMask = true
  if (draft.inputType === 'Button') {
    put('severity', draft.severity)
    put('variant', draft.variant)
    put('size', draft.size)
    put('icon', draft.icon)
  }
  if (draft.advancedJson.trim() !== '') {
    try {
      const extra = JSON.parse(draft.advancedJson)
      if (extra === null || typeof extra !== 'object' || Array.isArray(extra)) {
        throw new Error('debe ser un objeto')
      }
      Object.assign(props, extra)
    } catch (cause) {
      return { props: null, error: `Props avanzadas inválidas: ${cause instanceof Error ? cause.message : String(cause)}` }
    }
  }
  return { props }
}
