/**
 * `formBuilder` — estado del Form Builder: árbol de grids/campos, celda
 * seleccionada y derivados (schema de preview, JSON final FormKitSchema).
 *
 * El borrador persiste en localStorage (pinia-plugin-persistedstate). Las
 * mutaciones clonan el root (JSON-safe) y reasignan: el árbol es plano y
 * serializable, así el persist no arrastra proxies.
 */

import { defineStore } from 'pinia'
import type { FormKitSchemaNode } from '@formkit/core'
import {
  clampCols,
  clampRows,
  compileSchema,
  createField,
  createGrid,
  exportDraftJson,
  importBuilderJson,
  resizeGrid,
  structuredCloneSafe,
  type BuilderGrid,
  type BuilderInputType,
  type BuilderNode,
  type CellKey,
} from '@/utils/formkit/schemaBuilder'

export interface FormBuilderState {
  root: BuilderGrid | null
  selectedKey: CellKey | null
}

function fbFindGrid(root: BuilderGrid, gridId: string): BuilderGrid | null {
  if (root.id === gridId) return root
  for (const cell of root.cells) {
    if (cell && cell.kind === 'grid') {
      const found = fbFindGrid(cell, gridId)
      if (found) return found
    }
  }
  return null
}

/** Reemplaza un nodo por id en el árbol; devuelve el mismo ref si no cambió nada. */
function fbReplaceNode(
  node: BuilderNode | null,
  nodeId: string,
  fn: (found: BuilderNode) => BuilderNode | null,
): BuilderNode | null {
  if (!node) return null
  if (node.id === nodeId) return fn(node)
  if (node.kind === 'grid') {
    let changed = false
    const cells = node.cells.map((cell) => {
      if (!cell) return cell
      const next = fbReplaceNode(cell, nodeId, fn)
      if (next !== cell) changed = true
      return next
    })
    return changed ? { ...node, cells } : node
  }
  return node
}

interface RootHolder {
  root: FormBuilderState['root']
  selectedKey: FormBuilderState['selectedKey']
}

/** Celda apuntada por la selección dentro del árbol dado. */
function fbResolveTarget(root: BuilderGrid | null, key: CellKey | null): { grid: BuilderGrid; index: number } | null {
  if (!key || !root) return null
  const grid = fbFindGrid(root, key.gridId)
  if (!grid || key.index < 0 || key.index >= grid.cells.length) return null
  return { grid, index: key.index }
}

/** Aplica `fn` sobre un clon del root; `null` del fn = no-op sin tocar estado. */
function withDraft(store: RootHolder, fn: (draft: BuilderGrid) => BuilderGrid | null): boolean {
  if (!store.root) return false
  const next = fn(structuredCloneSafe(store.root))
  if (!next) return false
  store.root = next
  return true
}

export const useFormBuilderStore = defineStore('formBuilder', {
  persist: true,

  state: (): FormBuilderState => ({
    root: null,
    selectedKey: null,
  }),

  getters: {
    /** Schema con wrappers `.fb-cell` para la vista previa interactiva. */
    previewSchema(st): FormKitSchemaNode[] {
      if (!st.root) return []
      return compileSchema(structuredCloneSafe(st.root), {
        preview: true,
        selectedKey: st.selectedKey,
        onSelectCell: (key) => (this as unknown as { select: (k: CellKey) => void }).select(key),
      })
    },

    /** Resultado final: JSON FormKitSchema limpio (sin helpers de preview). */
    schemaJson(st): string {
      if (!st.root) return ''
      return JSON.stringify(compileSchema(structuredCloneSafe(st.root)), null, 2)
    },

    draftJson(st): string {
      return st.root ? exportDraftJson(st.root) : ''
    },

    /** Nodo contenido en la celda seleccionada (para editar/eliminar). */
    selectedNode(st): BuilderNode | null {
      const target = fbResolveTarget(st.root, st.selectedKey)
      return target?.grid.cells[target.index] ?? null
    },
  },

  actions: {
    select(key: CellKey | null) {
      this.selectedKey = key
    },

    initRoot(cols: number, rows: number) {
      this.root = createGrid(cols, rows)
      this.selectedKey = null
    },

    resizeRoot(cols: number, rows: number) {
      if (!this.root) return
      this.root = resizeGrid(this.root, clampCols(cols), clampRows(rows))
      this.selectedKey = null
    },

    /** Asigna un campo nuevo a la celda seleccionada (debe estar vacía). */
    assignField(inputType: BuilderInputType, props: Record<string, unknown>): boolean {
      return withDraft(this, (draft) => {
        const target = fbResolveTarget(draft, this.selectedKey)
        if (!target || target.grid.cells[target.index] !== null) return null
        target.grid.cells[target.index] = createField(inputType, structuredCloneSafe(props))
        return draft
      })
    },

    /** Crea un grid anidado en la celda seleccionada (debe estar vacía). */
    addNestedGrid(cols: number, rows: number): boolean {
      return withDraft(this, (draft) => {
        const target = fbResolveTarget(draft, this.selectedKey)
        if (!target || target.grid.cells[target.index] !== null) return null
        target.grid.cells[target.index] = createGrid(cols, rows)
        return draft
      })
    },

    /** Reemplaza el contenido de la celda seleccionada por un campo nuevo. */
    replaceSelected(inputType: BuilderInputType, props: Record<string, unknown>): boolean {
      return withDraft(this, (draft) => {
        const target = fbResolveTarget(draft, this.selectedKey)
        if (!target) return null
        target.grid.cells[target.index] = createField(inputType, structuredCloneSafe(props))
        return draft
      })
    },

    removeSelected(): boolean {
      return withDraft(this, (draft) => {
        const target = fbResolveTarget(draft, this.selectedKey)
        if (!target || target.grid.cells[target.index] === null) return null
        target.grid.cells[target.index] = null
        return draft
      })
    },

    patchFieldProps(fieldId: string, patch: Record<string, unknown>): boolean {
      const next = fbReplaceNode(this.root, fieldId, (node) =>
        node.kind === 'field'
          ? { ...node, props: { ...structuredCloneSafe(node.props), ...structuredCloneSafe(patch) } }
          : node,
      )
      if (!next || next === this.root || next.kind !== 'grid') return false
      this.root = structuredCloneSafe(next)
      return true
    },

    clearAll() {
      this.root = null
      this.selectedKey = null
    },

    /** Importa un borrador (`{version, root}` o grid pelado); lanza si es inválido. */
    importDraft(raw: string) {
      this.root = importBuilderJson(raw)
      this.selectedKey = null
    },
  },
})
