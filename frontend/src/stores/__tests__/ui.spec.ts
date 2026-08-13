import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useUiStore } from '@/stores/ui'

describe('useUiStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('starts with the left panel open and the right panel minimized', () => {
    const ui = useUiStore()
    expect(ui.leftState).toBe('open')
    expect(ui.rightState).toBe('mini')
  })

  it('cycles the left panel through open -> mini -> close -> open', () => {
    const ui = useUiStore()
    ui.isMobile = false
    expect(ui.leftState).toBe('open')
    ui.cycleLeft()
    expect(ui.leftState).toBe('mini')
    ui.cycleLeft()
    expect(ui.leftState).toBe('close')
    ui.cycleLeft()
    expect(ui.leftState).toBe('open')
  })

  it('toggles the mobile overlay instead of cycling state when on mobile', () => {
    const ui = useUiStore()
    ui.isMobile = true
    expect(ui.mobileLeftOpen).toBe(false)
    ui.cycleLeft()
    expect(ui.mobileLeftOpen).toBe(true)
    expect(ui.leftState).toBe('open') // underlying state untouched
  })

  it('derives pixel widths from panel state', () => {
    const ui = useUiStore()
    ui.setLeft('mini')
    expect(ui.leftWidth).toBe('70px')
    ui.setLeft('close')
    expect(ui.leftWidth).toBe('0px')
  })
})
