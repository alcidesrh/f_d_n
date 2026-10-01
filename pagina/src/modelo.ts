/**
 * Reglas puras de la página (sin Vue ni red, con tests): tarjeta, tiempo
 * restante, estado del mapa, selección de asientos, agrupación por
 * departamento, fechas e importes.
 */
import type { AsientoCroquis, ClaseAsiento, EstadoAsiento } from '@/core/croquis/types'
import type { Estacion, SalidaPublico } from './tipos'

export function luhn(numero: string): boolean {
  const d = numero.replace(/\D+/g, '')
  if (d.length < 13 || d.length > 19) return false
  let suma = 0
  let doble = false
  for (let i = d.length - 1; i >= 0; i--) {
    let n = Number(d[i])
    if (doble) {
      n *= 2
      if (n > 9) n -= 9
    }
    suma += n
    doble = !doble
  }
  return suma % 10 === 0
}

/** `visa`, `mastercard` o null (las únicas que se aceptan). */
export function marca(numero: string): 'visa' | 'mastercard' | null {
  const d = numero.replace(/\D+/g, '')
  if (d.startsWith('4')) return 'visa'
  if (/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/.test(d)) return 'mastercard'
  return null
}

/** `MM/AA` vigente respecto de `hoy`. */
export function expiraValida(valor: string, hoy = new Date()): boolean {
  const m = /^(\d{2})\/(\d{2})$/.exec(valor.trim())
  if (!m) return false
  const mes = Number(m[1])
  const anio = 2000 + Number(m[2])
  if (mes < 1 || mes > 12) return false
  return anio * 100 + mes >= hoy.getFullYear() * 100 + hoy.getMonth() + 1
}

/** `mm:ss` que faltan hasta `iso` (0 si ya pasó). */
export function restante(iso: string | null, ahora = Date.now()): { segundos: number; texto: string } {
  const s = iso ? Math.max(0, Math.floor((new Date(iso).getTime() - ahora) / 1000)) : 0
  const dos = (n: number) => String(n).padStart(2, '0')
  return { segundos: s, texto: `${dos(Math.floor(s / 60))}:${dos(s % 60)}` }
}

/**
 * Cómo se pinta cada asiento en la página (sin distinguir canales): lo que
 * el cliente eligió en esta pestaña es `seleccionado`; vendido o apartado
 * por otro, `ocupado`. Lo apartado por el propio carrito (`propio`) cuenta
 * como libre: se está editando la elección.
 */
export function estadoEnMapa(
  ocupacion: SalidaPublico['ocupacion'],
  elegidos: ReadonlySet<number> = new Set(),
  /** Clases con precio en línea; las demás no se venden en la web. */
  vendibles?: ReadonlySet<ClaseAsiento>,
): (asiento: AsientoCroquis) => EstadoAsiento {
  const porId = new Map(ocupacion.map((o) => [o.asiento, o.estado]))
  return (a) => {
    const id = a.id ?? -1
    const e = porId.get(id)
    if (e === 'vendido' || e === 'reservado') return 'ocupado'
    if (elegidos.has(id)) return 'seleccionado'
    if (vendibles && !vendibles.has(a.clase)) return 'bloqueado'
    return 'disponible'
  }
}

/** Asiento elegido en el croquis, con el precio de su clase en la web. */
export interface AsientoElegido {
  id: number
  numero: number
  clase: ClaseAsiento
  centavos: number
}

/**
 * Elegidos que otro ocupó mientras el cliente miraba el croquis (aviso de
 * Mercure o recarga): hay que quitarlos de la selección.
 */
export function tomadosPorOtros(elegidos: readonly AsientoElegido[], ocupacion: SalidaPublico['ocupacion']): AsientoElegido[] {
  const ocupados = new Set(ocupacion.filter((o) => o.estado === 'vendido' || o.estado === 'reservado').map((o) => o.asiento))
  return elegidos.filter((a) => ocupados.has(a.id))
}

/** Agrega o quita un asiento de la elección, sin pasar de `maximo`. */
export function alternarAsiento(
  elegidos: readonly AsientoElegido[],
  asiento: AsientoElegido,
  maximo: number,
): { elegidos: AsientoElegido[]; lleno: boolean } {
  if (elegidos.some((a) => a.id === asiento.id)) return { elegidos: elegidos.filter((a) => a.id !== asiento.id), lleno: false }
  if (elegidos.length >= maximo) return { elegidos: [...elegidos], lleno: true }
  return { elegidos: [...elegidos, asiento].sort((a, b) => a.numero - b.numero), lleno: false }
}

export const sumaCentavos = (asientos: readonly { centavos: number }[]) => asientos.reduce((t, a) => t + a.centavos, 0)

/**
 * Orígenes o destinos agrupados por departamento (como en la página
 * anterior), los grupos y las estaciones por nombre; sin departamento, en
 * `otros` al final.
 */
export function porDepartamento<E extends Estacion>(estaciones: readonly E[], otros: string, idioma = 'es'): Array<{ departamento: string; estaciones: E[] }> {
  const grupos = new Map<string, E[]>()
  for (const e of estaciones) {
    const d = e.departamento?.trim() || ''
    grupos.set(d, [...(grupos.get(d) ?? []), e])
  }
  const orden = (a: string, b: string) => a.localeCompare(b, idioma, { sensitivity: 'base' })
  return [...grupos.entries()]
    .sort(([a], [b]) => (a === '' ? 1 : b === '' ? -1 : orden(a, b)))
    .map(([d, lista]) => ({ departamento: d || otros, estaciones: [...lista].sort((x, y) => orden(x.nombre, y.nombre)) }))
}

const ZONA = 'America/Guatemala'

export const hora = (iso: string | null | undefined, region = 'es-GT') =>
  iso
    ? new Intl.DateTimeFormat(region, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: ZONA,
      }).format(new Date(iso))
    : '—'

export const fechaLarga = (iso: string | null | undefined, region = 'es-GT') =>
  iso
    ? new Intl.DateTimeFormat(region, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: ZONA,
      }).format(new Date(iso))
    : '—'

export const fechaCorta = (iso: string | null | undefined, region = 'es-GT') =>
  iso
    ? new Intl.DateTimeFormat(region, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        timeZone: ZONA,
      }).format(new Date(iso))
    : '—'

/** Día `AAAA-MM-DD` (sin hora) como texto largo, sin corrimiento de zona. */
export function diaLargo(dia: string | null | undefined, region = 'es-GT'): string {
  const d = dia ? desdeISO(dia) : null
  return d ? new Intl.DateTimeFormat(region, { weekday: 'long', day: 'numeric', month: 'long' }).format(d) : '—'
}

/** Minutos entre dos fechas ISO como `5 h 30 min` (null si falta alguna). */
export function duracion(desde: string | null | undefined, hasta: string | null | undefined): string | null {
  if (!desde || !hasta) return null
  const min = Math.round((Date.parse(hasta) - Date.parse(desde)) / 60000)
  if (!(min > 0)) return null
  const h = Math.floor(min / 60)
  const m = min % 60
  return h > 0 ? `${h} h${m ? ` ${m} min` : ''}` : `${m} min`
}

/** Centavos de quetzal con el formato de la región (`Q 1,199.00`, `1.199,00 GTQ`…). */
export function quetzales(centavos: number, region = 'es-GT'): string {
  return new Intl.NumberFormat(region, { style: 'currency', currency: 'GTQ', currencyDisplay: 'symbol' }).format(centavos / 100)
}

/** `AAAA-MM-DD` desplazado `dias`. */
export function sumarDias(dia: string, dias: number): string {
  const d = desdeISO(dia) ?? new Date()
  d.setDate(d.getDate() + dias)
  return diaISO(d)
}

/** Porcentaje ocupado de una salida (0–100). */
export const porcentajeOcupado = (s: { capacidad: number; ocupados: number }) =>
  s.capacidad > 0 ? Math.min(100, Math.round((s.ocupados / s.capacidad) * 100)) : 0

/** Fecha local `AAAA-MM-DD`. */
export function diaISO(d: Date): string {
  const dos = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`
}

/** `AAAA-MM-DD` → `Date` local (sin corrimiento por zona horaria). */
export function desdeISO(dia: string): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dia)
  return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null
}

/** Países (ISO 3166-1 alfa-2) para la dirección de facturación de la tarjeta. */
const CODIGOS_PAIS =
  'AD AE AF AG AI AL AM AO AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BW BY BZ CA CD CF CG CH CI CK CL CM CN CO CR CU CV CW CY CZ DE DJ DK DM DO DZ EC EE EG ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GT GU GW GY HK HN HR HT HU ID IE IL IM IN IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW'.split(' ')

/** Opciones de país con Guatemala primero y el resto por nombre. */
export function paises(idioma = 'es'): Array<{ label: string; value: string }> {
  let nombre: (c: string) => string = (c) => c
  try {
    const nombres = new Intl.DisplayNames([idioma], { type: 'region' })
    nombre = (c) => nombres.of(c) ?? c
  } catch {
    /* navegador sin Intl.DisplayNames: se muestran los códigos */
  }
  const todas = CODIGOS_PAIS.map((c) => ({ label: nombre(c), value: c }))
  const gt = todas.filter((p) => p.value === 'GT')
  return [...gt, ...todas.filter((p) => p.value !== 'GT').sort((a, b) => a.label.localeCompare(b.label, idioma))]
}

/** En EE. UU. y Canadá el banco valida el estado (2 letras) y el código postal. */
export const exigeCodigoPostal = (pais: unknown) => pais === 'US' || pais === 'CA'

/** El aviso (`postMessage`) viene de alguno de los orígenes esperados. */
export const deOrigen = (origen: string, origenes: readonly string[]) => origenes.includes(origen)

/** Aviso de `/api/publico/pagos/retorno` al terminar el desafío 3-D Secure. */
export function esRetorno3ds(dato: unknown): dato is { tipo: 'fdn-3ds'; datos: Record<string, string> } {
  if (typeof dato !== 'object' || dato === null) return false
  const d = dato as { tipo?: unknown; datos?: unknown }
  return d.tipo === 'fdn-3ds' && typeof d.datos === 'object' && d.datos !== null
}
