import { describe, expect, it } from 'vitest'
import type { Enlace, Seccion } from '../api'
import {
  coincide,
  describirDestino,
  destinoValido,
  enlacePayload,
  moverEnlace,
  moverSeccion,
  ordenDe,
  ordenarEnlaces,
  parametrosDeRuta,
  repartirEnColumnas,
  restringido,
  seccionesVisibles,
  ubicacion,
} from '../disposicion'

const enlace = (id: number, etiqueta: string, extra: Partial<Enlace> = {}): Enlace => ({
  id,
  etiqueta,
  icono: 'bus',
  claves: [],
  abrir: { ruta: 'entity-list', parametros: { entity: 'bus' }, path: null },
  agregar: null,
  activo: true,
  ...extra,
})

const secciones: Seccion[] = [
  { id: 1, titulo: 'Flota', icono: 'bus', color: 'blue', activa: true, enlaces: [enlace(10, 'Buses'), enlace(11, 'Asientos', { activo: false })] },
  { id: 2, titulo: 'Caja', icono: 'pos', color: 'lime', activa: true, enlaces: [enlace(20, 'Monedas', { claves: ['Divisas'] })] },
  { id: 3, titulo: 'Oculta', icono: 'x', color: 'red', activa: false, enlaces: [enlace(30, 'Nada')] },
]

describe('orden de las cajas', () => {
  it('mueve una caja sin salirse de la lista', () => {
    expect(moverSeccion(secciones, 2, -1).map((s) => s.id)).toEqual([2, 1, 3])
    expect(moverSeccion(secciones, 1, -1)).toBe(secciones)
    expect(moverSeccion(secciones, 1, 9).map((s) => s.id)).toEqual([2, 3, 1])
  })

  it('ordena los enlaces de una caja y rechaza otra lista', () => {
    expect(ordenarEnlaces(secciones, 1, [11, 10])[0]!.enlaces.map((e) => e.id)).toEqual([11, 10])
    expect(ordenarEnlaces(secciones, 1, [11])[0]!.enlaces.map((e) => e.id)).toEqual([10, 11])
  })

  it('pasa un enlace al final de otra caja', () => {
    const movidas = moverEnlace(secciones, 10, 2)
    expect(ordenDe(movidas)).toEqual([
      { id: 1, enlaces: [11] },
      { id: 2, enlaces: [20, 10] },
      { id: 3, enlaces: [30] },
    ])
    expect(moverEnlace(secciones, 10, 99)).toBe(secciones)
    expect(moverEnlace(secciones, 99, 2)).toBe(secciones)
  })
})

describe('lo que se ve', () => {
  it('quita cajas inactivas, enlaces inactivos y cajas vacías', () => {
    expect(seccionesVisibles(secciones, '').map((s) => [s.id, s.enlaces.map((e) => e.id)])).toEqual([
      [1, [10]],
      [2, [20]],
    ])
  })

  it('busca por texto, claves y título de la caja, sin acentos', () => {
    expect(coincide(enlace(1, 'Monedas', { claves: ['Divisas'] }), 'divisa')).toBe(true)
    expect(coincide(enlace(1, 'Países'), 'paises')).toBe(true)
    expect(coincide(enlace(1, 'Buses'), 'flota buses', 'Flota')).toBe(true)
    expect(coincide(enlace(1, 'Buses'), 'tarifas')).toBe(false)
    expect(seccionesVisibles(secciones, 'divisas').map((s) => s.id)).toEqual([2])
  })

  it('reparte como un muro: cada caja a la columna más baja', () => {
    const pesos: Record<string, number> = { a: 5, b: 2, c: 2, d: 1, e: 4 }
    // d empata (2 y 2) y va a la izquierda; e, a la que quedó más baja.
    expect(repartirEnColumnas(Object.keys(pesos), (k) => pesos[k]!, 3)).toEqual([['a'], ['b', 'd'], ['c', 'e']])
    expect(repartirEnColumnas(['a', 'b'], () => 1, 4)).toEqual([['a'], ['b'], [], []])
    expect(repartirEnColumnas(['a'], () => 1, 0)).toEqual([['a']])
  })
})

describe('destinos', () => {
  const existe = (n: string) => n !== 'borrada'

  it('ruta, path interno y URL externa', () => {
    expect(ubicacion({ ruta: 'entity-list', parametros: { entity: 'bus' }, path: null }, existe)).toEqual({ tipo: 'ruta', to: { name: 'entity-list', params: { entity: 'bus' } } })
    expect(ubicacion({ ruta: null, parametros: {}, path: '/?dias=7' }, existe)).toEqual({ tipo: 'path', to: '/?dias=7' })
    expect(ubicacion({ ruta: null, parametros: {}, path: 'https://sat.gob.gt' }, existe)).toEqual({ tipo: 'externa', href: 'https://sat.gob.gt' })
  })

  it('una ruta que ya no existe o sin destino no navega', () => {
    expect(ubicacion({ ruta: 'borrada', parametros: {}, path: null }, existe)).toBeNull()
    expect(ubicacion(null, existe)).toBeNull()
  })

  it('lee los parámetros del path de una ruta', () => {
    expect(parametrosDeRuta('/form/:entity/:id?')).toEqual([
      { nombre: 'entity', opcional: false },
      { nombre: 'id', opcional: true },
    ])
    expect(parametrosDeRuta('/rutas/:id(nueva|\\d+)')).toEqual([{ nombre: 'id', opcional: false }])
    expect(parametrosDeRuta('/salidas')).toEqual([])
  })

  it('un destino se guarda con sus parámetros o un path válido', () => {
    expect(destinoValido({ ruta: 'entity-list', parametros: { entity: 'bus' }, path: null }, ['entity'])).toBe(true)
    expect(destinoValido({ ruta: 'entity-list', parametros: { entity: ' ' }, path: null }, ['entity'])).toBe(false)
    expect(destinoValido({ ruta: null, parametros: {}, path: '/reporte?x=1' })).toBe(true)
    expect(destinoValido({ ruta: null, parametros: {}, path: 'https://sat.gob.gt' })).toBe(true)
    expect(destinoValido({ ruta: null, parametros: {}, path: 'lista/bus' })).toBe(false)
    expect(destinoValido({ ruta: null, parametros: {}, path: '//otro.com' })).toBe(false)
    expect(destinoValido({ ruta: null, parametros: {}, path: '/a b' })).toBe(false)
    expect(destinoValido(null)).toBe(false)
  })

  it('describe un destino', () => {
    expect(describirDestino({ ruta: 'entity-list', parametros: { entity: 'bus' }, path: null })).toBe('entity-list (entity: bus)')
    expect(describirDestino({ ruta: null, parametros: {}, path: '/x' })).toBe('/x')
    expect(describirDestino(null)).toBe('')
  })
})

describe('payloads', () => {
  it('reenvía el enlace con los cambios y sin acceso si no se pide', () => {
    const p = enlacePayload(enlace(1, 'Buses', { acceso: { roles: [{ id: 1, label: 'R' }], usuarios: [] } }), { activo: false })
    expect(p.activo).toBe(false)
    expect(p).not.toHaveProperty('acceso')
  })

  it('restringido si nombra roles o usuarios', () => {
    expect(restringido(undefined)).toBe(false)
    expect(restringido({ roles: [], usuarios: [] })).toBe(false)
    expect(restringido({ roles: [], usuarios: [{ id: 5, label: 'Ana' }] })).toBe(true)
  })
})
