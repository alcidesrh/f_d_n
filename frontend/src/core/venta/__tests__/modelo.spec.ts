import { describe, expect, it } from 'vitest'
import type { AsientoCroquis } from '@/core/croquis/types'
import {
  alternar,
  bajadaPorDefecto,
  depurarSeleccion,
  diaISO,
  estadoEnMapa,
  paradasDeBajada,
  paradasDeSubida,
  subidaPorDefecto,
  trayectoEntre,
} from '../modelo'
import type { AsientoOcupado } from '../types'

const asiento = (id: number): AsientoCroquis => ({
  tipo: 'asiento',
  id,
  numero: id,
  clase: 'A',
  planta: 1,
  fila: 1,
  columna: 1,
})

// Guatemala(1) → Santa Elena(2) → Melchor(3)
const detalle = {
  paradas: [
    { id: 1, nombre: 'Guatemala', direccion: null, posicion: 0, hora: null },
    { id: 2, nombre: 'Santa Elena', direccion: null, posicion: 1, hora: null },
    { id: 3, nombre: 'Melchor', direccion: null, posicion: 2, hora: null },
  ],
  trayectos: [
    { id: 10, origen: 1, destino: 3, completo: true },
    { id: 11, origen: 1, destino: 2, completo: false },
    { id: 12, origen: 2, destino: 3, completo: false },
  ],
}

describe('estadoEnMapa', () => {
  const ocupados: AsientoOcupado[] = [
    { asiento: 1, estado: 'vendido', canal: 'estacion' },
    { asiento: 2, estado: 'vendido', canal: 'web' },
    { asiento: 3, estado: 'vendido', canal: 'agencia' },
    { asiento: 4, estado: 'reservado', canal: null },
    { asiento: 5, estado: 'propio', canal: null },
  ]
  const estado = estadoEnMapa(ocupados, [6])

  it('distingue el canal de lo vendido', () => {
    expect([1, 2, 3].map((id) => estado(asiento(id)))).toEqual([
      'ocupado',
      'ocupado-web',
      'ocupado-agencia',
    ])
  })

  it('reservado, propio, elegido y libre', () => {
    expect([4, 5, 6, 7].map((id) => estado(asiento(id)))).toEqual([
      'reservado',
      'seleccionado',
      'seleccionado',
      'disponible',
    ])
  })
})

describe('selección', () => {
  it('alterna y respeta el máximo', () => {
    expect(alternar([1, 2], 2)).toEqual([1])
    expect(alternar([1], 2)).toEqual([1, 2])
    expect(alternar([1, 2], 3, 2)).toEqual([1, 2])
  })

  it('quita lo que otro ocupó, no lo propio', () => {
    expect(
      depurarSeleccion(
        [1, 2, 3],
        [
          { asiento: 2, estado: 'vendido', canal: 'web' },
          { asiento: 3, estado: 'propio', canal: null },
        ],
      ),
    ).toEqual([1, 3])
  })
})

describe('sube / baja', () => {
  it('subidas: paradas que son origen de algún trayecto', () => {
    expect(paradasDeSubida(detalle).map((p) => p.id)).toEqual([1, 2])
  })

  it('bajadas según la subida', () => {
    expect(paradasDeBajada(detalle, 1).map((p) => p.id)).toEqual([2, 3])
    expect(paradasDeBajada(detalle, 2).map((p) => p.id)).toEqual([3])
    expect(paradasDeBajada(detalle, null)).toEqual([])
  })

  it('trayecto entre paradas', () => {
    expect(trayectoEntre(detalle, 2, 3)).toBe(12)
    expect(trayectoEntre(detalle, 3, 1)).toBeNull()
  })

  it('por defecto sube en la estación de venta y baja al final', () => {
    expect(subidaPorDefecto(detalle, 2)).toBe(2)
    expect(subidaPorDefecto(detalle, 99)).toBe(1)
    expect(bajadaPorDefecto(detalle, 1)).toBe(3)
  })
})

it('diaISO usa la fecha local', () => {
  expect(diaISO(new Date(2026, 8, 7, 23, 30))).toBe('2026-09-07')
})
