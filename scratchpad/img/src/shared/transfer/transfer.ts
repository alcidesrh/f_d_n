/**
 * Reparto de opciones entre los dos conjuntos de `TransferList`: lo asignado
 * sigue el orden de la selección; lo disponible, el de las opciones. El filtro
 * de texto se aplica a los dos.
 */

export interface TransferOption {
  label: string
  value: unknown
}

export interface TransferParts {
  disponibles: TransferOption[]
  asignadas: TransferOption[]
}

export function dividirOpciones(
  options: TransferOption[],
  seleccion: unknown[],
  filtro = '',
): TransferParts {
  const porValor = new Map(options.map((option) => [option.value, option]))
  const enSeleccion = new Set(seleccion)
  const needle = filtro.trim().toLowerCase()
  const coincide = (option: TransferOption) =>
    !needle || option.label.toLowerCase().includes(needle)

  const asignadas = seleccion
    .map((value) => porValor.get(value))
    .filter((option): option is TransferOption => option !== undefined)
  const disponibles = options.filter((option) => !enSeleccion.has(option.value))

  return {
    disponibles: disponibles.filter(coincide),
    asignadas: asignadas.filter(coincide),
  }
}
