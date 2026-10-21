// @vitest-environment node
// Puro: sin DOM.
import { describe, it, expect, vi } from 'vitest'
import { alDesfasarseElEsquema, avisarDesfase, esErrorDeEsquema } from '@/core/graphql/desfase'

describe('esErrorDeEsquema', () => {
  it.each([
    'Cannot query field "noExiste" on type "Bus".',
    'Unknown argument "filtroInventado" on field "buses" of type "Query".',
    'Unknown type "BusFilter_viejo".',
    'Variable "$i" got invalid value {"campoViejo":1}; Field "campoViejo" is not defined by type "updateBusInput".',
    'Field "createBus" argument "input" of type "createBusInput!" is required, but it was not provided.',
    'Variable "$input" got invalid value {}; Field "codigo" of required type "String!" was not provided.',
    'Variable "$f_empresa" of type "String" used in position expecting type "[String]".',
    'Enum "EstadoSalida" cannot represent value: "vieja"',
  ])('delata un schema viejo: %s', (mensaje) => {
    expect(esErrorDeEsquema(mensaje)).toBe(true)
  })

  it.each(['Access Denied.', 'Item "/api/buses/9" not found.', 'Internal server error'])('no es del schema: %s', (mensaje) => {
    expect(esErrorDeEsquema(mensaje)).toBe(false)
  })
})

describe('avisarDesfase', () => {
  it('pasa solo los mensajes del schema a quien atiende, mientras esté registrado', () => {
    const oyente = vi.fn<(mensajes: string[]) => void>()
    const quitar = alDesfasarseElEsquema(oyente)
    avisarDesfase(['Access Denied.'])
    expect(oyente).not.toHaveBeenCalled()
    avisarDesfase(['Access Denied.', 'Cannot query field "x" on type "Bus".'])
    expect(oyente).toHaveBeenCalledExactlyOnceWith(['Cannot query field "x" on type "Bus".'])

    quitar()
    avisarDesfase(['Cannot query field "x" on type "Bus".'])
    expect(oyente).toHaveBeenCalledOnce()
  })
})
