import { describe, expect, it } from 'vitest'
import type { RouteLocationRaw } from 'vue-router'
import type { NavItem } from '@/core/navigation/userMenus'
import { agruparCatalogo, cambiosDeCapacidades, debeElegirEstacion, describirAmbito, podarMenu, puedeEntrar, recursoDeRuta, tiene } from '../reglas'
import type { DefinicionCapacidad, MiAcceso } from '../tipos'

const acceso = (cambios: Partial<MiAcceso> = {}): MiAcceso => ({
  usuario: { id: 1, username: 'u' },
  sistema: false,
  capacidades: { 'venta.vender': [{ empresas: [1], estaciones: [3], agencia: null }] },
  recursos: { Bus: { ver: true, editar: false, eliminar: false }, Enclave: { ver: true, editar: false, eliminar: false } },
  agencia: null,
  estacionTrabajo: null,
  estaciones: [],
  huella: 'h',
  ...cambios,
})

const item = (id: number, nombre: string | null, children: NavItem[] = []): NavItem => ({
  id,
  label: `item ${id}`,
  icon: null,
  route: nombre === null ? null : { id, name: nombre, path: null, params: [] },
  path: null,
  children,
})

const enlace = (id: number, path: string): NavItem => ({ ...item(id, null), path })

describe('tiene', () => {
  it('con una capacidad, o cualquiera de varias', () => {
    expect(tiene(acceso(), 'venta.vender')).toBe(true)
    expect(tiene(acceso(), 'boleto.anular')).toBe(false)
    expect(tiene(acceso(), ['boleto.anular', 'venta.vender'])).toBe(true)
  })

  it('sin exigencia sí; sin acceso cargado no', () => {
    expect(tiene(acceso(), undefined)).toBe(true)
    expect(tiene(null, 'venta.vender')).toBe(false)
  })

  it('una capacidad sin ámbitos no cuenta', () => {
    expect(tiene(acceso({ capacidades: { 'venta.vender': [] } }), 'venta.vender')).toBe(false)
  })
})

describe('puedeEntrar', () => {
  it('exige la capacidad de la ruta', () => {
    expect(puedeEntrar(acceso(), { name: 'venta', meta: { capacidad: 'venta.vender' } })).toBe(true)
    expect(puedeEntrar(acceso(), { name: 'salidas', meta: { capacidad: 'salida.ver' } })).toBe(false)
  })

  it('en el listado genérico, ver la entidad; en el formulario nuevo, editarla', () => {
    expect(puedeEntrar(acceso(), { name: 'entity-list', params: { entity: 'bus' } })).toBe(true)
    expect(puedeEntrar(acceso(), { name: 'entity-list', params: { entity: 'usuario' } })).toBe(false)
    expect(puedeEntrar(acceso(), { name: 'entity-form', params: { entity: 'bus', id: '5' } })).toBe(true)
    expect(puedeEntrar(acceso(), { name: 'entity-form', params: { entity: 'bus' } })).toBe(false)
  })

  it('kebab de la URL a la entidad', () => {
    expect(recursoDeRuta('bus-clase')).toBe('BusClase')
    expect(recursoDeRuta('boleto_tarifa')).toBe('BoletoTarifa')
  })
})

describe('podarMenu', () => {
  const nombreDe = (destino: RouteLocationRaw) => (typeof destino === 'object' && 'name' in destino ? destino.name : destino)
  const permitido = (destino: RouteLocationRaw) => !['salidas', '/reservado'].includes(String(nombreDe(destino)))

  it('quita lo que no puede abrir y los grupos que quedan vacíos', () => {
    const menu = [item(1, 'venta'), item(2, null, [item(3, 'salidas')]), item(4, null, [item(5, 'venta'), item(6, 'salidas')])]

    const podado = podarMenu(menu, permitido)

    expect(podado.map((i) => i.id)).toEqual([1, 4])
    expect(podado[1]!.children.map((i) => i.id)).toEqual([5])
  })

  it('pide por su path a un enlace: lo quita si no puede abrirlo', () => {
    const menu = [enlace(1, '/chequeo'), enlace(2, '/reservado'), item(3, null, [enlace(4, '/reservado')])]

    const podado = podarMenu(menu, permitido)

    expect(podado.map((i) => i.id)).toEqual([1])
  })
})

describe('debeElegirEstacion', () => {
  const tres = [
    { id: 3, nombre: 'Cobán', departamento: null },
    { id: 4, nombre: 'Petén', departamento: null },
  ]

  it('si solo opera en estaciones concretas y no tiene una', () => {
    expect(debeElegirEstacion(acceso({ estaciones: tres }))).toBe(true)
    expect(debeElegirEstacion(acceso({ estaciones: tres, estacionTrabajo: tres[0]! }))).toBe(false)
  })

  it('no a quien tiene todas las estaciones ni a una agencia', () => {
    const oficina = acceso({ estaciones: tres, capacidades: { 'reporte.ventas': [{ empresas: [1], estaciones: null, agencia: null }] } })
    expect(debeElegirEstacion(oficina)).toBe(false)
    expect(debeElegirEstacion(acceso({ estaciones: tres, agencia: { id: 1, nombre: 'A' } }))).toBe(false)
  })
})

describe('agruparCatalogo', () => {
  const c = (codigo: string, area: string, recurso: string | null = null, operacion: DefinicionCapacidad['operacion'] = null): DefinicionCapacidad => ({
    codigo,
    nombre: recurso ? `Buses: ${operacion}` : codigo,
    area,
    dimensiones: [],
    limites: [],
    recurso,
    operacion,
  })

  it('las del dominio como casillas y las del CRUD como filas por entidad', () => {
    const grupos = agruparCatalogo([c('salida.ver', 'Salidas'), c('bus.ver', 'Flota', 'Bus', 'ver'), c('bus.editar', 'Flota', 'Bus', 'editar')])

    expect(grupos.map((g) => g.area)).toEqual(['Salidas', 'Flota'])
    expect(grupos[1]!.recursos).toHaveLength(1)
    expect(grupos[1]!.recursos[0]!.nombre).toBe('Buses')
    expect(grupos[1]!.recursos[0]!.editar?.codigo).toBe('bus.editar')
    expect(grupos[1]!.recursos[0]!.eliminar).toBeUndefined()
  })
})

describe('describirAmbito', () => {
  const nombres = { empresas: new Map([[1, 'FDN']]), estaciones: new Map([[3, 'Cobán']]) }

  it('nombra empresas y estaciones, o "todas"', () => {
    expect(describirAmbito({ empresas: [1], estaciones: null }, nombres)).toBe('FDN · todas las estaciones')
    expect(describirAmbito({ empresas: null, estaciones: [3] }, nombres)).toBe('Todas las empresas · Cobán')
    expect(describirAmbito({ empresas: [], estaciones: [3] }, nombres)).toBe('Ninguna empresa · Cobán')
    expect(describirAmbito({ empresas: null, estaciones: null, agencia: 7 }, { ...nombres, agencia: 'Belice' })).toBe('Agencia Belice')
  })
})

describe('cambiosDeCapacidades', () => {
  it('lo agregado y lo quitado', () => {
    expect(cambiosDeCapacidades(['a', 'b'], ['b', 'c'])).toEqual({ agregadas: ['c'], quitadas: ['a'] })
  })
})
