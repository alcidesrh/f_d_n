/**
 * Porciones de la gráfica de pastel de la portada: la cola de cuñas angostas
 * se agrupa en "Otros" y "Otros" se abre como otro nivel de la misma gráfica,
 * de mayor a menor; la geometría de cada cuña y dónde cabe su rótulo. Puro
 * (con tests).
 */

export interface Porcion {
  clave: string
  /** Lo que se lee en la lista y en el centro (nombre de la estación, usuario, tipo). */
  etiqueta: string
  /** Lo que la acompaña en la lista (sigla de la estación, nombre del usuario); igual a `etiqueta` si no hay nada más que decir. */
  nombre: string
  valor: number
  /** Dato secundario de la lista y el tooltip (p. ej. "87 boletos"). */
  detalle?: string
}

export interface ReglasNivel {
  /** Cuñas propias como máximo (sin contar "Otros"). */
  maximo: number
  /** Parte mínima del nivel para tener cuña propia (la mayor siempre la tiene). */
  minimo: number
}

/**
 * Hasta 6 cuñas propias + "Otros" y cada una de al menos 5% del nivel (18°,
 * unos 38 px de arco en el teléfono: aloja un blanco táctil de 24 px, WCAG
 * 2.5.8). Datawrapper y Elastic topan en 5–6; lo demás lo lee la lista.
 */
export const REGLAS: ReglasNivel = { maximo: 6, minimo: 0.05 }

/** Todas con cuña propia. */
export const TODAS: ReglasNivel = { maximo: Infinity, minimo: 0 }

export interface Nivel {
  /** Las que tienen cuña propia, de mayor a menor. */
  porciones: Porcion[]
  /** Lo que agrupa "Otros" (al menos dos; vacío si no hay "Otros"). */
  resto: Porcion[]
  /** Suma del nivel, "Otros" incluido. */
  total: number
}

const suma = (p: Porcion[]) => p.reduce((s, x) => s + x.valor, 0)

/** De mayor a menor; a igual valor, por etiqueta (el orden no salta entre recargas). */
export function ordenar(porciones: Porcion[]): Porcion[] {
  return porciones.filter((p) => p.valor > 0).sort((a, b) => b.valor - a.valor || a.etiqueta.localeCompare(b.etiqueta, 'es'))
}

/**
 * Un nivel de la gráfica, de mayor a menor: las `maximo` mayores que lleguen
 * a `minimo` del total tienen cuña propia; el resto va a "Otros", que nunca
 * agrupa una sola (esa se dibuja como es).
 */
export function nivel(porciones: Porcion[], reglas: ReglasNivel = REGLAS): Nivel {
  const orden = ordenar(porciones)
  const total = suma(orden)
  let propias = 0
  while (propias < orden.length && propias < reglas.maximo && (propias === 0 || orden[propias]!.valor / total >= reglas.minimo)) propias++
  if (orden.length - propias === 1) propias++
  return { porciones: orden.slice(0, propias), resto: orden.slice(propias), total }
}

/**
 * La línea de lectura de un reparto largo: cuánto suman las tres mayores y
 * cuántas quedan bajo el 2% del total cada una. Null si son pocas para que
 * valga la pena.
 */
export function lectura(porciones: Porcion[], nombre: string): string | null {
  const orden = ordenar(porciones)
  const total = suma(orden)
  if (orden.length < 8 || total <= 0) return null
  const tres = suma(orden.slice(0, 3)) / total
  const chicas = orden.filter((p) => p.valor / total < 0.02).length
  const parte = `${Math.round(tres * 100)}%`
  return chicas
    ? `Los 3 primeros ${nombre} suman ${parte}; ${chicas} de ${orden.length} quedan bajo 2% cada uno.`
    : `Los 3 primeros ${nombre} suman ${parte} del total.`
}

/** Una cuña ya ubicada: ángulos en radianes desde las 12, en el sentido del reloj. */
export interface Cuna {
  clave: string
  inicio: number
  fin: number
  /** Parte del nivel (0..1). */
  parte: number
}

/** Ubica las cuñas del nivel (las propias y, al final, "Otros" con la clave `otros`). */
export function cunas(n: Nivel): Cuna[] {
  const lista = [...n.porciones.map((p) => ({ clave: p.clave, valor: p.valor })), ...(n.resto.length ? [{ clave: OTROS, valor: suma(n.resto) }] : [])]
  let angulo = 0
  return lista.map((p) => {
    const parte = n.total > 0 ? p.valor / n.total : 0
    const inicio = angulo
    angulo += parte * 2 * Math.PI
    return { clave: p.clave, inicio, fin: angulo, parte }
  })
}

export const OTROS = 'otros'

const punto = (r: number, a: number) => `${(r * Math.sin(a)).toFixed(3)},${(-r * Math.cos(a)).toFixed(3)}`

/**
 * Trazo de una cuña de dona centrada en el origen, de `inicio` a `fin`
 * (radianes desde las 12, sentido del reloj). Una cuña de toda la vuelta se
 * traza como dos medias (un arco SVG no cierra sobre sí mismo).
 */
export function arco(inicio: number, fin: number, exterior: number, interior: number): string {
  if (fin - inicio >= 2 * Math.PI - 1e-9) {
    const medio = inicio + Math.PI
    return `${arco(inicio, medio, exterior, interior)}${arco(medio, inicio + 2 * Math.PI, exterior, interior)}`
  }
  const grande = fin - inicio > Math.PI ? 1 : 0
  return `M${punto(exterior, inicio)}A${exterior},${exterior} 0 ${grande} 1 ${punto(exterior, fin)}L${punto(interior, fin)}A${interior},${interior} 0 ${grande} 0 ${punto(interior, inicio)}Z`
}

/**
 * La cuña bajo el puntero, por su ángulo: cada cuña se puede tocar en al
 * menos `minimo` radianes centrados en ella (una cuña de 1° no se acierta
 * con el dedo); donde dos se disputan el ángulo gana la más cercana a su centro.
 */
export function cunaEn(lista: Cuna[], angulo: number, minimo = 0): Cuna | null {
  const a = ((angulo % (2 * Math.PI)) + 2 * Math.PI) % (2 * Math.PI)
  let mejor: Cuna | null = null
  let distancia = Infinity
  for (const c of lista) {
    const centro = (c.inicio + c.fin) / 2
    const mitad = Math.max(c.fin - c.inicio, minimo) / 2
    const d = Math.min(Math.abs(a - centro), 2 * Math.PI - Math.abs(a - centro))
    if (d <= mitad && d / mitad < distancia) {
      mejor = c
      distancia = d / mitad
    }
  }
  return mejor
}

/** Ángulo desde las 12, en el sentido del reloj, de un punto relativo al centro. */
export const anguloDe = (x: number, y: number) => (Math.atan2(x, -y) + 2 * Math.PI) % (2 * Math.PI)

/** Ancho de las cifras de un porcentaje ("24%", "4.5%", "<0.1%") con números tabulares, en tamaños de letra (un poco de más). */
export function anchoCifras(texto: string): number {
  return [...texto].reduce((s, c) => s + (c === '%' ? 0.85 : c === '.' ? 0.3 : 0.6), 0)
}

/**
 * Dónde va el rótulo de una cuña (su centro, en las unidades del trazo), o
 * null si no cabe: la caja del texto, a media altura del anillo sobre la
 * bisectriz, debe quedar dentro de la cuña con `aire` de margen hasta los
 * bordes. Un rótulo que no cabe no se recorta: lo dicen la lista y el centro.
 */
export function lugarRotulo(c: Cuna, ancho: number, alto: number, exterior: number, interior: number, aire = 2): { x: number; y: number } | null {
  const medio = (c.inicio + c.fin) / 2
  const mitad = (c.fin - c.inicio) / 2
  const radio = (exterior + interior) / 2
  const x = radio * Math.sin(medio)
  const y = -radio * Math.cos(medio)
  const [x0, x1, y0, y1] = [x - ancho / 2, x + ancho / 2, y - alto / 2, y + alto / 2]
  // Lo más cerca del centro de la dona puede ser un lado de la caja; lo más lejos, una esquina.
  const cerca = Math.hypot(Math.max(x0, Math.min(0, x1)), Math.max(y0, Math.min(0, y1)))
  if (cerca < interior + aire) return null
  for (const [px, py] of [[x0, y0], [x1, y0], [x1, y1], [x0, y1]] as const) {
    const r = Math.hypot(px, py)
    if (r > exterior - aire) return null
    const desvio = Math.abs(anguloDe(px, py) - medio)
    const d = Math.min(desvio, 2 * Math.PI - desvio)
    // Distancia de la esquina al borde de la cuña (pasado un cuarto de vuelta, el borde más cercano es el centro).
    if (d >= mitad || (mitad - d < Math.PI / 2 ? r * Math.sin(mitad - d) : r) < aire) return null
  }
  return { x, y }
}
