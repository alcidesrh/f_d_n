import { describe, expect, it } from 'vitest'
import type { MiAcceso } from '@/core/acceso/tipos'
import {
  agruparPorTema,
  clavesDeRuta,
  coincide,
  deLaPantalla,
  normalizar,
  partirReferencias,
  referenciasDe,
  veGuia,
  veSiVeOrigen,
} from '../reglas'
import type { Guia } from '../tipos'

const acceso = (capacidades: string[]): MiAcceso => ({
  usuario: { id: 1, username: 'u' },
  sistema: false,
  capacidades: Object.fromEntries(
    capacidades.map((c) => [c, [{ empresas: null, estaciones: null, agencia: null }]]),
  ),
  recursos: {},
  agencia: null,
  estacionTrabajo: null,
  estaciones: [],
  huella: 'h',
})

const guia = (cambios: Partial<Guia> = {}): Guia => ({
  id: 'g',
  tema: 't',
  titulo: 'Cambiar mi contraseña',
  resumen: 'Desde Mi cuenta',
  para: [],
  ...cambios,
})

describe('veGuia', () => {
  it('la de todos la ve cualquiera, también sin acceso cargado', () => {
    expect(veGuia(guia(), null)).toBe(true)
  })

  it('la de un puesto, solo quien encaja en alguna audiencia', () => {
    const g = guia({
      para: [
        { etiqueta: 'Asigna', exige: 'iam.asignar' },
        { etiqueta: 'Roles', exige: 'iam.roles' },
      ],
    })
    expect(veGuia(g, acceso(['venta.vender']))).toBe(false)
    expect(veGuia(g, acceso(['iam.roles']))).toBe(true)
    expect(veGuia(g, null)).toBe(false)
  })
})

describe('coincide', () => {
  it('sin tildes ni mayúsculas, todas las palabras en cualquier orden', () => {
    expect(coincide(guia(), 'CONTRASENA mi')).toBe(true)
    expect(coincide(guia(), 'contraseña rol')).toBe(false)
    expect(coincide(guia(), '  ')).toBe(true)
  })

  it('busca también en los nombres del sistema anterior y en las equivalencias', () => {
    expect(coincide(guia({ palabras: ['bloquear usuario'] }), 'bloquear')).toBe(true)
    expect(
      coincide(guia({ tabla: [{ antes: 'Voucher por día', ahora: 'Límite del rol' }] }), 'voucher'),
    ).toBe(true)
  })

  it('normaliza la ñ y las tildes', () => {
    expect(normalizar('Estación Peñón')).toBe('estacion penon')
  })
})

describe('ayuda por pantalla', () => {
  it('en el CRUD genérico la clave lleva la entidad', () => {
    expect(clavesDeRuta('entity-list', { entity: 'usuario' })).toEqual([
      'entity-list:usuario',
      'entity-list',
    ])
    expect(clavesDeRuta('mi-cuenta')).toEqual(['mi-cuenta'])
    expect(clavesDeRuta(undefined)).toEqual([])
  })

  it('una guía es de la pantalla si la nombra', () => {
    const g = guia({ pantallas: ['entity-list:usuario'] })
    expect(deLaPantalla(g, clavesDeRuta('entity-list', { entity: 'usuario' }))).toBe(true)
    expect(deLaPantalla(g, clavesDeRuta('entity-list', { entity: 'bus' }))).toBe(false)
  })
})

it('agruparPorTema respeta el orden de los temas y omite los vacíos', () => {
  const temas = [
    { id: 'a', titulo: 'A', icono: 'x' },
    { id: 'b', titulo: 'B', icono: 'x' },
    { id: 'c', titulo: 'C', icono: 'x' },
  ]
  const r = agruparPorTema(temas, [guia({ id: '1', tema: 'c' }), guia({ id: '2', tema: 'a' })])
  expect(r.map((g) => g.tema.id)).toEqual(['a', 'c'])
})

describe('referencias entre guías', () => {
  it('parte un texto en trozos normales y enlaces', () => {
    expect(partirReferencias('Vea [anular](ayuda:anular-boleto) antes.')).toEqual([
      { texto: 'Vea ' },
      { texto: 'anular', guia: 'anular-boleto' },
      { texto: ' antes.' },
    ])
    expect(partirReferencias('sin enlaces')).toEqual([{ texto: 'sin enlaces' }])
  })

  it('las referencias de todos los campos de la guía se cuentan', () => {
    const g = guia({ pasos: ['Pulse [Aquí](ayuda:uno)'], ojo: ['Mire [esto](ayuda:dos)'] })
    expect(referenciasDe(g)).toEqual(['uno', 'dos'])
  })

  it('la búsqueda ve el texto del enlace, no su marca', () => {
    const g = guia({ pasos: ['Vea [anular boleto](ayuda:anular-boleto)'] })
    expect(coincide(g, 'anular boleto')).toBe(true)
    expect(coincide(g, 'ayuda')).toBe(false)
  })

  it('quien ve el origen ve el destino; para todos abre todo, pero lo de todos no abre lo restringido', () => {
    const general = guia({ id: 'g', para: [] })
    const restringida = guia({ id: 'r', para: [{ etiqueta: 'Venta', exige: 'venta.vender' }] })
    const otra = guia({ id: 'o', para: [{ etiqueta: 'Boletos', exige: 'boleto.ver' }] })
    const mixta = guia({
      id: 'm',
      para: [
        { etiqueta: 'Venta', exige: 'venta.vender' },
        { etiqueta: 'Boletos', exige: 'boleto.ver' },
      ],
    })
    expect(veSiVeOrigen(general, general)).toBe(true)
    expect(veSiVeOrigen(restringida, general)).toBe(true)
    expect(veSiVeOrigen(general, restringida)).toBe(false)
    expect(veSiVeOrigen(restringida, restringida)).toBe(true)
    expect(veSiVeOrigen(restringida, otra)).toBe(false)
    expect(veSiVeOrigen(mixta, restringida)).toBe(false)
    expect(veSiVeOrigen(restringida, mixta)).toBe(true)
  })
})
