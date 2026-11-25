/**
 * Casillas de empresa de la distribución de la venta: "Todas" (lista vacía,
 * lo de siempre) o las que se marcan. Puro (con tests).
 */

/**
 * Lo elegido, limpio: solo empresas de las opciones, sin repetir y en orden.
 * Marcar todas una por una es lo mismo que "Todas", y así queda.
 */
export function elegirEmpresas(elegidas: number[], opciones: number[]): number[] {
  const validas = [...new Set(elegidas)].filter((id) => opciones.includes(id)).sort((a, b) => a - b)
  return validas.length === opciones.length ? [] : validas
}
