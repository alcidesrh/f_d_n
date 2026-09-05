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

/**
 * Resuelve las options de un input pudiendo ser un array o una función getter.
 * Si es función se invoca (registrando sus dependencias reactivas), de modo
 * que los cambios en los refs que la función lee se reflejan al re-evaluarse el
 * `computed` del wrapper — FormKit congela `attrs.options`, por lo que una
 * función estable con closure reactivo es la vía para actualizar options sin
 * remontar el componente.
 */
export function resolveOptions(options: unknown): unknown[] {
  if (typeof options === 'function') {
    return (options as () => unknown)() as unknown[]
  }
  return Array.isArray(options) ? options : []
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
