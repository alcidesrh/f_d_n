/** Formato y escalas del tablero de gestión. Puro (con tests). */

/** Mismos umbrales que `App\Tablero\Tablero` (señal de rutas). */
export const OCUPACION_BAJA = 0.35
export const OCUPACION_SATURADA = 0.85

export const DIAS_SEMANA = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'] as const

const entero = new Intl.NumberFormat('es-GT', { maximumFractionDigits: 0 })
const conDecimales = new Intl.NumberFormat('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/** `Q 1,234.50`; compacto: `Q 1.2 M`, `Q 845 mil`, `Q 980`. */
export function dinero(centavos: number | null | undefined, compacto = false): string {
  if (centavos === null || centavos === undefined) return '—'
  const q = centavos / 100
  if (!compacto) return `Q ${conDecimales.format(q)}`
  const abs = Math.abs(q)
  if (abs >= 1_000_000) return `Q ${decimal(q / 1_000_000)} M`
  if (abs >= 10_000) return `Q ${entero.format(Math.round(q / 1000))} mil`
  return `Q ${entero.format(Math.round(q))}`
}

export const numero = (n: number | null | undefined) => (n === null || n === undefined ? '—' : entero.format(n))

/** 0.4065 → `41%`; con decimales, `40.7%`. */
export function porciento(f: number | null | undefined, decimales = 0): string {
  if (f === null || f === undefined) return '—'
  return `${(f * 100).toFixed(decimales)}%`
}

function decimal(n: number): string {
  const r = Math.round(n * 10) / 10
  return Number.isInteger(r) ? String(r) : r.toFixed(1)
}

export interface Variacion {
  texto: string
  tendencia: 'sube' | 'baja' | 'igual'
  /** Si el cambio es bueno para el negocio (con `inverso`, bajar es bueno). */
  valoracion: 'buena' | 'mala' | 'neutra'
}

/**
 * Cambio contra el periodo anterior. Las tasas (`puntos`) se comparan en
 * puntos porcentuales: pasar de 2% a 3% de anulación es +1 pp, no +50%.
 * Sin base de comparación devuelve null (no un "+∞%").
 */
export function variacion(
  actual: number | null | undefined,
  anterior: number | null | undefined,
  { inverso = false, puntos = false }: { inverso?: boolean; puntos?: boolean } = {},
): Variacion | null {
  if (actual === null || actual === undefined || anterior === null || anterior === undefined) return null
  let delta: number
  let texto: string
  if (puntos) {
    delta = (actual - anterior) * 100
    texto = `${delta > 0 ? '+' : delta < 0 ? '−' : ''}${decimal(Math.abs(delta))} pp`
  } else {
    if (anterior === 0) return null
    delta = ((actual - anterior) / Math.abs(anterior)) * 100
    // Desde 10 veces lo anterior el porcentaje deja de leerse ("+40527%"): se dice cuántas veces.
    texto =
      anterior > 0 && actual / anterior >= 10
        ? `×${entero.format(Math.round(actual / anterior))}`
        : `${delta > 0 ? '+' : delta < 0 ? '−' : ''}${Math.abs(delta) >= 10 ? Math.round(Math.abs(delta)) : decimal(Math.abs(delta))}%`
  }
  const umbral = puntos ? 0.05 : 0.5
  const tendencia = Math.abs(delta) < umbral ? 'igual' : delta > 0 ? 'sube' : 'baja'
  const valoracion = tendencia === 'igual' ? 'neutra' : (tendencia === 'sube') !== inverso ? 'buena' : 'mala'
  return { texto: tendencia === 'igual' ? (puntos ? '0 pp' : '0%') : texto, tendencia, valoracion }
}

/** Máximo "redondo" y marcas del eje para valores de 0 a `max` (1, 2, 2.5 o 5 × 10ⁿ). */
export function escala(max: number, marcas = 4): { max: number; ticks: number[] } {
  if (!(max > 0)) return { max: 1, ticks: [0, 1] }
  const crudo = max / marcas
  const potencia = 10 ** Math.floor(Math.log10(crudo))
  const paso = ([1, 2, 2.5, 5, 10].find((m) => m * potencia >= crudo) ?? 10) * potencia
  const tope = Math.ceil(max / paso) * paso
  const ticks: number[] = []
  for (let v = 0; v <= tope + paso / 2; v += paso) ticks.push(Math.round(v * 1e6) / 1e6)
  return { max: tope, ticks }
}

/** Fecha local `AAAA-MM-DD[ HH:mm]` sin pasar por UTC. */
export function fechaLocal(t: string): Date {
  const [d, h = '00:00'] = t.replace('T', ' ').split(' ')
  const [y, m, dd] = d!.split('-').map(Number)
  const [hh, mm] = h.split(':').map(Number)
  return new Date(y!, m! - 1, dd!, hh ?? 0, mm ?? 0)
}

/** Día de la semana de 0 = lunes. */
export const diaSemana = (f: Date) => (f.getDay() + 6) % 7

/** Etiqueta corta de un cubo de la serie: `14 h` (por hora) o `lun 12`. */
export function etiquetaCubo(t: string, porHora: boolean): string {
  const f = fechaLocal(t)
  return porHora ? `${f.getHours()} h` : `${DIAS_SEMANA[diaSemana(f)]} ${f.getDate()}`
}

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']

/** `12 oct` / `12 oct 14:00`. */
export function fechaCorta(t: string, conHora = false): string {
  const f = fechaLocal(t)
  const base = `${f.getDate()} ${MESES[f.getMonth()]}`
  return conHora ? `${base} ${hora(t)}` : base
}

export function hora(t: string): string {
  const f = fechaLocal(t)
  return `${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`
}

/** Rango del periodo para el subtítulo: `6 – 12 oct` o `14 sep – 12 oct`. */
export function rango(desde: string, hasta: string): string {
  if (desde === hasta) return fechaCorta(desde)
  const a = fechaLocal(desde)
  const b = fechaLocal(hasta)
  return a.getMonth() === b.getMonth() ? `${a.getDate()} – ${fechaCorta(hasta)}` : `${fechaCorta(desde)} – ${fechaCorta(hasta)}`
}

export type NivelOcupacion = 'baja' | 'media' | 'alta'

export function nivelOcupacion(o: number | null | undefined): NivelOcupacion | null {
  if (o === null || o === undefined) return null
  return o >= OCUPACION_SATURADA ? 'alta' : o < OCUPACION_BAJA ? 'baja' : 'media'
}

/** Minutos que faltan (negativo si ya pasó) para una salida, legible: `en 25 min`, `hace 10 min`, `en 2 h 05`. */
export function cuentaRegresiva(fecha: string, ahora: Date): string {
  const min = Math.round((fechaLocal(fecha).getTime() - ahora.getTime()) / 60000)
  const abs = Math.abs(min)
  const t = abs < 60 ? `${abs} min` : `${Math.floor(abs / 60)} h ${String(abs % 60).padStart(2, '0')}`
  if (abs === 0) return 'ahora'
  return min > 0 ? `en ${t}` : `hace ${t}`
}
