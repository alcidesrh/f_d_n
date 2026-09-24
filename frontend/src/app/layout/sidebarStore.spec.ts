import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
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
})
