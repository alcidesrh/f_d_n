import { describe, it, expect, vi } from 'vitest'
import { createMutationLink } from '@/lib/apollo/links/mutationLink'

type Operation = {
  operationType: 'query' | 'mutation'
  variables: Record<string, unknown>
  getContext: () => Record<string, unknown>
}

function runLink(input: unknown, context: Record<string, unknown> = {}) {
  const operation: Operation = {
    operationType: 'mutation',
    variables: { input },
    getContext: () => context,
  }
  const forward = vi.fn<(op: Operation) => Operation>((op) => op)
  const link = createMutationLink()
  link.request(operation as never, forward as never)
  return operation
}

describe('createMutationLink', () => {
  it('reduce relaciones objeto a su id', () => {
    const op = runLink({ ruta: { id: 5 } })
    expect(op.variables.input).toEqual({ ruta: 5 })
  })

  it('mapea listas de relaciones a ids y deja el resto intacto', () => {
    const op = runLink({ precios: [{ id: 1 }, { id: 2 }, 7] })
    expect(op.variables.input).toEqual({ precios: [1, 2, 7] })
  })

  it('no toca el input con contexto keepId', () => {
    const input = { ruta: { id: 5 } }
    const op = runLink(input, { keepId: true })
    expect(op.variables.input).toEqual(input)
  })

  it('deja pasar queries sin input sin errores', () => {
    const operation: Operation = {
      operationType: 'query',
      variables: {},
      getContext: () => ({}),
    }
    const forward = vi.fn<(op: Operation) => Operation>((op) => op)
    expect(() => createMutationLink().request(operation as never, forward as never)).not.toThrow()
  })
})