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

  it('conserva el orden y la visibilidad del usuario, y agrega las columnas nuevas al final', () => {
    const saved = [col('bus', { visible: false }), col('nombre')]
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
})
