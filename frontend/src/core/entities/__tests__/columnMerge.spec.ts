import { describe, it, expect } from 'vitest'
import { mergeColumns } from '@/core/entities/columnMerge'
import type { CollectionFieldConfig } from '@/core/entities/types'

const col = (field: string, extra: Partial<CollectionFieldConfig> = {}): CollectionFieldConfig => ({ field, visible: true, ...extra })

describe('mergeColumns', () => {
  it('toma el ancho del servidor cuando cambió y el usuario no lo arrastró', () => {
    const saved = [col('nombre', { width: 'xs', configWidth: 'xs' })]
    const server = [col('nombre', { width: 'xl' })]
    expect(mergeColumns(saved, server)[0]).toMatchObject({ width: 'xl', configWidth: 'xl' })
  })

  it('conserva el ancho que el usuario arrastró', () => {
    const saved = [col('nombre', { width: '320px', configWidth: 'xs' })]
    const server = [col('nombre', { width: 'xl' })]
    expect(mergeColumns(saved, server)[0]).toMatchObject({ width: '320px', configWidth: 'xl' })
  })

  it('conserva el orden y la visibilidad que eligió el usuario, y agrega las columnas nuevas al final', () => {
    const saved = [col('bus', { visible: false, configVisible: true }), col('nombre', { configVisible: true })]
    const server = [col('nombre'), col('bus'), col('empresa')]
    const merged = mergeColumns(saved, server)
    expect(merged.map((c) => c.field)).toEqual(['bus', 'nombre', 'empresa'])
    expect(merged[0]?.visible).toBe(false)
    expect(merged[2]).toMatchObject({ visible: true, showFilter: false })
  })

  it('quita las columnas que ya no existen en el servidor', () => {
    const merged = mergeColumns([col('viejo'), col('nombre')], [col('nombre')])
    expect(merged.map((c) => c.field)).toEqual(['nombre'])
  })

  it('sin configWidth guardado, usa el ancho del servidor', () => {
    const merged = mergeColumns([col('nombre', { width: '12rem' })], [col('nombre', { width: 'lg' })])
    expect(merged[0]?.width).toBe('lg')
  })

  it('oculta la columna que el servidor ocultó si el usuario no la tocó', () => {
    const merged = mergeColumns([col('activo', { configVisible: true })], [col('activo', { visible: false })])
    expect(merged[0]).toMatchObject({ visible: false, configVisible: false })
  })

  it('vuelve a mostrar la que el servidor muestra otra vez', () => {
    const merged = mergeColumns([col('activo', { visible: false, configVisible: false })], [col('activo', { visible: true })])
    expect(merged[0]?.visible).toBe(true)
  })

  it('sin configVisible guardado (vista anterior), manda la visibilidad del servidor', () => {
    const merged = mergeColumns([col('activo')], [col('activo', { visible: false })])
    expect(merged[0]?.visible).toBe(false)
  })
})
