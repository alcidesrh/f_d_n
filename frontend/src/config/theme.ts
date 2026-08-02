import Aura from '@primeuix/themes/aura'
import Lara from '@primeuix/themes/lara'
import Material from '@primeuix/themes/material'
import Nora from '@primeuix/themes/nora'
import type { Preset } from '@primeuix/themes/types'
import type { PrimaryColor, SurfacePalette, ThemePreset } from '@/types'

export interface PrimaryOption {
  key: PrimaryColor
  label: string
  swatch: string
}

export interface SurfaceOption {
  key: SurfacePalette
  label: string
  bg: string
  accent: string
}

export interface PresetOption {
  key: ThemePreset
  label: string
  value: Preset
}

/** PrimeVue theme presets selectable in the customizer, as in Sakai. */
export const PRESET_OPTIONS: PresetOption[] = [
  { key: 'aura', label: 'Aura', value: Aura },
  { key: 'lara', label: 'Lara', value: Lara },
  { key: 'material', label: 'Material', value: Material },
  { key: 'nora', label: 'Nora', value: Nora }
]

/** Options shown in the header's appearance / theme customizer. */
export const PRIMARY_OPTIONS: PrimaryOption[] = [
  { key: 'blue', label: 'Ruta Azul', swatch: '#2563eb' },
  { key: 'emerald', label: 'Esmeralda', swatch: '#059669' },
  { key: 'amber', label: 'Ámbar Señal', swatch: '#d97706' },
  { key: 'violet', label: 'Violeta Depósito', swatch: '#7c3aed' },
  { key: 'rose', label: 'Rosa Alerta', swatch: '#e11d48' },
  { key: 'teal', label: 'Verde Puerto', swatch: '#0d9488' },
  { key: 'orange', label: 'Naranja Atardecer', swatch: '#ea580c' },
  { key: 'indigo', label: 'Índigo', swatch: '#4f46e5' }
]

export const SURFACE_OPTIONS: SurfaceOption[] = [
  { key: 'slate', label: 'Slate', bg: '#e2e8f0', accent: '#334155' },
  { key: 'gray', label: 'Gray', bg: '#e5e7eb', accent: '#374151' },
  { key: 'zinc', label: 'Zinc', bg: '#e4e4e7', accent: '#3f3f46' },
  { key: 'neutral', label: 'Neutral', bg: '#e5e5e5', accent: '#404040' },
  { key: 'stone', label: 'Stone', bg: '#e7e5e4', accent: '#44403c' }
]
