import { describe, expect, it } from 'vitest'
import {
  columnasPasillo,
  plantasDe,
  rejillas,
  resumen,
  siguienteNumero,
  sinIds,
  tieneErrores,
  validar,
} from '../model'
import type { ElementoCroquis } from '../types'

const asiento = (
  numero: number,
  fila: number,
  columna: number,
  extra: Partial<ElementoCroquis> = {},
) =>
  ({
    tipo: 'asiento',
    id: numero * 10,
    numero,
    clase: 'A',
    planta: 1,
    fila,
    columna,
    ...extra,
  }) as ElementoCroquis

/** Bus de 2 filas 2 + pasillo + 2 y fila trasera de 5, con chofer y puerta. */
const bus: ElementoCroquis[] = [
  { tipo: 'chofer', id: 1, planta: 1, fila: 1, columna: 1 },
  { tipo: 'puerta', id: 2, planta: 1, fila: 1, columna: 5 },
  asiento(1, 2, 1),
  asiento(2, 2, 2),
  asiento(3, 2, 4),
  asiento(4, 2, 5),
  asiento(5, 3, 1),
  asiento(6, 3, 2),
  asiento(7, 3, 3),
  asiento(8, 3, 4),
  asiento(9, 3, 5),
]

describe('geometría', () => {
  it('plantas y rejilla salen de los elementos, con el marco de 5 columnas como mínimo', () => {
    expect(plantasDe([])).toEqual([1])
    expect(plantasDe([asiento(1, 1, 1, { planta: 2 })])).toEqual([1, 2])
    expect(rejillas(bus)).toEqual({ 1: { filas: 3, columnas: 5 } })
    expect(rejillas(bus, { filas: 10 })[1]).toEqual({ filas: 10, columnas: 5 })
  })

  it('el pasillo es la columna interior sin asientos salvo en la fila trasera', () => {
    expect(columnasPasillo(bus, 1, { filas: 3, columnas: 5 })).toEqual([3])
    expect(columnasPasillo([], 1, { filas: 3, columnas: 5 })).toEqual([])
  })
})

describe('resumen y numeración', () => {
  it('cuenta asientos por clase, puertas y chofer', () => {
    const r = resumen([...bus, asiento(10, 4, 1, { clase: 'B' })])
    expect(r).toEqual({
      asientos: 10,
      porClase: { A: 9, B: 1 },
      plantas: 1,
      puertas: 1,
      chofer: true,
    })
  })

  it('el siguiente número es el menor libre', () => {
    expect(siguienteNumero([])).toBe(1)
    expect(siguienteNumero([asiento(1, 1, 1), asiento(3, 1, 2)])).toBe(2)
  })
})

describe('validar', () => {
  it('un croquis completo no tiene problemas', () => {
    expect(validar(bus)).toEqual([])
  })

  it('celda ocupada dos veces, número repetido y dos choferes son errores', () => {
    const problemas = validar([
      asiento(1, 1, 1),
      asiento(1, 1, 2),
      { tipo: 'puerta', id: null, planta: 1, fila: 1, columna: 1 },
      { tipo: 'chofer', id: null, planta: 1, fila: 2, columna: 1 },
      { tipo: 'chofer', id: null, planta: 2, fila: 2, columna: 1 },
    ])
    expect(tieneErrores(problemas)).toBe(true)
    expect(problemas.filter((p) => p.nivel === 'error').map((p) => p.mensaje)).toEqual([
      'Hay dos elementos en la misma celda.',
      'El asiento 1 está repetido.',
      'El bus solo puede tener un chofer.',
    ])
    expect(problemas[0]!.celdas).toEqual(['1:1:1'])
  })

  it('numeración con huecos, sin chofer o sin puerta son avisos', () => {
    const problemas = validar([asiento(1, 1, 1), asiento(3, 1, 2)])
    expect(tieneErrores(problemas)).toBe(false)
    expect(problemas.map((p) => p.mensaje)).toEqual([
      'La numeración de los asientos no es consecutiva desde 1.',
      'Falta el chofer.',
      'Falta al menos una puerta.',
    ])
  })

  it('coordenadas fuera del marco son errores', () => {
    expect(tieneErrores(validar([asiento(1, 0, 1)]))).toBe(true)
    expect(tieneErrores(validar([asiento(1, 1, 7)]))).toBe(true)
    expect(tieneErrores(validar([asiento(1, 1, 1, { planta: 3 })]))).toBe(true)
  })
})

it('sinIds quita ids y marcas de venta', () => {
  const copia = sinIds([
    asiento(1, 1, 1, { conBoletos: true } as Partial<ElementoCroquis>),
    bus[0]!,
  ])
  expect(copia.every((e) => e.id === null)).toBe(true)
  expect(copia[0]).not.toHaveProperty('conBoletos')
})
