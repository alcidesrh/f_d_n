import { defineStore } from 'pinia'
import type { PrimaryColor, SurfacePalette, ThemeMode, ThemePreset } from './themeTypes'
import { usePreset } from '@primeuix/themes'
import { themeColors } from './theme'

const MOBILE_BREAKPOINT = 1024

export interface UiState {
  mode: ThemeMode
  primary: PrimaryColor
  surface: SurfacePalette
  preset: ThemePreset
  isMobile: boolean
}

/**
 * Preferencias de interfaz (tema: modo, preset, color primario y superficie)
 * y viewport móvil. Los paneles laterales tienen su propio store
 * (`layout/sidebarStore.ts`).
 */
export const useUiStore = defineStore('ui', {
  persist: true,
  state: (): UiState => ({
    mode: 'light',
    primary: 'blue',
    surface: 'slate',
    preset: 'lara',
    isMobile: typeof window !== 'undefined' ? window.innerWidth <= MOBILE_BREAKPOINT : false,
  }),

  actions: {
    setMode(mode: ThemeMode) {
      this.mode = mode
      this.applyTheme()
    },
    setPrimary(primary: PrimaryColor) {
      this.primary = primary
      this.applyTheme()
    },
    setSurface(surface: SurfacePalette) {
      this.surface = surface
      this.applyTheme()
    },
    setPreset(preset: ThemePreset) {
      this.preset = preset
      this.applyTheme()
    },
    async applyTheme() {
      await usePreset(themeColors(this.preset, this.primary, this.surface, this.mode))
    },
    syncViewport() {
      const mobile = window.innerWidth <= MOBILE_BREAKPOINT
      this.isMobile = mobile
    },
    init() {
      this.syncViewport()
      this.applyTheme()
    },
  },
})
