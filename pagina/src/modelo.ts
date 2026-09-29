/** Reglas puras de la página: tarjeta, tiempo restante, estado del mapa. */
import type { AsientoCroquis, EstadoAsiento } from '@/core/croquis/types'
import type { RecorridoPublico } from './tipos'

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

/** Cómo se pinta cada asiento en la página (sin distinguir canales). */
export function estadoEnMapa(
  ocupacion: RecorridoPublico['ocupacion'],
): (asiento: AsientoCroquis) => EstadoAsiento {
  const porId = new Map(ocupacion.map((o) => [o.asiento, o.estado]))
  return (a) => {
    const e = porId.get(a.id ?? -1)
    if (e === 'propio') return 'seleccionado'
    if (e === 'vendido' || e === 'reservado') return 'ocupado'
    return 'disponible'
  }
}

const ZONA = 'America/Guatemala'

export const hora = (iso: string | null | undefined) =>
  iso
    ? new Intl.DateTimeFormat('es-GT', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
        timeZone: ZONA,
      }).format(new Date(iso))
    : '—'

export const fechaLarga = (iso: string | null | undefined) =>
  iso
    ? new Intl.DateTimeFormat('es-GT', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: ZONA,
      }).format(new Date(iso))
    : '—'

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
