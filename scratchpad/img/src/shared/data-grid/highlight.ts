/**
 * Resaltado de coincidencias de un filtro en el texto de una celda: el texto
 * partido en tramos, los que coinciden (sin distinguir mayúsculas ni
 * acentos) marcados. Puro: la celda pinta `<mark>` en los marcados.
 */

export interface TextSegment {
  text: string
  match: boolean
}

/** Minúsculas y sin diacríticos, conservando la longitud de cada carácter base. */
function fold(text: string): string {
  return [...text].map((char) => char.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase() || char).join('')
}

/**
 * Tramos de `text` según `needle`. Sin aguja (o vacía) un único tramo sin
 * marcar. Si plegar acentos cambiara la longitud del texto, compara sin
 * plegar (los offsets dejarían de corresponder).
 */
export function splitMatches(text: string, needle: unknown): TextSegment[] {
  const target = needle === null || needle === undefined ? '' : String(needle).trim()
  if (!text) return []
  if (!target) return [{ text, match: false }]

  let haystack = fold(text)
  let probe = fold(target)
  if (haystack.length !== text.length) {
    haystack = text.toLowerCase()
    probe = target.toLowerCase()
  }

  const segments: TextSegment[] = []
  let cursor = 0
  let index = haystack.indexOf(probe)
  while (index !== -1 && probe.length > 0) {
    if (index > cursor) segments.push({ text: text.slice(cursor, index), match: false })
    segments.push({ text: text.slice(index, index + probe.length), match: true })
    cursor = index + probe.length
    index = haystack.indexOf(probe, cursor)
  }
  if (cursor < text.length) segments.push({ text: text.slice(cursor), match: false })
  return segments
}
