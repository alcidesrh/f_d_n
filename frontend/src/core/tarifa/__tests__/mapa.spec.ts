import { describe, expect, it } from 'vitest'
import {
  FILTRO_MAPA_VACIO,
  filtrarTarifas,
  puntoMedio,
  type ParadaMapa,
  type TarifaMapa,
} from '../mapa'

const tarifa = (over: Partial<TarifaMapa> = {}): TarifaMapa => ({
  id: 1,
  nombre: 'Normal',
  clase: 'A',
  empresaId: 10,
  empresa: 'Fuentes',
  busId: 100,
  bus: 'B-1',
  busClaseId: null,
  busClase: 'Oro',
  precio: 100,
  importe: 'Q 100.00',
  horaDesde: null,
  horaHasta: null,
  vigenteDesde: '2026-01-10 08:00',
  usuario: 'ana',
  ...over,
})

describe('filtrarTarifas', () => {
  const lista = [
    tarifa({ id: 1 }),
    tarifa({
      id: 2,
      empresaId: 20,
      clase: 'B',
      busId: 200,
      precio: 250,
      vigenteDesde: '2026-03-01 00:00',
    }),
    tarifa({ id: 3, precio: null, empresaId: null }),
  ]

  it('sin términos deja todas', () => {
    expect(filtrarTarifas(lista, FILTRO_MAPA_VACIO)).toHaveLength(3)
  })

  it('empresa, clase y bus se cumplen a la vez', () => {
    expect(
      filtrarTarifas(lista, { ...FILTRO_MAPA_VACIO, empresaId: 20, clase: 'B', busId: 200 }).map(
        (t) => t.id,
      ),
    ).toEqual([2])
    expect(filtrarTarifas(lista, { ...FILTRO_MAPA_VACIO, clase: 'B', busId: 100 })).toEqual([])
  })

  it('la clase de bus filtra por su id, no por la clase del asiento', () => {
    const conClase = [
      tarifa({ id: 1, busClaseId: 5, clase: 'A' }),
      tarifa({ id: 2, busClaseId: 6, clase: 'A' }),
      tarifa({ id: 3, busClaseId: null }),
    ]
    expect(
      filtrarTarifas(conClase, { ...FILTRO_MAPA_VACIO, busClaseId: 5 }).map((t) => t.id),
    ).toEqual([1])
    expect(filtrarTarifas(conClase, { ...FILTRO_MAPA_VACIO, busClaseId: 5, clase: 'B' })).toEqual(
      [],
    )
  })

  it('el rango de precio excluye las tarifas sin precio', () => {
    expect(
      filtrarTarifas(lista, { ...FILTRO_MAPA_VACIO, precioMin: 200 }).map((t) => t.id),
    ).toEqual([2])
    expect(
      filtrarTarifas(lista, { ...FILTRO_MAPA_VACIO, precioMax: 150 }).map((t) => t.id),
    ).toEqual([1])
  })

  it('el rango de vigencia compara solo el día, inclusive', () => {
    expect(
      filtrarTarifas(lista, { ...FILTRO_MAPA_VACIO, vigenteDesde: '2026-03-01' }).map((t) => t.id),
    ).toEqual([2])
    expect(
      filtrarTarifas(lista, { ...FILTRO_MAPA_VACIO, vigenteHasta: '2026-01-10' }).map((t) => t.id),
    ).toEqual([1, 3])
  })
})

describe('puntoMedio', () => {
  const paradas = (coords: Array<[number, number] | null>): ParadaMapa[] =>
    coords.map((c, i) => ({
      orden: i,
      enclaveId: i,
      nombre: `P${i}`,
      latitud: c ? c[0] : null,
      longitud: c ? c[1] : null,
    }))

  it('en una recta, a la mitad exacta', () => {
    const [lat, lng] = puntoMedio(
      paradas([
        [14, -90],
        [16, -90],
      ]),
      0,
      1,
    )!
    expect(lat).toBeCloseTo(15, 9)
    expect(lng).toBeCloseTo(-90, 9)
  })

  it('reparte por distancia recorrida, no por número de paradas', () => {
    // Tramo corto y luego uno largo: la mitad cae dentro del largo.
    const p = paradas([
      [14, -90],
      [14.1, -90],
      [16, -90],
    ])
    const [lat] = puntoMedio(p, 0, 2)!
    expect(lat).toBeGreaterThan(14.1)
    expect(lat).toBeLessThan(16)
  })

  it('sin ubicación en algún punto del tramo, null', () => {
    expect(puntoMedio(paradas([[14, -90], null, [16, -90]]), 0, 2)).toBeNull()
    expect(
      puntoMedio(
        paradas([
          [14, -90],
          [16, -90],
        ]),
        0,
        0,
      ),
    ).toBeNull()
  })
})
