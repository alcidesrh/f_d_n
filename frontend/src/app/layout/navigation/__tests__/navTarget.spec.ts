import { describe, expect, it } from 'vitest'
import type { NavItem } from '@/core/navigation/userMenus'
import { navTarget } from '../navTarget'

const router = {
  hasRoute: (name: string | symbol) => ['dashboard', 'entity-list'].includes(String(name)),
}

const item = (route: Partial<NavItem['route']>): NavItem => ({
  id: 1,
  label: 'Inicio',
  icon: 'home',
  route: { id: 1, name: 'dashboard', path: '/', params: [], ...route },
  children: [],
})

describe('navTarget', () => {
  it('navega por nombre a una ruta existente sin parámetros', () => {
    expect(navTarget(item({}), router)).toEqual({ name: 'dashboard' })
  })

  it('no navega si la ruta exige parámetros que el ítem no guarda', () => {
    expect(
      navTarget(item({ name: 'entity-list', path: '/lista/:entity', params: ['entity'] }), router),
    ).toBeNull()
  })

  it('no navega si la ruta ya no está en el router o no tiene nombre', () => {
    expect(navTarget(item({ name: 'borrada' }), router)).toBeNull()
    expect(navTarget(item({ name: null }), router)).toBeNull()
  })
})
