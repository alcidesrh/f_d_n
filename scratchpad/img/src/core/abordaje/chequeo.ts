/**
 * Reglas puras de la pantalla de chequeo (ADR-032): las marcas SI/NO de cada
 * boleto (excluyentes, como las casillas del legado), lo que se envía, los
 * conteos y qué se puede hacer con la salida.
 */
import type { EstadoSalida } from '@/core/salida/types'
import type { BoletoAbordaje, PuedeAbordaje, SalidaAbordaje } from './types'

/** `si`: se presentó; `no`: no se presentó (queda cancelado y libera el asiento). */
export type Marca = 'si' | 'no'

/** Marca por id de boleto; sin entrada = sin marcar. */
export type Marcas = Readonly<Record<number, Marca>>

export interface Seleccion {
  si: number[]
  no: number[]
}

/** Marcar SI o NO; volver a marcar lo mismo lo desmarca. */
export function alternar(marcas: Marcas, id: number, marca: Marca): Marcas {
  const { [id]: actual, ...resto } = marcas
  return actual === marca ? resto : { ...resto, [id]: marca }
}

/** Todos los boletos que se pueden chequear, con la misma marca (respeta las demás). */
export function marcarTodos(marcas: Marcas, boletos: readonly BoletoAbordaje[], marca: Marca): Marcas {
  const nuevas: Record<number, Marca> = { ...marcas }
  for (const b of boletos) if (b.chequeable) nuevas[b.id] = marca
  return nuevas
}

/** Tras recargar: solo quedan las marcas de boletos que todavía se pueden chequear. */
export function vigentes(marcas: Marcas, boletos: readonly BoletoAbordaje[]): Marcas {
  const ids = new Set(boletos.filter((b) => b.chequeable).map((b) => b.id))
  return Object.fromEntries(Object.entries(marcas).filter(([id]) => ids.has(Number(id)))) as Marcas
}

export function seleccion(marcas: Marcas): Seleccion {
  const s: Seleccion = { si: [], no: [] }
  for (const [id, m] of Object.entries(marcas)) s[m].push(Number(id))
  s.si.sort((a, b) => a - b)
  s.no.sort((a, b) => a - b)
  return s
}

export interface Conteo {
  total: number
  /** Chequeados o ya en tránsito. */
  abordo: number
  /** Emitidos, sin chequear. */
  pendientes: number
  /** Emitidos que suben en el origen: los que se cancelan al iniciar. */
  pendientesOrigen: number
}

export function conteo(boletos: readonly BoletoAbordaje[]): Conteo {
  const pendientes = boletos.filter((b) => b.estado === 'emitido')
  return {
    total: boletos.length,
    abordo: boletos.length - pendientes.length,
    pendientes: pendientes.length,
    pendientesOrigen: pendientes.filter((b) => b.ordenSube === 0).length,
  }
}

export interface Acciones {
  abordar: boolean
  iniciar: boolean
  finalizar: boolean
}

/** Abordar, iniciar y finalizar según el estado de la salida y los permisos (las demás reglas las aplica el backend). */
export function acciones(salida: Pick<SalidaAbordaje, 'estado'>, puede: PuedeAbordaje): Acciones {
  return {
    abordar: puede.abordar && salida.estado === 'programada',
    iniciar: puede.iniciar && salida.estado === 'abordando',
    finalizar: puede.finalizar && salida.estado === 'iniciada',
  }
}

/** Opción del selector: `11/10/2026 18:00 · Ruta · Empresa · Bus 39`. */
export function etiquetaSalida(s: SalidaAbordaje): string {
  const fecha = new Intl.DateTimeFormat('es-GT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(s.fecha))
  return [fecha.replace(',', ''), s.ruta, s.empresa, s.bus ? `Bus ${s.bus}` : null].filter(Boolean).join(' · ')
}

/** Filtro del selector de salidas; vacío = todas. */
export interface FiltroSalidasAbordaje {
  empresas: readonly string[]
  estados: readonly EstadoSalida[]
}

/** Empresas de las salidas, sin repetir y en orden alfabético. */
export function empresasDe(salidas: readonly SalidaAbordaje[]): string[] {
  return [...new Set(salidas.map((s) => s.empresa).filter((e): e is string => !!e))].sort((a, b) => a.localeCompare(b, 'es'))
}

/** Estados presentes en las salidas, en el orden de `orden`. */
export function estadosDe(salidas: readonly SalidaAbordaje[], orden: readonly EstadoSalida[]): EstadoSalida[] {
  const hay = new Set(salidas.map((s) => s.estado))
  return orden.filter((e) => hay.has(e))
}

export function filtrarSalidas(salidas: readonly SalidaAbordaje[], f: FiltroSalidasAbordaje): SalidaAbordaje[] {
  return salidas.filter((s) => (f.empresas.length === 0 || (s.empresa != null && f.empresas.includes(s.empresa))) && (f.estados.length === 0 || f.estados.includes(s.estado)))
}
