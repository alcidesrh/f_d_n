/**
 * Código QR (espejo de `App\Venta\Boleto\CodigoQr`): modo byte, nivel de
 * corrección M y versiones 1 a 6 (hasta 106 bytes), para el código de
 * abordaje del boleto en el ticket de taquilla (ADR-063). Solo ASCII.
 */

/** Por versión: [bloques, codewords de datos por bloque, de corrección por bloque] (nivel M). */
const BLOQUES: Record<number, [number, number, number]> = {
  1: [1, 16, 10],
  2: [1, 28, 16],
  3: [1, 44, 26],
  4: [2, 32, 18],
  5: [2, 43, 24],
  6: [4, 27, 16],
}

type Matriz = boolean[][]

/** La versión más chica en la que caben `bytes` (4 bits de modo + 8 de largo + datos). */
export function version(bytes: number): number {
  for (const [v, [bloques, datos]] of Object.entries(BLOQUES)) {
    if (bytes <= bloques * datos - 2) return Number(v)
  }
  throw new Error('El texto no cabe en un código QR versión 6-M (106 bytes)')
}

/** Producto en GF(2^8) con el polinomio 0x11D. */
function producto(a: number, b: number): number {
  let z = 0
  for (let i = 7; i >= 0; i--) {
    z = (z << 1) ^ ((z >> 7) * 0x11d)
    z ^= ((b >> i) & 1) * a
  }
  return z
}

/** Coeficientes del polinomio generador de Reed-Solomon (sin el término mayor). */
function generador(grado: number): number[] {
  const g = new Array<number>(grado).fill(0)
  g[grado - 1] = 1
  let raiz = 1
  for (let i = 0; i < grado; i++) {
    for (let j = 0; j < grado; j++) {
      g[j] = producto(g[j]!, raiz)
      if (j + 1 < grado) g[j]! ^= g[j + 1]!
    }
    raiz = producto(raiz, 2)
  }
  return g
}

function resto(datos: number[], gen: number[]): number[] {
  const r = new Array<number>(gen.length).fill(0)
  for (const d of datos) {
    const factor = d ^ r.shift()!
    r.push(0)
    gen.forEach((coef, i) => (r[i]! ^= producto(coef, factor)))
  }
  return r
}

/** Los codewords finales: datos y corrección, intercalados por bloque. */
export function codewords(texto: string, v: number): number[] {
  const [bloques, porBloque, correccion] = BLOQUES[v]!
  const capacidad = bloques * porBloque

  // Modo byte (0100), largo en 8 bits, datos y terminador (0000).
  let bits = '0100' + texto.length.toString(2).padStart(8, '0')
  for (const c of texto) {
    const o = c.charCodeAt(0)
    if (o > 127) throw new Error('El código QR solo admite ASCII')
    bits += o.toString(2).padStart(8, '0')
  }
  bits += '0'.repeat(Math.min(4, capacidad * 8 - bits.length))
  bits += '0'.repeat((8 - (bits.length % 8)) % 8)
  const datos = bits.match(/.{8}/g)!.map((b) => parseInt(b, 2))
  for (let relleno = 0xec; datos.length < capacidad; relleno ^= 0xec ^ 0x11) datos.push(relleno)

  const gen = generador(correccion)
  const grupos: number[][] = []
  for (let i = 0; i < datos.length; i += porBloque) grupos.push(datos.slice(i, i + porBloque))
  const paridades = grupos.map((g) => resto(g, gen))

  const final: number[] = []
  for (let i = 0; i < porBloque; i++) for (const g of grupos) final.push(g[i]!)
  for (let i = 0; i < correccion; i++) for (const p of paridades) final.push(p[i]!)
  return final
}

const vacia = (lado: number): Matriz => Array.from({ length: lado }, () => new Array<boolean>(lado).fill(false))

/** Buscadores, separadores, sincronía, alineación, módulo oscuro y la reserva del formato. */
function patrones(m: Matriz, f: Matriz, v: number): void {
  const lado = m.length
  const poner = (x: number, y: number, oscuro: boolean) => {
    if (x >= 0 && x < lado && y >= 0 && y < lado) {
      m[y]![x] = oscuro
      f[y]![x] = true
    }
  }
  for (let i = 0; i < lado; i++) {
    poner(6, i, i % 2 === 0)
    poner(i, 6, i % 2 === 0)
  }
  for (const [cx, cy] of [
    [3, 3],
    [lado - 4, 3],
    [3, lado - 4],
  ] as const) {
    for (let dy = -4; dy <= 4; dy++) {
      for (let dx = -4; dx <= 4; dx++) {
        const d = Math.max(Math.abs(dx), Math.abs(dy))
        poner(cx + dx, cy + dy, d !== 2 && d !== 4)
      }
    }
  }
  if (v >= 2) {
    const c = lado - 7
    for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++) poner(c + dx, c + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1)
  }
  for (let i = 0; i < 9; i++) {
    if (!f[8]![i]) poner(i, 8, false)
    if (!f[i]![8]) poner(8, i, false)
  }
  for (let i = 0; i < 8; i++) {
    poner(lado - 1 - i, 8, false)
    poner(8, lado - 1 - i, false)
  }
  poner(8, lado - 8, true)
}

/** Los bits de los codewords en zigzag, de abajo a la derecha hacia arriba. */
function colocar(m: Matriz, f: Matriz, cw: number[]): void {
  const lado = m.length
  const total = cw.length * 8
  let i = 0
  for (let derecha = lado - 1; derecha >= 1; derecha -= 2) {
    if (derecha === 6) derecha = 5
    for (let vert = 0; vert < lado; vert++) {
      for (let j = 0; j < 2; j++) {
        const x = derecha - j
        const y = ((derecha + 1) & 2) === 0 ? lado - 1 - vert : vert
        if (!f[y]![x] && i < total) {
          m[y]![x] = ((cw[i >> 3]! >> (7 - (i & 7))) & 1) === 1
          i++
        }
      }
    }
  }
}

const MASCARAS: Array<(x: number, y: number) => boolean> = [
  (x, y) => (x + y) % 2 === 0,
  (_, y) => y % 2 === 0,
  (x) => x % 3 === 0,
  (x, y) => (x + y) % 3 === 0,
  (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0,
  (x, y) => ((x * y) % 2) + ((x * y) % 3) === 0,
  (x, y) => (((x * y) % 2) + ((x * y) % 3)) % 2 === 0,
  (x, y) => (((x + y) % 2) + ((x * y) % 3)) % 2 === 0,
]

const enmascarar = (m: Matriz, f: Matriz, mascara: number): Matriz => m.map((fila, y) => fila.map((oscuro, x) => (f[y]![x] ? oscuro : oscuro !== MASCARAS[mascara]!(x, y))))

/** Los 15 bits de formato (nivel M y máscara, con su BCH), en sus dos copias. */
function formato(m: Matriz, mascara: number): void {
  const lado = m.length
  let r = mascara
  for (let i = 0; i < 10; i++) r = (r << 1) ^ ((r >> 9) * 0x537)
  const bits = ((mascara << 10) | r) ^ 0x5412
  const bit = (i: number) => ((bits >> i) & 1) === 1

  for (let i = 0; i <= 5; i++) m[i]![8] = bit(i)
  m[7]![8] = bit(6)
  m[8]![8] = bit(7)
  m[8]![7] = bit(8)
  for (let i = 9; i < 15; i++) m[8]![14 - i] = bit(i)
  for (let i = 0; i < 8; i++) m[8]![lado - 1 - i] = bit(i)
  for (let i = 8; i < 15; i++) m[lado - 15 + i]![8] = bit(i)
}

/** Las cuatro reglas de penalización para elegir la máscara. */
function penalizacion(m: Matriz): number {
  const lado = m.length
  let total = 0
  const lineas = [...m, ...m.map((_, x) => m.map((fila) => fila[x]!))]
  for (const linea of lineas) {
    let corrida = 1
    for (let i = 1; i <= lado; i++) {
      if (i < lado && linea[i] === linea[i - 1]) {
        corrida++
        continue
      }
      if (corrida >= 5) total += corrida - 2
      corrida = 1
    }
    const texto = linea.map((b) => (b ? '1' : '0')).join('')
    total += 40 * (texto.split('10111010000').length - 1 + (texto.split('00001011101').length - 1))
  }
  let oscuros = 0
  for (let y = 0; y < lado; y++) {
    for (let x = 0; x < lado; x++) {
      const v = m[y]![x]
      if (v) oscuros++
      if (x + 1 < lado && y + 1 < lado && v === m[y]![x + 1] && v === m[y + 1]![x] && v === m[y + 1]![x + 1]) total += 3
    }
  }
  return total + 10 * Math.floor(Math.abs(oscuros * 20 - lado * lado * 10) / (lado * lado))
}

/** La matriz del código: `true` = módulo oscuro. Sin margen. */
export function matriz(texto: string): Matriz {
  const v = version(texto.length)
  const lado = 17 + 4 * v
  const m = vacia(lado)
  const f = vacia(lado)
  patrones(m, f, v)
  colocar(m, f, codewords(texto, v))

  let mejor = m
  let menor = Infinity
  for (let mascara = 0; mascara < 8; mascara++) {
    const candidata = enmascarar(m, f, mascara)
    formato(candidata, mascara)
    const p = penalizacion(candidata)
    if (p < menor) {
      menor = p
      mejor = candidata
    }
  }
  return mejor
}

/** SVG listo para incrustar, con 4 módulos de margen y `modulo` píxeles por módulo. */
export function svg(texto: string, modulo = 4): string {
  const m = matriz(texto)
  const n = m.length
  let rects = ''
  m.forEach((fila, y) => {
    for (let x = 0; x < n; x++) {
      if (!fila[x]) continue
      let ancho = 1
      while (x + ancho < n && fila[x + ancho]) ancho++
      rects += `<rect x="${(x + 4) * modulo}" y="${(y + 4) * modulo}" width="${ancho * modulo}" height="${modulo}"/>`
      x += ancho - 1
    }
  })
  const lado = (n + 8) * modulo
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${lado} ${lado}" width="${lado}" height="${lado}" shape-rendering="crispEdges"><rect width="${lado}" height="${lado}" fill="#fff"/><g fill="#000">${rects}</g></svg>`
}
