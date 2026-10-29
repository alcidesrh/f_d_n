/**
 * Código de barras Code 128 (espejo de `App\Venta\Boleto\Code128`). Dígitos
 * de largo par en el subconjunto C; el resto, en el B (ASCII 32–127).
 */

/** Anchos barra/espacio de cada símbolo (0–105) y el de parada (106). */
export const PATRONES = [
  '212222',
  '222122',
  '222221',
  '121223',
  '121322',
  '131222',
  '122213',
  '122312',
  '132212',
  '221213',
  '221312',
  '231212',
  '112232',
  '122132',
  '122231',
  '113222',
  '123122',
  '123221',
  '223211',
  '221132',
  '221231',
  '213212',
  '223112',
  '312131',
  '311222',
  '321122',
  '321221',
  '312212',
  '322112',
  '322211',
  '212123',
  '212321',
  '232121',
  '111323',
  '131123',
  '131321',
  '112313',
  '132113',
  '132311',
  '211313',
  '231113',
  '231311',
  '112133',
  '112331',
  '132131',
  '113123',
  '113321',
  '133121',
  '313121',
  '211331',
  '231131',
  '213113',
  '213311',
  '213131',
  '311123',
  '311321',
  '331121',
  '312113',
  '312311',
  '332111',
  '314111',
  '221411',
  '431111',
  '111224',
  '111422',
  '121124',
  '121421',
  '141122',
  '141221',
  '112214',
  '112412',
  '122114',
  '122411',
  '142112',
  '142211',
  '241211',
  '221114',
  '413111',
  '241112',
  '134111',
  '111242',
  '121142',
  '121241',
  '114212',
  '124112',
  '124211',
  '411212',
  '421112',
  '421211',
  '212141',
  '214121',
  '412121',
  '111143',
  '111341',
  '131141',
  '114113',
  '114311',
  '411113',
  '411311',
  '113141',
  '114131',
  '311141',
  '411131',
  '211412',
  '211214',
  '211232',
  '2331112',
] as const

const INICIO_B = 104
const INICIO_C = 105
const PARADA = 106

/** Valores de símbolo con inicio, checksum y parada. */
export function simbolos(texto: string): number[] {
  let datos: number[]
  let inicio: number
  if (/^\d+$/.test(texto) && texto.length % 2 === 0) {
    datos = texto.match(/\d\d/g)!.map(Number)
    inicio = INICIO_C
  } else {
    datos = [...texto].map((c) => {
      const o = c.charCodeAt(0)
      if (o < 32 || o > 127) throw new Error('Code 128 B solo admite ASCII imprimible')
      return o - 32
    })
    inicio = INICIO_B
  }
  const suma = datos.reduce((acc, v, i) => acc + v * (i + 1), inicio)
  return [inicio, ...datos, suma % 103, PARADA]
}

/** Barras como `[x, ancho]` en módulos, con 10 módulos de margen a cada lado. */
export function barras(texto: string): { barras: Array<[number, number]>; modulos: number } {
  const lista: Array<[number, number]> = []
  let x = 10
  for (const s of simbolos(texto)) {
    const patron = PATRONES[s]!
    for (let i = 0; i < patron.length; i++) {
      const ancho = Number(patron[i])
      if (i % 2 === 0) lista.push([x, ancho])
      x += ancho
    }
  }
  return { barras: lista, modulos: x + 10 }
}

/** SVG listo para incrustar (ticket, impresión). */
export function svg(texto: string, alto = 50): string {
  const { barras: bs, modulos } = barras(texto)
  const rects = bs.map(([x, w]) => `<rect x="${x}" y="0" width="${w}" height="${alto}"/>`).join('')
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${modulos} ${alto}" width="${modulos * 2}" height="${alto}" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><g fill="#000">${rects}</g></svg>`
}
