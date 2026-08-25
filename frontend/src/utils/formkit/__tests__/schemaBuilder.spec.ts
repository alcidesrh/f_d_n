import { describe, it, expect } from 'vitest'
import {
  BUILDER_INPUT_TYPES,
  compileSchema,
  createField,
  createGrid,
  exportDraftJson,
  gridClass,
  importBuilderJson,
  parseOptionsText,
  resizeGrid,
  type BuilderGrid,
} from '@/utils/formkit/schemaBuilder'

describe('factories y clamps', () => {
  it('createGrid genera cells = rows*cols vacías', () => {
    const grid = createGrid(3, 2)
    expect(grid.cols).toBe(3)
    expect(grid.rows).toBe(2)
    expect(grid.cells).toHaveLength(6)
    expect(grid.cells.every((cell) => cell === null)).toBe(true)
  })

  it('clampa cols a 1..6 y rows a >=1', () => {
    expect(createGrid(99, 0).cols).toBe(6)
    expect(createGrid(99, 0).rows).toBe(1)
  })
})

describe('gridClass', () => {
  it('mapea cols a clases tailwind mobile-first', () => {
    expect(gridClass(2)).toBe('grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4')
    expect(gridClass(6)).toContain('md:grid-cols-6')
  })
})

describe('compileSchema (export)', () => {
  it('compila campos con $formkit + props + key estable', () => {
    const root: BuilderGrid = {
      ...createGrid(2, 1),
      cells: [createField('InputText', { name: 'nombre', label: 'Nombre' }), null],
    }
    const [node] = compileSchema(root)
    const record = node as { attrs?: { class?: string }; children?: Array<Record<string, unknown>> }
    expect(record.attrs?.class).toBe(gridClass(2))
    expect(record.children).toHaveLength(1)
    expect(record.children?.[0]).toMatchObject({
      $formkit: 'InputText',
      name: 'nombre',
      label: 'Nombre',
      key: (root.cells[0] as { id: string }).id,
    })
  })

  it('preserva posiciones: huecos intermedios → spacers; vacíos finales descartados', () => {
    const root: BuilderGrid = {
      ...createGrid(2, 2),
      cells: [null, createField('InputText', { name: 'a' }), null, createField('InputText', { name: 'b' })],
    }
    const [node] = compileSchema(root)
    const children = (node as { children?: unknown[] }).children ?? []
    // Celdas 0..3: spacer, campo a, spacer, campo b (la celda 3 es la última llena).
    expect(children).toHaveLength(4)
    expect(children[0]).toEqual({ $el: 'div' })
    expect(children[1]).toMatchObject({ $formkit: 'InputText', name: 'a' })
    expect(children[2]).toEqual({ $el: 'div' })
    expect(children[3]).toMatchObject({ $formkit: 'InputText', name: 'b' })
  })

  it('descarta todas las celdas si el grid está vacío', () => {
    const [node] = compileSchema(createGrid(3, 3))
    expect((node as { children?: unknown[] }).children).toHaveLength(0)
  })

  it('compila grids anidados recursivamente', () => {
    const nested = createGrid(2, 1)
    nested.cells[0] = createField('Button', { label: 'Guardar', severity: 'success' })
    const root = createGrid(1, 1)
    root.cells[0] = nested
    const [node] = compileSchema(root)
    const outerChildren =
      (node as { children?: Array<{ attrs?: Record<string, unknown>; children?: Array<Record<string, unknown>> }> })
        .children ?? []
    const inner = outerChildren[0]
    expect(inner?.attrs?.class).toBe(gridClass(2))
    expect(inner?.children?.[0]).toMatchObject({ $formkit: 'Button', label: 'Guardar' })
  })

  it('produce JSON plano (round-trip JSON.stringify)', () => {
    const root = createGrid(2, 1)
    root.cells[0] = createField('Select', {
      name: 'ruta',
      options: [
        { label: 'A', value: '/api/a/1' },
        { label: 'B', value: '/api/b/2' },
      ],
    })
    const schema = compileSchema(root)
    expect(JSON.parse(JSON.stringify(schema))).toEqual(schema)
  })
})

describe('compileSchema (preview)', () => {
  it('envuelve cada celda en .fb-cell y marca la seleccionada', () => {
    const root = createGrid(2, 1)
    root.cells[0] = createField('InputText', { name: 'a' })
    const [node] = compileSchema(root, {
      preview: true,
      selectedKey: { gridId: root.id, index: 1 },
    })
    const cells = ((node as { children?: Array<{ attrs?: Record<string, unknown> }> }).children ?? [])
    expect(cells).toHaveLength(2)
    expect(cells[0]?.attrs).toMatchObject({ class: 'fb-cell' })
    expect(cells[1]?.attrs).toMatchObject({ class: 'fb-cell fb-cell--selected' })
  })

  it('celdas vacías reciben clase fb-cell--empty y onClick reporta la clave', () => {
    const root = createGrid(1, 1)
    const clicks: Array<{ gridId: string; index: number }> = []
    const [node] = compileSchema(root, {
      preview: true,
      onSelectCell: (key) => clicks.push(key),
    })
    const cell = ((node as { children?: Array<{ attrs?: Record<string, unknown> }> }).children ?? [])[0]
    const onClick = cell?.attrs?.onClick as (() => void) | undefined
    expect(onClick).toBeTypeOf('function')
    expect(cell?.attrs).toMatchObject({ class: 'fb-cell fb-cell--empty', 'data-cell': `${root.id}:0` })
    onClick?.()
    expect(clicks).toEqual([{ gridId: root.id, index: 0 }])
  })

  it('los helpers de preview nunca aparecen en el export', () => {
    const root = createGrid(2, 1)
    root.cells[0] = createField('InputText', { name: 'a' })
    const json = JSON.stringify(compileSchema(root))
    expect(json).not.toContain('fb-cell')
    expect(json).not.toContain('onClick')
    expect(json).not.toContain('data-cell')
  })
})

describe('parseOptionsText', () => {
  it('parsea "Label | valor", default sin pipe y descarta líneas vacías', () => {
    expect(parseOptionsText('Alta | A\nBaja\n\n')).toEqual([
      { label: 'Alta', value: 'A' },
      { label: 'Baja', value: 'Baja' },
    ])
    expect(parseOptionsText('Solo | ')).toEqual([{ label: 'Solo', value: 'Solo' }])
  })
})

describe('resizeGrid', () => {
  it('preserva el contenido en la intersección fila-major', () => {
    const grid = createGrid(2, 2)
    grid.cells[3] = createField('InputText', { name: 'x' })
    const bigger = resizeGrid(grid, 3, 3)
    expect(bigger.cols).toBe(3)
    expect(bigger.rows).toBe(3)
    expect(bigger.cells).toHaveLength(9)
    expect(bigger.cells[4]).toMatchObject({ kind: 'field' })

    const smaller = resizeGrid(bigger, 1, 1)
    expect(smaller.cells).toHaveLength(1)
    expect(smaller.cells[0]).toBeNull()
  })
})

describe('import/export de borrador', () => {
  it('round-trip exportDraftJson → importBuilderJson', () => {
    const root = createGrid(2, 1)
    root.cells[0] = createField('TextArea', { name: 'descripcion', rows: 4 })
    root.cells[1] = createGrid(1, 1)
    const revived = importBuilderJson(exportDraftJson(root))
    expect(revived.cols).toBe(2)
    expect(revived.cells[0]).toMatchObject({ kind: 'field', inputType: 'TextArea' })
    expect(revived.cells[1]).toMatchObject({ kind: 'grid', rows: 1, cols: 1 })
  })

  it('acepta grid pelado (sin wrapper version/root)', () => {
    const root = createGrid(1, 1)
    expect(importBuilderJson(JSON.stringify(root)).kind).toBe('grid')
  })

  it('rechaza JSON roto, shapes inválidos e inputTypes desconocidos', () => {
    expect(() => importBuilderJson('{nope')).toThrow('JSON')
    expect(() => importBuilderJson('{"kind":"field"}')).toThrow('raíz')
    const bad = { kind: 'grid', id: 'g', cols: 2, rows: 1, cells: [{ kind: 'field', inputType: 'NoExiste', props: {} }, null] }
    expect(() => importBuilderJson(JSON.stringify(bad))).toThrow('inputType')
    const wrongCells = { kind: 'grid', id: 'g', cols: 2, rows: 2, cells: [] }
    expect(() => importBuilderJson(JSON.stringify(wrongCells))).toThrow('cells')
  })

  it('BUILDER_INPUT_TYPES cubre todos los inputs registrados + Button', () => {
    for (const type of ['InputText', 'MultiSelect', 'TreeSelect', 'Button'] as const) {
      expect(BUILDER_INPUT_TYPES).toContain(type)
    }
  })
})
