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
    expect(agruparPorDepartamento(lista, { otros: 'Sin depto.' }).at(-1)!.departamento).toBe('Sin depto.')
  })
})
