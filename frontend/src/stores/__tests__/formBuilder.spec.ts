import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useFormBuilderStore } from '@/stores/formBuilder'
import type { BuilderGrid } from '@/utils/formkit/schemaBuilder'

describe('formBuilder store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('initRoot crea el grid raíz y resizeRoot preserva contenido', () => {
    const store = useFormBuilderStore()
    expect(store.root).toBeNull()
    store.initRoot(2, 2)
    expect(store.root?.cols).toBe(2)
    expect(store.root?.rows).toBe(2)
    expect(store.root?.cells).toHaveLength(4)

    store.select({ gridId: store.root!.id, index: 3 })
    expect(store.assignField('InputText', { name: 'x' })).toBe(true)
    store.resizeRoot(3, 3)
    // (fila 1, col 1) sigue ocupada tras crecer.
    expect(store.root?.cells[4]).toMatchObject({ kind: 'field' })
  })

  it('assignField solo en celda seleccionada y vacía; schemaJson compila FormKitSchema', () => {
    const store = useFormBuilderStore()
    store.initRoot(2, 1)
    // Sin selección → no-op.
    expect(store.assignField('InputText', { name: 'a' })).toBe(false)

    store.select({ gridId: store.root!.id, index: 0 })
    expect(store.assignField('InputText', { name: 'a', label: 'A' })).toBe(true)
    // Misma celda otra vez → ocupada.
    expect(store.assignField('TextArea', { name: 'b' })).toBe(false)

    const parsed = JSON.parse(store.schemaJson) as Array<{ children?: Array<Record<string, unknown>> }>
    expect(parsed).toHaveLength(1)
    expect(parsed[0]?.children?.[0]).toMatchObject({ $formkit: 'InputText', name: 'a' })
    // El export no arrastra helpers de preview.
    expect(store.schemaJson).not.toContain('fb-cell')
  })

  it('previewSchema envuelve en .fb-cell y el click de celda selecciona', () => {
    const store = useFormBuilderStore()
    store.initRoot(2, 1)
    const [node] = store.previewSchema as Array<{
      children?: Array<Record<string, unknown>>
      attrs?: Record<string, unknown>
    }>
    const cells = node?.children ?? []
    expect(cells).toHaveLength(2)
    const onClick = (cells[0] as { attrs?: { onClick?: () => void } } | undefined)?.attrs?.onClick
    onClick?.()
    expect(store.selectedKey).toEqual({ gridId: store.root!.id, index: 0 })
    expect(store.selectedNode).toBeNull()
  })

  it('addNestedGrid + removeSelected + replaceSelected', () => {
    const store = useFormBuilderStore()
    store.initRoot(2, 2)
    store.select({ gridId: store.root!.id, index: 1 })
    expect(store.addNestedGrid(2, 1)).toBe(true)
    const nested = store.selectedNode as BuilderGrid
    expect(nested.kind).toBe('grid')

    // Anidar dentro del anidado: seleccionar una celda interna.
    store.select({ gridId: nested.id, index: 0 })
    expect(store.addNestedGrid(1, 1)).toBe(true)
    expect((store.selectedNode as BuilderGrid).kind).toBe('grid')

    // Reemplazar la celda raíz 0 por un campo.
    store.select({ gridId: store.root!.id, index: 0 })
    expect(store.replaceSelected('Button', { label: 'Guardar' })).toBe(true)
    expect(store.selectedNode).toMatchObject({ kind: 'field', inputType: 'Button' })

    expect(store.removeSelected()).toBe(true)
    expect(store.selectedNode).toBeNull()
    expect(store.removeSelected()).toBe(false)
  })

  it('importDraft valida y limpia selección; clearAll resetea', () => {
    const store = useFormBuilderStore()
    store.initRoot(1, 1)
    store.select({ gridId: store.root!.id, index: 0 })
    expect(() => store.importDraft('{{')).toThrow('JSON')
    expect(store.root?.rows).toBe(1)

    const draft = {
      version: 1,
      root: { kind: 'grid', id: 'g1', cols: 2, rows: 1, cells: [null, null] },
    }
    store.importDraft(JSON.stringify(draft))
    expect(store.root?.id).toBe('g1')
    expect(store.selectedKey).toBeNull()

    store.clearAll()
    expect(store.root).toBeNull()
    expect(store.schemaJson).toBe('')
  })
})
