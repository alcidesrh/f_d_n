import { describe, expect, it } from 'vitest'
import type { NavItem } from '@/core/navigation/userMenus'
import { idsDelArbol } from '../arbol'

const item = (id: number, children: NavItem[] = []): NavItem => ({
  id,
  label: `Ítem ${id}`,
  icon: null,
  route: null,
  path: null,
  children,
})

describe('idsDelArbol', () => {
  it('da los ids de todos los niveles, en orden', () => {
    const menu = [item(1, [item(2, [item(3)]), item(4)]), item(5)]
    expect(idsDelArbol(menu)).toEqual([1, 2, 3, 4, 5])
  })

  it('un menú vacío no tiene ids', () => {
    expect(idsDelArbol([])).toEqual([])
  })
})
