/**
 * Filtro a la medida del listado de tarifas (`BoletoTarifa`): trayecto por
 * sus enclaves de origen y destino, empresa, clase de bus, bus, usuario y
 * horario. Todos los términos se cumplen a la vez. Traduce a los argumentos
 * de la colección: `ColumnaFilter` (empresa, busClase, bus, usuario) y
 * `BoletoTarifaFilter` (trayectoOrigen/Destino, horarioDesde/Hasta) del backend.
 */
import type { CustomFilterChip, CustomListFilterState } from '@/core/entities/listCustomFilter'

/** Una opción elegida: IRI y etiqueta (para volver a mostrarla sin pedirla). */
export interface OpcionFiltro {
  value: string
  label: string
}

export interface FiltroTarifa {
  origen: OpcionFiltro | null
  destino: OpcionFiltro | null
  empresa: OpcionFiltro | null
  busClase: OpcionFiltro | null
  bus: OpcionFiltro | null
  usuario: OpcionFiltro | null
  /** `HH:mm`. */
  horaDesde: string | null
  horaHasta: string | null
}

export const FILTRO_TARIFA_VACIO: Readonly<FiltroTarifa> = {
  origen: null,
  destino: null,
  empresa: null,
  busClase: null,
  bus: null,
  usuario: null,
  horaDesde: null,
  horaHasta: null,
}

const RELACIONES = ['origen', 'destino', 'empresa', 'busClase', 'bus', 'usuario'] as const

/** Argumento de la colección de cada término. */
const ARGUMENTO: Record<keyof FiltroTarifa, string> = {
  origen: 'trayectoOrigen',
  destino: 'trayectoDestino',
  empresa: 'empresa',
  busClase: 'busClase',
  bus: 'bus',
  usuario: 'usuario',
  horaDesde: 'horarioDesde',
  horaHasta: 'horarioHasta',
}

const ETIQUETA: Record<'empresa' | 'busClase' | 'bus' | 'usuario', string> = {
  empresa: 'Empresa',
  busClase: 'Clase de bus',
  bus: 'Bus',
  usuario: 'Usuario',
}

const HORA = /^([01]\d|2[0-3]):[0-5]\d$/

function opcion(valor: unknown): OpcionFiltro | null {
  if (!valor || typeof valor !== 'object') return null
  const { value, label } = valor as Record<string, unknown>
  return typeof value === 'string' && value !== '' ? { value, label: typeof label === 'string' ? label : value } : null
}

function hora(valor: unknown): string | null {
  if (typeof valor !== 'string') return null
  const hhmm = valor.slice(0, 5)
  return HORA.test(hhmm) ? hhmm : null
}

/** Normaliza lo guardado (o null) a un filtro válido; descarta lo que no sirve. */
export function filtroTarifaDe(values: Record<string, unknown> | null | undefined): FiltroTarifa {
  const filtro: FiltroTarifa = { ...FILTRO_TARIFA_VACIO }
  if (!values) return filtro
  for (const campo of RELACIONES) filtro[campo] = opcion(values[campo])
  filtro.horaDesde = hora(values.horaDesde)
  filtro.horaHasta = hora(values.horaHasta)
  return filtro
}

export function argsFiltroTarifa(filtro: FiltroTarifa): Record<string, unknown> {
  const args: Record<string, unknown> = {}
  for (const campo of RELACIONES) {
    const elegido = filtro[campo]
    if (elegido) args[ARGUMENTO[campo]] = elegido.value
  }
  if (filtro.horaDesde) args[ARGUMENTO.horaDesde] = filtro.horaDesde
  if (filtro.horaHasta) args[ARGUMENTO.horaHasta] = filtro.horaHasta
  return args
}

export function chipsFiltroTarifa(filtro: FiltroTarifa): CustomFilterChip[] {
  const chips: CustomFilterChip[] = []
  const { origen, destino, horaDesde, horaHasta } = filtro
  if (origen && destino) chips.push({ label: 'Trayecto', value: `${origen.label} ➔ ${destino.label}` })
  else if (origen) chips.push({ label: 'Origen', value: origen.label })
  else if (destino) chips.push({ label: 'Destino', value: destino.label })
  for (const campo of ['empresa', 'busClase', 'bus', 'usuario'] as const) {
    const elegido = filtro[campo]
    if (elegido) chips.push({ label: ETIQUETA[campo], value: elegido.label })
  }
  if (horaDesde && horaHasta && horaDesde !== horaHasta) chips.push({ label: 'Horario', value: `${horaDesde}–${horaHasta}` })
  else if (horaDesde || horaHasta) chips.push({ label: 'Hora', value: (horaDesde ?? horaHasta)! })
  return chips
}

/** Estado para `ListPage` (contrato `listCustomFilter`). */
export function estadoFiltroTarifa(filtro: FiltroTarifa): CustomListFilterState {
  return { values: { ...filtro }, args: argsFiltroTarifa(filtro), chips: chipsFiltroTarifa(filtro) }
}
