import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type { RouteLocationNormalized } from 'vue-router'
import { useNavigationHistoryStore } from '../navigationHistory'

/** Lo mínimo que `push` lee de una ruta resuelta. */
function ruta(fullPath: string, label: string): RouteLocationNormalized {
  return { fullPath, path: fullPath.split('?')[0], name: label, params: {}, meta: { label } } as unknown as RouteLocationNormalized
}

describe('navigationHistory', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('un mismo texto no se repite al cambiar de sección de la misma pantalla', () => {
    const historial = useNavigationHistoryStore()
    historial.push(ruta('/mi-cuenta', 'Mi cuenta'))
    historial.push(ruta('/mi-cuenta?seccion=seguridad', 'Mi cuenta'))
    historial.push(ruta('/mi-cuenta?seccion=apariencia', 'Mi cuenta'))

    expect(historial.entries.map((e) => e.label)).toEqual(['Mi cuenta'])
    expect(historial.entries.at(-1)?.path, 'la migaja lleva a la sección actual').toBe('/mi-cuenta?seccion=apariencia')
  })

  it('el texto repetido pasa al final con la ruta más reciente', () => {
    const historial = useNavigationHistoryStore()
    historial.push(ruta('/boletos', 'Boletos'))
    historial.push(ruta('/mi-cuenta?seccion=perfil', 'Mi cuenta'))
    historial.push(ruta('/salidas', 'Salidas'))
    historial.push(ruta('/mi-cuenta?seccion=seguridad', 'Mi cuenta'))

    expect(historial.entries.map((e) => e.label)).toEqual(['Boletos', 'Salidas', 'Mi cuenta'])
    expect(historial.entries.at(-1)?.path).toBe('/mi-cuenta?seccion=seguridad')
  })

  it('sigue acotado a 4 migajas', () => {
    const historial = useNavigationHistoryStore()
    for (const label of ['A', 'B', 'C', 'D', 'E']) historial.push(ruta(`/${label}`, label))

    expect(historial.entries.map((e) => e.label)).toEqual(['B', 'C', 'D', 'E'])
  })
})
