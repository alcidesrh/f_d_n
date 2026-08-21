import type { FormKitFrameworkContext } from '@formkit/core'

export interface FormKitInputProps {
  context: FormKitFrameworkContext
}

export interface NormalizedOption {
  label: string
  value: unknown
}

export function toScalarValue(value: unknown): unknown {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const record = value as Record<string, unknown>
    if (record.value !== undefined) return record.value
    if (record.id !== undefined) return record.id
    if (record['@id'] !== undefined) return record['@id']
  }
  return value
}

export function toScalarArray(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(toScalarValue)
  return toScalarValue(value)
}

export function normalizeOptions(options: unknown): NormalizedOption[] {
  if (!Array.isArray(options)) return []
  return options.map((option) => {
    if (option && typeof option === 'object') {
      const record = option as Record<string, unknown>
      const label = String(record.label ?? record.name ?? option)
      const value = toScalarValue(record)
      return { label, value }
    }
    return { label: String(option), value: option }
  })
}

export function useFormKitInput(props: FormKitInputProps) {
  const context = toRef(props, 'context')

  const update = (value: unknown) => {
    props.context.node.input(value)
  }

  const blur = (e?: unknown) => {
    props.context.handlers.blur(e as FocusEvent | undefined)
  }

  const invalid = computed(() => props.context.state.invalid === true)
  const disabled = computed(() => props.context.disabled === true)

  return { context, update, blur, invalid, disabled }
}
