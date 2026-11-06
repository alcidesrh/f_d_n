import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useUiStore } from '@/app/ui'
import { defineSidebarStore } from '../sidebarStore'

describe('defineSidebarStore', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('empieza abierto y deriva el ancho del modo', () => {
    const left = defineSidebarStore('left')()
    expect(left.mode).toBe('open')
    expect(left.width).toBe(250)
    left.setMode('mini')
    expect(left.width).toBe(60)
    left.setMode('close')
    expect(left.width).toBe(0)
  })

  it('el panel derecho de menús arranca cerrado si no caben los dos', () => {
    vi.stubGlobal('matchMedia', () => ({ matches: false }))
    const derecho = defineSidebarStore('right', 'right')()
    expect(derecho.mode).toBe('close')
    derecho.setMode()
    expect(derecho.mode, 'el botón lo abre con textos').toBe('open')
    expect(defineSidebarStore('left')().mode).toBe('open')
    expect(defineSidebarStore('right', 'panel:migracion')().mode, 'el panel de una ruta son sus controles').toBe('open')
    vi.unstubAllGlobals()
  })

  it('sin argumento alterna con el modo anterior', () => {
    const left = defineSidebarStore('left')()
    left.setMode('close')
    left.setMode()
    expect(left.mode).toBe('open')
    left.setMode()
    expect(left.mode).toBe('close')
  })

  it('cachea una definición por nombre de panel', () => {
    expect(defineSidebarStore('right')).toBe(defineSidebarStore('right'))
    expect(defineSidebarStore('right', 'panel:migracion')).not.toBe(defineSidebarStore('right'))
  })

  it('en escritorio el botón de menú alterna el modo y cerrar lo pasa a `close`', () => {
    useUiStore().isMobile = false
    const left = defineSidebarStore('left')()
    left.toggle()
    expect(left.mode).toBe('mini')
    left.shown = 'mini'
    expect(left.compact).toBe(true)
    left.dismiss()
    expect(left.mode).toBe('close')
    expect(left.drawer).toBe(false)
  })

  it('de los menús solo uno abierto: abrir uno cierra el otro, y cerrarlo deja todos cerrados', () => {
    useUiStore().isMobile = false
    const left = defineSidebarStore('left')()
    left.toggleMenu(1)
    left.toggleMenu(2)
    expect(left.expandedMenus).toEqual([2])
    left.toggleMenu(2)
    expect(left.expandedMenus).toEqual([])
  })

  it('pliega los ítems dados y deja abiertos los demás', () => {
    const left = defineSidebarStore('left')()
    left.expandItems([1, 2, 3])
    left.collapseItems([2, 9])
    expect(left.expandedItems).toEqual([1, 3])
  })

  it('debajo de `lg` abre y cierra el drawer sin tocar el modo de escritorio', () => {
    useUiStore().isMobile = true
    const left = defineSidebarStore('left')()
    left.setMode('mini')
    left.toggle()
    expect(left.drawer).toBe(true)
    expect(left.mode).toBe('mini')
    // El drawer siempre muestra los textos, aunque el modo de escritorio sea `mini`.
    expect(left.compact).toBe(false)
    left.dismiss()
    expect(left.drawer).toBe(false)
    expect(left.mode).toBe('mini')
  })
})
