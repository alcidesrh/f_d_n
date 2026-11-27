/**
 * Catálogo de la apariencia (Mi cuenta → Apariencia): cada rasgo general del
 * diseño que el usuario puede cambiar, sus opciones, el valor de fábrica y
 * cómo se aplica. Puro, con tests: lo usan la store, el editor y la lectura de
 * lo guardado (en el navegador y en la cuenta).
 *
 * Dos clases de ajuste:
 *  - Con `variable`: un parámetro CSS `--apariencia-*` en <html>. Se aplica al
 *    instante, sin regenerar el CSS de PrimeVue (su valor de fábrica también
 *    está en `styles/tema/apariencia.css`; un test los compara).
 *  - Sin `variable` (modo, color primario, superficie y estilo): entran en el
 *    preset de PrimeVue (`preset.ts`), que se regenera al cambiarlos.
 *
 * Un ajuste nuevo: su entrada aquí (y en `GRUPOS`), su parámetro en
 * `apariencia.css` si es de los primeros, y quien lo use (tokens o preset).
 */

export type Modo = 'light' | 'dark'
export type Estilo = 'material' | 'aura' | 'lara' | 'nora'
export type Contraste = 'normal' | 'alto'
export type Fondo = 'blanco' | 'tenue' | 'gris'
export type Fuente = 'roboto' | 'sistema'

export const PRIMARIOS = [
  { valor: 'neutral', etiqueta: 'Neutral', muestra: 'oklch(0.44 0.00 0)' },
  { valor: 'stone', etiqueta: 'Stone', muestra: 'oklch(0.44 0.01 74)' },
  { valor: 'gray', etiqueta: 'Gris', muestra: 'oklch(0.45 0.03 257)' },
  { valor: 'slate', etiqueta: 'Slate', muestra: 'oklch(0.45 0.04 257)' },
  { valor: 'green', etiqueta: 'Verde', muestra: 'oklch(0.63 0.17 149)' },
  { valor: 'emerald', etiqueta: 'Esmeralda', muestra: 'oklch(0.60 0.13 163)' },
  { valor: 'teal', etiqueta: 'Teal', muestra: 'oklch(0.60 0.10 185)' },
  { valor: 'cyan', etiqueta: 'Cyan', muestra: 'oklch(0.61 0.11 222)' },
  { valor: 'sky', etiqueta: 'Sky', muestra: 'oklch(0.59 0.14 242)' },
  { valor: 'blue', etiqueta: 'Azul', muestra: 'oklch(0.55 0.22 263)' },
  { valor: 'indigo', etiqueta: 'Indigo', muestra: 'oklch(0.51 0.23 277)' },
  { valor: 'violet', etiqueta: 'Violeta', muestra: 'oklch(0.54 0.25 293)' },
  { valor: 'yellow', etiqueta: 'Amarillo', muestra: 'oklch(0.68 0.14 76)' },
  { valor: 'amber', etiqueta: 'Ámbar', muestra: 'oklch(0.67 0.16 58)' },
  { valor: 'orange', etiqueta: 'Naranja', muestra: 'oklch(0.65 0.19 41)' },
  { valor: 'red', etiqueta: 'Rojo', muestra: 'oklch(0.58 0.22 27)' },
] as const

export const SUPERFICIES = [
  { valor: 'slate', etiqueta: 'Slate', muestra: '#e2e8f0', acento: '#334155' },
  { valor: 'gray', etiqueta: 'Gray', muestra: '#e5e7eb', acento: '#374151' },
  { valor: 'zinc', etiqueta: 'Zinc', muestra: '#e4e4e7', acento: '#3f3f46' },
  { valor: 'neutral', etiqueta: 'Neutral', muestra: '#e5e5e5', acento: '#404040' },
  { valor: 'stone', etiqueta: 'Stone', muestra: '#e7e5e4', acento: '#44403c' },
] as const

export type Primario = (typeof PRIMARIOS)[number]['valor']
export type Superficie = (typeof SUPERFICIES)[number]['valor']

/** Lo que el usuario eligió: una opción de cada ajuste. */
export interface Ajustes {
  modo: Modo
  primario: Primario
  superficie: Superficie
  contraste: Contraste
  fondo: Fondo
  estilo: Estilo
  esquinas: number
  densidad: number
  sombras: number
  texto: number
  fuente: Fuente
  movimiento: number
}
export type ClaveAjuste = keyof Ajustes

export interface Opcion<V> {
  valor: V
  etiqueta: string
  /** Para el editor: color, fondo, familia… con que se dibuja la opción. */
  muestra?: string
  acento?: string
  icono?: string
}

export interface DefinicionAjuste<V> {
  titulo: string
  /** Qué cambia, para el editor. */
  descripcion: string
  opciones: readonly Opcion<V>[]
  fabrica: V
  /** Parámetro CSS en <html> y su valor para cada opción; sin él, el ajuste va en el preset de PrimeVue. */
  variable?: { nombre: `--apariencia-${string}`; css: (valor: V) => string }
}

const factor = (nombre: `--apariencia-${string}`) => ({ nombre, css: (v: number) => String(v) })

/** Valor CSS de cada fondo de la página (el de fábrica, también en `apariencia.css`). */
const FONDOS: Record<Fondo, string> = {
  blanco: 'var(--p-surface-0)',
  tenue: 'color-mix(in srgb, var(--p-surface-50) 60%, transparent)',
  gris: 'var(--p-surface-100)',
}
const FUENTES: Record<Fuente, string> = {
  roboto: '"Roboto Flex Variable", sans-serif',
  sistema: 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
}

export const AJUSTES: { [K in ClaveAjuste]: DefinicionAjuste<Ajustes[K]> } = {
  modo: {
    titulo: 'Modo',
    descripcion: 'Al entrar por primera vez sigue el del sistema operativo.',
    opciones: [
      { valor: 'light', etiqueta: 'Claro', icono: 'light-mode-outline' },
      { valor: 'dark', etiqueta: 'Oscuro', icono: 'dark-mode-outline' },
    ],
    fabrica: 'light',
  },
  primario: {
    titulo: 'Color primario',
    descripcion: 'Botones, enlaces y lo seleccionado.',
    opciones: PRIMARIOS,
    fabrica: 'blue',
  },
  superficie: {
    titulo: 'Superficie',
    descripcion: 'El tono de fondos, bordes y textos.',
    opciones: SUPERFICIES,
    fabrica: 'slate',
  },
  contraste: {
    titulo: 'Contraste',
    descripcion: 'Alto: textos más oscuros (más claros en el modo oscuro) y bordes más marcados.',
    opciones: [
      { valor: 'normal', etiqueta: 'Normal' },
      { valor: 'alto', etiqueta: 'Alto' },
    ],
    fabrica: 'normal',
    variable: { nombre: '--apariencia-contraste', css: (v) => (v === 'alto' ? '1' : '0') },
  },
  fondo: {
    titulo: 'Fondo de la página',
    descripcion: 'Detrás de tarjetas y formularios, y en la cabecera.',
    opciones: [
      { valor: 'blanco', etiqueta: 'Liso', muestra: FONDOS.blanco },
      { valor: 'tenue', etiqueta: 'Tenue', muestra: FONDOS.tenue },
      { valor: 'gris', etiqueta: 'Gris', muestra: FONDOS.gris },
    ],
    fabrica: 'tenue',
    variable: { nombre: '--apariencia-fondo', css: (v) => FONDOS[v] },
  },
  estilo: {
    titulo: 'Estilo de componentes',
    descripcion: 'El diseño base de botones, campos y menús.',
    opciones: [
      { valor: 'material', etiqueta: 'Material' },
      { valor: 'aura', etiqueta: 'Aura' },
      { valor: 'lara', etiqueta: 'Lara' },
      { valor: 'nora', etiqueta: 'Nora' },
    ],
    fabrica: 'lara',
  },
  esquinas: {
    titulo: 'Esquinas',
    descripcion: 'Qué tan redondeados son botones, campos, tarjetas y diálogos.',
    opciones: [
      { valor: 0, etiqueta: 'Rectas' },
      { valor: 0.5, etiqueta: 'Suaves' },
      { valor: 1, etiqueta: 'Normales' },
      { valor: 1.5, etiqueta: 'Redondas' },
      { valor: 2, etiqueta: 'Muy redondas' },
    ],
    fabrica: 1,
    variable: factor('--apariencia-esquinas'),
  },
  densidad: {
    titulo: 'Densidad',
    descripcion: 'El espacio dentro de botones, campos, listas, tablas y menús.',
    opciones: [
      { valor: 0.75, etiqueta: 'Compacta' },
      { valor: 1, etiqueta: 'Normal' },
      { valor: 1.25, etiqueta: 'Amplia' },
    ],
    fabrica: 1,
    variable: factor('--apariencia-densidad'),
  },
  sombras: {
    titulo: 'Sombras',
    descripcion: 'Bajo menús, diálogos, avisos y tarjetas.',
    opciones: [
      { valor: 0, etiqueta: 'Ninguna' },
      { valor: 1, etiqueta: 'Suaves' },
      { valor: 1.75, etiqueta: 'Marcadas' },
    ],
    fabrica: 1,
    variable: factor('--apariencia-sombras'),
  },
  texto: {
    titulo: 'Tamaño del texto',
    descripcion: 'Textos y controles crecen juntos.',
    opciones: [
      { valor: 14, etiqueta: 'Pequeño' },
      { valor: 15, etiqueta: 'Normal' },
      { valor: 16, etiqueta: 'Grande' },
      { valor: 17, etiqueta: 'Más grande' },
      { valor: 18, etiqueta: 'Muy grande' },
    ],
    fabrica: 15,
    variable: { nombre: '--apariencia-texto', css: (v) => `${v}px` },
  },
  fuente: {
    titulo: 'Tipografía',
    descripcion: 'La letra de toda la aplicación.',
    opciones: [
      { valor: 'roboto', etiqueta: 'Roboto Flex', muestra: FUENTES.roboto },
      { valor: 'sistema', etiqueta: 'La del sistema', muestra: FUENTES.sistema },
    ],
    fabrica: 'roboto',
    variable: { nombre: '--apariencia-fuente', css: (v) => FUENTES[v] },
  },
  movimiento: {
    titulo: 'Animaciones',
    descripcion: 'Paneles, menús y cambios de pantalla. Sin ellas todo aparece al instante.',
    opciones: [
      { valor: 1, etiqueta: 'Normales' },
      { valor: 0.5, etiqueta: 'Rápidas' },
      { valor: 0, etiqueta: 'Ninguna' },
    ],
    fabrica: 1,
    variable: factor('--apariencia-movimiento'),
  },
}

/** Secciones del editor, en orden. */
export const GRUPOS: readonly { titulo: string; claves: readonly ClaveAjuste[] }[] = [
  { titulo: 'Color', claves: ['modo', 'primario', 'superficie', 'contraste', 'fondo'] },
  { titulo: 'Forma', claves: ['estilo', 'esquinas', 'densidad', 'sombras'] },
  { titulo: 'Texto', claves: ['texto', 'fuente'] },
  { titulo: 'Movimiento', claves: ['movimiento'] },
]

export const CLAVES = Object.keys(AJUSTES) as ClaveAjuste[]

/** Los que entran en el preset de PrimeVue: cambiarlos lo regenera. */
export const CLAVES_DEL_PRESET = CLAVES.filter((c) => !AJUSTES[c].variable)

/** La apariencia de fábrica, con el modo dado (el de fábrica sale del sistema operativo). */
export function deFabrica(modo: Modo = 'light'): Ajustes {
  const out = Object.fromEntries(CLAVES.map((c) => [c, AJUSTES[c].fabrica])) as unknown as Ajustes
  return { ...out, modo }
}

export const esOpcion = <K extends ClaveAjuste>(clave: K, valor: unknown): valor is Ajustes[K] =>
  AJUSTES[clave].opciones.some((o) => o.valor === valor)

/**
 * Lo guardado (en el navegador o en la cuenta) como `Ajustes`: de cada clave
 * se toma lo que sea una opción vigente; lo que falte o ya no exista, de `base`.
 */
export function normalizar(entrada: unknown, base: Ajustes): Ajustes {
  const datos = entrada && typeof entrada === 'object' ? (entrada as Record<string, unknown>) : {}
  const out = { ...base } as Record<ClaveAjuste, unknown>
  for (const clave of CLAVES) if (esOpcion(clave, datos[clave])) out[clave] = datos[clave]
  return out as unknown as Ajustes
}

/** Lo que guardaba la store `ui` antes de este catálogo (`mode`, `primary`, `surface`, `preset`). */
export function desdeAnterior(anterior: unknown): Partial<Ajustes> {
  if (!anterior || typeof anterior !== 'object') return {}
  const { mode, primary, surface, preset } = anterior as Record<string, unknown>
  return { modo: mode, primario: primary, superficie: surface, estilo: preset } as Partial<Ajustes>
}

export const iguales = (a: Ajustes, b: Ajustes): boolean => CLAVES.every((c) => a[c] === b[c])

/**
 * Los parámetros CSS de `ajustes`: nombre → valor, o `null` si es el de
 * fábrica (sin él en <html> rige el de `apariencia.css`).
 */
export function variablesCss(ajustes: Ajustes): Record<string, string | null> {
  const out: Record<string, string | null> = {}
  for (const clave of CLAVES) {
    const definicion = AJUSTES[clave] as DefinicionAjuste<unknown>
    if (!definicion.variable) continue
    out[definicion.variable.nombre] = ajustes[clave] === definicion.fabrica ? null : definicion.variable.css(ajustes[clave])
  }
  return out
}

/** El valor CSS de fábrica de cada parámetro (para comparar con `apariencia.css`). */
export function variablesDeFabrica(): Record<string, string> {
  const out: Record<string, string> = {}
  for (const clave of CLAVES) {
    const definicion = AJUSTES[clave] as DefinicionAjuste<unknown>
    if (definicion.variable) out[definicion.variable.nombre] = definicion.variable.css(definicion.fabrica)
  }
  return out
}

/** Etiqueta de la opción elegida de `clave`. */
export const etiquetaDe = <K extends ClaveAjuste>(clave: K, valor: Ajustes[K]): string =>
  AJUSTES[clave].opciones.find((o) => o.valor === valor)?.etiqueta ?? String(valor)
