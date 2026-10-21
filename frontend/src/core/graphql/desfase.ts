/**
 * Errores de validación de GraphQL que delatan un schema viejo en el
 * navegador: piden un campo, un argumento o un tipo que la API ya no tiene, o
 * les falta uno que ahora es obligatorio (ADR-036). El enlace de errores los
 * pasa a quien se registre (`core/metadata/sincronizacion`, que vuelve a pedir
 * la introspección); este módulo no sabe de stores.
 */

const PATRONES = [
  /^Cannot query field "/,
  /^Unknown argument "/,
  /^Unknown type "/,
  /is not defined by type "/,
  /^Field "[^"]+" argument "[^"]+" of type "[^"]+" is required/,
  /of required type "[^"]+" was not provided/,
  /^Variable "\$[^"]+" of type "[^"]+" used in position expecting/,
  /^Enum "[^"]+" cannot represent/,
  /^Value "[^"]+" does not exist in "[^"]+" enum/,
]

export function esErrorDeEsquema(mensaje: string): boolean {
  return PATRONES.some((patron) => patron.test(mensaje))
}

type Oyente = (mensajes: string[]) => void

let oyente: Oyente | null = null

/** Registra a quien atiende el desfase (uno a la vez); devuelve cómo quitarlo. */
export function alDesfasarseElEsquema(nuevo: Oyente): () => void {
  oyente = nuevo
  return () => {
    if (oyente === nuevo) oyente = null
  }
}

/** Pasa los mensajes que delatan un schema viejo (si hay alguno) a quien los atiende. */
export function avisarDesfase(mensajes: string[]): void {
  const desfase = mensajes.filter(esErrorDeEsquema)
  if (desfase.length > 0) oyente?.(desfase)
}
