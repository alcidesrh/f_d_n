import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useUiStore } from '@/app/ui'
import { defineSidebarStore } from './sidebarStore'

describe('defineSidebarStore', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('empieza abierto y deriva el ancho del modo', () => {
    const left = defineSidebarStore('left')()
    expect(left.mode).toBe('open')
    expect(left.width).toBe(250)
    left.setMode('mini')
    expect(left.width).toBe(71)
    left.setMode('close')
    expect(left.width).toBe(0)
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
    expect(left.collapsed).toBe(true)
    left.dismiss()
    expect(left.mode).toBe('close')
    expect(left.drawer).toBe(false)
  })

  it('debajo de `lg` abre y cierra el drawer sin tocar el modo de escritorio', () => {
    useUiStore().isMobile = true
    const left = defineSidebarStore('left')()
    left.setMode('mini')
    left.toggle()
    expect(left.drawer).toBe(true)
    expect(left.mode).toBe('mini')
    // El drawer siempre muestra los textos, aunque el modo de escritorio sea `mini`.
    expect(left.collapsed).toBe(false)
    left.dismiss()
    expect(left.drawer).toBe(false)
    expect(left.mode).toBe('mini')
  })
})
