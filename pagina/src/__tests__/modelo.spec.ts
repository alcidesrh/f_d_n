import { describe, expect, it } from 'vitest'
import type { AsientoCroquis } from '@/core/croquis/types'
import {
  alternarAsiento,
  deOrigen,
  desdeISO,
  diaISO,
  duracion,
  esRetorno3ds,
  estadoEnMapa,
  exigeCodigoPostal,
  expiraValida,
  luhn,
  marca,
  paises,
  porcentajeOcupado,
  porDepartamento,
  quetzales,
  restante,
  sumaCentavos,
  sumarDias,
  tomadosPorOtros,
  type AsientoElegido,
} from '../modelo'

describe('tarjeta', () => {
  it('luhn y marca', () => {
    expect(luhn('4242 4242 4242 4242')).toBe(true)
    expect(luhn('4242 4242 4242 4241')).toBe(false)
    expect(marca('4242')).toBe('visa')
    expect(marca('5555555555554444')).toBe('mastercard')
    expect(marca('378282246310005')).toBeNull()
  })
  it('vencimiento', () => {
    const hoy = new Date(2026, 8, 29)
    expect(expiraValida('09/26', hoy)).toBe(true)
    expect(expiraValida('08/26', hoy)).toBe(false)
    expect(expiraValida('13/30', hoy)).toBe(false)
  })
})

it('tiempo restante', () => {
  const ahora = Date.parse('2026-09-29T10:00:00Z')
  expect(restante('2026-09-29T10:14:05Z', ahora)).toEqual({ segundos: 845, texto: '14:05' })
  expect(restante('2026-09-29T09:00:00Z', ahora).segundos).toBe(0)
})

it('estado del mapa: elegido = seleccionado; vendido y reservado por otro = ocupado; lo propio se puede editar', () => {
  const a = (id: number, clase: 'A' | 'B' = 'A'): AsientoCroquis => ({ tipo: 'asiento', id, numero: id, clase, planta: 1, fila: 1, columna: 1 })
  const ocupacion = [
    { asiento: 1, estado: 'propio' as const },
    { asiento: 2, estado: 'vendido' as const },
    { asiento: 3, estado: 'reservado' as const },
  ]
  const e = estadoEnMapa(ocupacion, new Set([5]))
  expect([1, 2, 3, 4, 5].map((id) => e(a(id)))).toEqual(['disponible', 'ocupado', 'ocupado', 'disponible', 'seleccionado'])
  // Una clase sin tarifa en línea no se vende en la web.
  expect(estadoEnMapa([], new Set(), new Set(['A']))(a(9, 'B'))).toBe('bloqueado')
})

describe('elección de asientos', () => {
  const asiento = (id: number, centavos = 27500): AsientoElegido => ({ id, numero: id, clase: 'A', centavos })

  it('alterna, ordena y respeta el máximo', () => {
    let r = alternarAsiento([], asiento(7), 2)
    r = alternarAsiento(r.elegidos, asiento(3), 2)
    expect(r.elegidos.map((a) => a.numero)).toEqual([3, 7])
    expect(alternarAsiento(r.elegidos, asiento(9), 2)).toMatchObject({ lleno: true })
    expect(alternarAsiento(r.elegidos, asiento(3), 2).elegidos.map((a) => a.id)).toEqual([7])
    expect(sumaCentavos(r.elegidos)).toBe(55000)
  })

  it('quita lo que otro ocupó mientras se elegía', () => {
    const elegidos = [asiento(1), asiento(2), asiento(3)]
    const tomados = tomadosPorOtros(elegidos, [
      { asiento: 2, estado: 'reservado' },
      { asiento: 3, estado: 'propio' },
      { asiento: 9, estado: 'vendido' },
    ])
    expect(tomados.map((a) => a.id)).toEqual([2])
  })
})

it('agrupa estaciones por departamento, "otros" al final', () => {
  const g = porDepartamento(
    [
      { id: 1, nombre: 'Santa Elena', departamento: 'Petén' },
      { id: 2, nombre: 'Guatemala', departamento: 'Guatemala' },
      { id: 3, nombre: 'Melchor', departamento: 'Petén' },
      { id: 4, nombre: 'Belice', departamento: null },
    ],
    'Otros',
  )
  expect(g.map((x) => x.departamento)).toEqual(['Guatemala', 'Petén', 'Otros'])
  expect(g[1]!.estaciones.map((e) => e.nombre)).toEqual(['Melchor', 'Santa Elena'])
})

it('importes, días y duración', () => {
  expect(quetzales(119900)).toMatch(/^Q\s?1,199\.00$/)
  expect(sumarDias('2026-12-31', 1)).toBe('2027-01-01')
  expect(duracion('2026-12-15T21:00:00-06:00', '2026-12-16T06:30:00-06:00')).toBe('9 h 30 min')
  expect(duracion(null, '2026-12-16T06:30:00-06:00')).toBeNull()
  expect(porcentajeOcupado({ capacidad: 60, ocupados: 15 })).toBe(25)
})

it('fechas locales', () => {
  expect(diaISO(new Date(2026, 0, 5))).toBe('2026-01-05')
  expect(desdeISO('2026-01-05')?.getDate()).toBe(5)
  expect(desdeISO('x')).toBeNull()
})

describe('pago: país y 3-D Secure', () => {
  it('lista los países con Guatemala primero', () => {
    const lista = paises()
    expect(lista[0]).toEqual({ label: 'Guatemala', value: 'GT' })
    expect(lista.some((p) => p.value === 'US')).toBe(true)
  })

  it('exige código postal solo en EE. UU. y Canadá', () => {
    expect(exigeCodigoPostal('US')).toBe(true)
    expect(exigeCodigoPostal('CA')).toBe(true)
    expect(exigeCodigoPostal('GT')).toBe(false)
  })

  it('acepta avisos solo de los orígenes esperados', () => {
    const origenes = ['https://centinelapistag.cardinalcommerce.com']
    expect(deOrigen('https://centinelapistag.cardinalcommerce.com', origenes)).toBe(true)
    expect(deOrigen('https://evil.example', origenes)).toBe(false)
  })

  it('reconoce el aviso de retorno del banco', () => {
    expect(esRetorno3ds({ tipo: 'fdn-3ds', datos: { resultado: 'Y' } })).toBe(true)
    expect(esRetorno3ds({ tipo: 'otro', datos: {} })).toBe(false)
    expect(esRetorno3ds('fdn-3ds')).toBe(false)
  })
})
