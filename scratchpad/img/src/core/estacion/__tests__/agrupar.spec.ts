import { describe, expect, it } from 'vitest'
import { agruparPorDepartamento } from '../agrupar'

const e = (id: number, nombre: string, departamento: string | null) => ({ id, nombre, departamento })
const lista = [e(1, 'Zacapa', 'Zacapa'), e(2, 'Flores', 'Petén'), e(3, 'Capital', 'Guatemala'), e(4, 'Xela', 'Quetzaltenango'), e(5, 'Suelta', null), e(6, 'Antigua', 'Guatemala')]

describe('agruparPorDepartamento', () => {
  it('Guatemala y Petén primero, luego por nombre y sin departamento al final', () => {
    expect(agruparPorDepartamento(lista).map((g) => g.departamento)).toEqual(['Guatemala', 'Petén', 'Quetzaltenango', 'Zacapa', 'Otros'])
  })
  it('ordena las estaciones por nombre', () => {
    expect(agruparPorDepartamento(lista)[0]!.estaciones.map((x) => x.nombre)).toEqual(['Antigua', 'Capital'])
  })
  it('el departamento del usuario va de primero, sin importar tildes ni mayúsculas', () => {
    expect(agruparPorDepartamento(lista, { propio: 'zacapa' })[0]!.departamento).toBe('Zacapa')
    expect(agruparPorDepartamento(lista, { propio: 'PETEN' }).map((g) => g.departamento).slice(0, 2)).toEqual(['Petén', 'Guatemala'])
  })
  it('usa el rótulo dado para las sin departamento', () => {
    expect(agruparPorDepartamento(lista, { otros: 'Sin depto.' })[4]!.departamento).toBe('Sin depto.')
  })
  it('en Guatemala salen Aguilar Batres y Guatemala antes que el resto', () => {
    const guate = [e(1, 'Antigua', 'Guatemala'), e(2, 'Guatemala', 'Guatemala'), e(3, 'Aguilar Batres', 'Guatemala'), e(4, 'Villa Nueva', 'Guatemala')]
    expect(agruparPorDepartamento(guate)[0]!.estaciones.map((x) => x.nombre)).toEqual(['Aguilar Batres', 'Guatemala', 'Antigua', 'Villa Nueva'])
  })
  it('en Petén salen Santa Elena, Melchor y Poptun antes que el resto, sin acentos ni sufijos', () => {
    const peten = [e(1, 'Flores', 'Petén'), e(2, 'Poptún', 'Petén'), e(3, 'Melchor de Mencos', 'Petén'), e(4, 'Santa Elena, Flores', 'Petén'), e(5, 'Dolores', 'Petén')]
    expect(agruparPorDepartamento(peten)[0]!.estaciones.map((x) => x.nombre)).toEqual(['Santa Elena, Flores', 'Melchor de Mencos', 'Poptún', 'Dolores', 'Flores'])
  })
})
