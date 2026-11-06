import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it } from 'vitest'
import type { NavItem, NavMenu } from '@/core/navigation/userMenus'
import { useUserMenusStore } from '@/core/navigation/userMenus'
import { defineSidebarStore } from '../../sidebarStore'
import NavArea from '../NavArea.vue'

// Menú 1: el grupo 10 agrupa al 11 (que agrupa al 12) y al ítem 13. Menú 2: el grupo 20.
const item = (id: number, children: NavItem[] = []): NavItem => ({ id, label: `Ítem ${id}`, icon: null, route: null, path: null, children })
const menu = (id: number, items: NavItem[]): NavMenu => ({ id, nombre: `Menú ${id}`, items })

const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', name: 'home', component: { render: () => null } }] })

describe('NavArea', () => {
  let sidebar: ReturnType<ReturnType<typeof defineSidebarStore>>

  beforeEach(() => {
    setActivePinia(createPinia())
    useUserMenusStore().areas.sidebar_left = [menu(1, [item(10, [item(11, [item(12)])]), item(13)]), menu(2, [item(20, [item(21)])])]
    sidebar = defineSidebarStore('left', 'test-nav-area')()
  })

  const montar = () => mount(NavArea, { props: { area: 'sidebar_left', sidebar }, global: { plugins: [router] } })
  /** Pulsa el título de la sección `n` (0 = primera) y espera al render. */
  const pulsarMenu = async (w: ReturnType<typeof montar>, n: number) => {
    const titulo = w.findAll('.nav-section-title')[n]
    if (!titulo) throw new Error(`No hay título de sección ${n}`)
    await titulo.trigger('click')
    await nextTick()
  }

  it('al cerrar un menú se pliegan sus grupos anidados, sin tocar los de otro menú', async () => {
    const w = montar()
    await pulsarMenu(w, 0)
    sidebar.expandItems([10, 11, 20])
    await pulsarMenu(w, 0)
    expect(sidebar.expandedMenus).toEqual([])
    expect(sidebar.expandedItems).toEqual([20])
  })

  it('abrir otro menú pliega los grupos del anterior', async () => {
    const w = montar()
    await pulsarMenu(w, 0)
    sidebar.expandItems([10, 11])
    await pulsarMenu(w, 1)
    expect(sidebar.expandedMenus).toEqual([2])
    expect(sidebar.expandedItems).toEqual([])
  })

  it('al volver a abrir el menú, sus grupos vuelven cerrados', async () => {
    const w = montar()
    await pulsarMenu(w, 0)
    sidebar.expandItems([10, 11])
    await nextTick()
    expect(w.find('.nav-tree--nested').exists(), 'abierto: se ven los grupos').toBe(true)

    await pulsarMenu(w, 0)
    await pulsarMenu(w, 0)
    expect(w.find('.nav-tree--nested').exists(), 'reabierto: los grupos siguen cerrados').toBe(false)
  })

  it('el botón +/− despliega un grupo y lo pliega al volver a pulsarlo', async () => {
    const w = montar()
    await pulsarMenu(w, 0)
    const boton = w.findAll('.nav-toggle')[0]
    if (!boton) throw new Error('No hay botón +/− en el primer ítem')
    await boton.trigger('click')
    expect(sidebar.expandedItems).toEqual([10])
    await boton.trigger('click')
    expect(sidebar.expandedItems).toEqual([])
  })

  it('el botón +/− de un ítem que también navega lo despliega sin navegar', async () => {
    useUserMenusStore().areas.sidebar_left = [{ id: 3, nombre: 'Menú 3', items: [{ ...item(30, [item(31)]), route: { id: 3, name: 'home', path: '/', params: [] } }] }]
    const w = montar()
    const boton = w.find('.nav-toggle')
    await boton.trigger('click')
    await nextTick()
    expect(boton.attributes('aria-expanded')).toBe('true')
    expect(w.find('.nav-tree--nested').exists()).toBe(true)
  })
})
