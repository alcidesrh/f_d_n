/**
 * Variables CSS de la rampa de color de una caja (`--tono-50` … `--tono-900`)
 * para pintarla con un solo juego de reglas. Leen de la rampa de PrimeVue, que
 * el modo oscuro invierte: sirven en ambos modos sin `dark:`.
 */
const PASOS = [50, 100, 200, 300, 500, 600, 700, 800, 900] as const

export function tonos(color: string): Record<string, string> {
  return Object.fromEntries(PASOS.map((p) => [`--tono-${p}`, `var(--p-${color}-${p})`]))
}
