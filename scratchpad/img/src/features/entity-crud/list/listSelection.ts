/**
 * Selección de filas del listado: sobrevive al cambio de página o de
 * filtros (se identifica por clave, no por objeto) y conserva el orden en
 * que se eligió. Las filas de la página actual reemplazan a su versión
 * guardada (pueden traer datos más frescos).
 */

/** `on`: agrega `rows` (las que falten, al final); si no, las quita. */
export function toggleRows<T>(current: readonly T[], rows: readonly T[], on: boolean, key: (row: T) => string): T[] {
  const keys = new Set(rows.map(key))
  if (!on) return current.filter((row) => !keys.has(key(row)))
  const fresh = new Map(rows.map((row) => [key(row), row]))
  const kept = current.map((row) => fresh.get(key(row)) ?? row)
  const present = new Set(kept.map(key))
  return [...kept, ...rows.filter((row) => !present.has(key(row)))]
}

export function keySet<T>(rows: readonly T[], key: (row: T) => string): Set<string> {
  return new Set(rows.map(key))
}
