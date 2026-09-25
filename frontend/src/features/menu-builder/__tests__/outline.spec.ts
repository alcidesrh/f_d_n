import { describe, expect, it } from 'vitest'
import {
  blockEnd,
  depthRange,
  flatten,
  liftRow,
  moveBlock,
  normalize,
  placeBlock,
  takeBlock,
  toTree,
  type OutlineRow,
  type TreeNode,
} from '../outline'

/** `[[id, depth], ...]` → filas. */
const rows = (spec: Array<[number, number]>): OutlineRow[] =>
  spec.map(([id, depth]) => ({ key: `item-${id}`, id, depth }))
const shape = (list: OutlineRow[]) => list.map((row) => [row.id, row.depth])

// 1
// 2
//   3
//     4
//   5
// 6
const TREE: TreeNode[] = [
  { id: 1 },
  { id: 2, children: [{ id: 3, children: [{ id: 4 }] }, { id: 5 }] },
  { id: 6 },
]
const OUTLINE = rows([
  [1, 0],
  [2, 0],
  [3, 1],
  [4, 2],
  [5, 1],
  [6, 0],
])

describe('flatten / toTree', () => {
  it('aplana en preorden con la profundidad', () => {
    expect(flatten(TREE)).toEqual(OUTLINE)
  })

  it('reconstruye el mismo árbol (ida y vuelta)', () => {
    const strip = (nodes: TreeNode[]): TreeNode[] =>
      nodes.map((node) => ({ id: node.id, children: strip(node.children ?? []) }))
    expect(toTree(OUTLINE)).toEqual(strip(TREE))
  })

  it('normaliza un esquema inválido antes de anidar', () => {
    expect(
      toTree(
        rows([
          [1, 2],
          [2, 3],
        ]),
      ),
    ).toEqual([{ id: 1, children: [{ id: 2, children: [] }] }])
  })
})

describe('normalize', () => {
  it('la primera fila es raíz y nadie salta más de un nivel', () => {
    expect(
      shape(
        normalize(
          rows([
            [1, 1],
            [2, 3],
            [3, 0],
            [4, 5],
          ]),
        ),
      ),
    ).toEqual([
      [1, 0],
      [2, 1],
      [3, 0],
      [4, 1],
    ])
  })
})

describe('bloques', () => {
  it('blockEnd abarca a todos los descendientes', () => {
    expect(blockEnd(OUTLINE, 1)).toBe(5)
    expect(blockEnd(OUTLINE, 2)).toBe(4)
    expect(blockEnd(OUTLINE, 5)).toBe(6)
  })

  it('takeBlock deja un esquema válido', () => {
    const { rest, block } = takeBlock(OUTLINE, 2)
    expect(shape(block)).toEqual([
      [3, 1],
      [4, 2],
    ])
    expect(shape(rest)).toEqual(shape(normalize(rest)))
  })
})

describe('depthRange', () => {
  it('máximo: un nivel más que la anterior; mínimo: el nivel de la siguiente', () => {
    expect(depthRange(OUTLINE, 0)).toEqual({ min: 0, max: 0 })
    expect(depthRange(OUTLINE, 3)).toEqual({ min: 2, max: 2 })
    expect(depthRange(OUTLINE, 5)).toEqual({ min: 0, max: 2 })
    expect(depthRange(OUTLINE, 6)).toEqual({ min: 0, max: 1 })
  })
})

describe('moveBlock', () => {
  it('reordena entre hermanos llevando a los descendientes', () => {
    // 2 (con 3, 4, 5) delante de 1
    expect(shape(moveBlock(OUTLINE, 1, 0, 0))).toEqual([
      [2, 0],
      [3, 1],
      [4, 2],
      [5, 1],
      [1, 0],
      [6, 0],
    ])
  })

  it('cambia de nivel desplazando toda la sangría del bloque', () => {
    // 3 (con 4) al final como raíz
    expect(shape(moveBlock(OUTLINE, 2, 4, 0))).toEqual([
      [1, 0],
      [2, 0],
      [5, 1],
      [6, 0],
      [3, 0],
      [4, 1],
    ])
  })

  it('anida bajo la fila anterior (sangría a la derecha)', () => {
    expect(shape(moveBlock(OUTLINE, 5, 5, 3))).toEqual([
      [1, 0],
      [2, 0],
      [3, 1],
      [4, 2],
      [5, 1],
      [6, 2],
    ])
  })

  it('ajusta una sangría pedida fuera de rango', () => {
    // 6 como primera fila: solo puede ser raíz
    expect(shape(moveBlock(OUTLINE, 5, 0, 4)).slice(0, 2)).toEqual([
      [6, 0],
      [1, 0],
    ])
    // entre 3 y 4 no puede quedar por encima del nivel de 4
    expect(shape(moveBlock(OUTLINE, 0, 2, 0)).slice(1, 4)).toEqual([
      [3, 1],
      [1, 2],
      [4, 2],
    ])
  })

  it('siempre produce un esquema válido', () => {
    for (let from = 0; from < OUTLINE.length; from++)
      for (let to = 0; to <= OUTLINE.length; to++)
        for (let depth = -1; depth <= 4; depth++) {
          const moved = moveBlock(OUTLINE, from, to, depth)
          expect(moved).toHaveLength(OUTLINE.length)
          expect(shape(moved)).toEqual(shape(normalize(moved)))
        }
  })
})

describe('placeBlock', () => {
  it('inserta un ítem nuevo desde la paleta', () => {
    expect(shape(placeBlock(OUTLINE, rows([[9, 0]]), 3, 2))).toEqual([
      [1, 0],
      [2, 0],
      [3, 1],
      [9, 2],
      [4, 2],
      [5, 1],
      [6, 0],
    ])
  })

  it('en un menú vacío el ítem es raíz', () => {
    expect(shape(placeBlock([], rows([[9, 0]]), 0, 3))).toEqual([[9, 0]])
  })
})

describe('liftRow', () => {
  it('quita la fila y sube a sus descendientes a su lugar', () => {
    expect(shape(liftRow(OUTLINE, 1))).toEqual([
      [1, 0],
      [3, 0],
      [4, 1],
      [5, 0],
      [6, 0],
    ])
  })

  it('una hoja simplemente desaparece', () => {
    expect(shape(liftRow(OUTLINE, 3))).toEqual(shape(OUTLINE).filter(([id]) => id !== 4))
  })
})
