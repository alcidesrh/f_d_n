/**
 * Árbol de un menú como esquema plano con sangría: cada fila es un ítem con
 * su `depth` (0 = raíz) y el orden del array es el recorrido en preorden.
 * Así el editor pinta todos los nodos "expandidos" y un arrastre solo decide
 * dos números: la fila destino y la sangría.
 *
 * Invariante de un esquema válido: la primera fila tiene `depth` 0 y cada
 * fila tiene como mucho un nivel más que la anterior. Un nodo arrastra
 * consigo su bloque (él y sus descendientes, que son las filas siguientes
 * con `depth` mayor).
 */

/** Nodo del árbol tal como lo guarda/lee el backend (`/menus/{id}/tree`). */
export interface TreeNode {
  id: number
  children?: TreeNode[]
}

export interface OutlineRow {
  /** Clave estable para el v-for y el drag & drop. */
  key: string
  /** Id del `MenuItem`. */
  id: number
  depth: number
}

export const rowKey = (id: number) => `item-${id}`

export function flatten(tree: readonly TreeNode[], depth = 0): OutlineRow[] {
  return tree.flatMap((node) => [
    { key: rowKey(node.id), id: node.id, depth },
    ...flatten(node.children ?? [], depth + 1),
  ])
}

/** Esquema → árbol anidado (normaliza antes, así nunca produce un árbol inválido). */
export function toTree(rows: readonly OutlineRow[]): TreeNode[] {
  const roots: TreeNode[] = []
  const stack: Array<{ depth: number; children: TreeNode[] }> = [{ depth: -1, children: roots }]
  for (const row of normalize(rows)) {
    while (stack.length > 1 && stack[stack.length - 1]!.depth >= row.depth) stack.pop()
    const node: TreeNode = { id: row.id, children: [] }
    stack[stack.length - 1]!.children.push(node)
    stack.push({ depth: row.depth, children: node.children! })
  }
  return roots
}

/** Ajusta cada `depth` al rango válido `[0, anterior + 1]`. */
export function normalize(rows: readonly OutlineRow[]): OutlineRow[] {
  let previous = -1
  return rows.map((row) => {
    const depth = Math.max(0, Math.min(row.depth, previous + 1))
    previous = depth
    return depth === row.depth ? row : { ...row, depth }
  })
}

/** Índice (exclusivo) donde termina el bloque de la fila `index`. */
export function blockEnd(rows: readonly OutlineRow[], index: number): number {
  const depth = rows[index]?.depth ?? 0
  let end = index + 1
  while (end < rows.length && rows[end]!.depth > depth) end++
  return end
}

/**
 * Sangrías válidas para un bloque insertado en la posición `at`: como mucho
 * un nivel más que la fila anterior y, para no dejar huérfana a la fila
 * siguiente, al menos su nivel.
 */
export function depthRange(rows: readonly OutlineRow[], at: number): { min: number; max: number } {
  const previous = rows[at - 1]
  const next = rows[at]
  const max = previous ? previous.depth + 1 : 0
  return { min: Math.min(next?.depth ?? 0, max), max }
}

export function clampDepth(rows: readonly OutlineRow[], at: number, depth: number): number {
  const { min, max } = depthRange(rows, at)
  return Math.max(min, Math.min(Math.round(depth), max))
}

/** Inserta `block` en `at` con su raíz en la sangría válida más cercana a `depth`. */
export function placeBlock(
  rows: readonly OutlineRow[],
  block: readonly OutlineRow[],
  at: number,
  depth: number,
): OutlineRow[] {
  if (block.length === 0) return [...rows]
  const index = Math.max(0, Math.min(at, rows.length))
  const offset = clampDepth(rows, index, depth) - block[0]!.depth
  const moved = block.map((row) => ({ ...row, depth: row.depth + offset }))
  return [...rows.slice(0, index), ...moved, ...rows.slice(index)]
}

/** Separa el bloque de la fila `from`: `{ rest, block }`. */
export function takeBlock(
  rows: readonly OutlineRow[],
  from: number,
): { rest: OutlineRow[]; block: OutlineRow[] } {
  const end = blockEnd(rows, from)
  return { rest: [...rows.slice(0, from), ...rows.slice(end)], block: rows.slice(from, end) }
}

/**
 * Mueve el bloque de la fila `from` a la posición `to` del esquema sin el
 * bloque, con la sangría `depth` (ajustada al rango válido).
 */
export function moveBlock(
  rows: readonly OutlineRow[],
  from: number,
  to: number,
  depth: number,
): OutlineRow[] {
  const { rest, block } = takeBlock(rows, from)
  return placeBlock(rest, block, to, depth)
}

/**
 * Quita la fila `index` y sube un nivel a sus descendientes (ocupan su
 * lugar), como hace el backend al borrar un `MenuItem` usado en el menú.
 */
export function liftRow(rows: readonly OutlineRow[], index: number): OutlineRow[] {
  const end = blockEnd(rows, index)
  return [
    ...rows.slice(0, index),
    ...rows.slice(index + 1, end).map((row) => ({ ...row, depth: row.depth - 1 })),
    ...rows.slice(end),
  ]
}
