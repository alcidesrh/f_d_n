/**
 * Plan del programador de salidas (ADR-024): qué pasa con cada salida de la
 * vista previa y del resultado de crear, agrupado por día, y los textos del
 * resumen. Puro.
 *
 * En la vista previa, «ya existe» es informativo (no se duplica) y solo el
 * bus ocupado o la hora pasada son problemas. En el resultado, las nuevas
 * son las que esta misma operación creó (traen su `id`): no se cuentan como
 * omitidas aunque una vista previa pedida después las vea como existentes.
 */
import { aDia } from './filtro'
import type { EstadoPlan, ItemPlan, ResultadoProgramacion, VistaPrevia } from './types'

export type Severidad = 'success' | 'secondary' | 'info' | 'warn' | 'danger'

/** Cómo se muestra un ítem: en el resultado la nueva es «creada». */
export type EstadoItem = EstadoPlan | 'creada'

export const ESTADO_ITEM: Record<EstadoItem, { etiqueta: string; severidad: Severidad }> = {
  nueva: { etiqueta: 'nueva', severidad: 'success' },
  creada: { etiqueta: 'creada', severidad: 'success' },
  existe: { etiqueta: 'ya existe', severidad: 'secondary' },
  conflicto: { etiqueta: 'bus ocupado', severidad: 'danger' },
  pasada: { etiqueta: 'hora pasada', severidad: 'warn' },
}

/** Orden de las etiquetas del resumen. */
const ORDEN: EstadoPlan[] = ['nueva', 'existe', 'conflicto', 'pasada']

/** Una salida de la vista previa o del resultado. */
export const estadoItem = (it: ItemPlan): EstadoItem => (it.estado === 'nueva' && it.id ? 'creada' : it.estado)

/** No se crea (o no se creó): todo lo que no es nueva. */
export const omitida = (it: ItemPlan) => it.estado !== 'nueva'

/** Impide crearla: bus ocupado u hora pasada (ya existe no es un problema). */
export const problema = (it: ItemPlan) => it.estado === 'conflicto' || it.estado === 'pasada'

export interface DiaPlan {
  /** `AAAA-MM-DD` */
  dia: string
  items: ItemPlan[]
  omitidas: number
  problemas: number
}

/** Ítems por día (local), en el orden en que llegan. */
export function porDia(items: readonly ItemPlan[]): DiaPlan[] {
  const grupos = new Map<string, ItemPlan[]>()
  for (const it of items) {
    const dia = aDia(new Date(it.fecha))
    grupos.set(dia, [...(grupos.get(dia) ?? []), it])
  }
  return [...grupos].map(([dia, del]) => ({ dia, items: del, omitidas: del.filter(omitida).length, problemas: del.filter(problema).length }))
}

/** Etiqueta del día: «2 se omiten» (warn si alguna es un problema), o nada. */
export function etiquetaDia(d: DiaPlan): { texto: string; severidad: Severidad } | null {
  if (!d.omitidas) return null
  return { texto: d.omitidas === 1 ? '1 se omite' : `${d.omitidas} se omiten`, severidad: d.problemas ? 'warn' : 'secondary' }
}

/** Etiquetas del resumen de la vista previa: `3 nuevas`, `1 ya existe`… (solo las que hay). */
export function resumenPrevia(r: VistaPrevia['resumen']): Array<{ estado: EstadoPlan; texto: string; severidad: Severidad }> {
  return ORDEN.filter((e) => r[e] > 0).map((e) => ({
    estado: e,
    texto: `${r[e]} ${e === 'nueva' ? (r[e] === 1 ? 'nueva' : 'nuevas') : ESTADO_ITEM[e].etiqueta}`,
    severidad: ESTADO_ITEM[e].severidad,
  }))
}

const MOTIVO: Record<Exclude<EstadoPlan, 'nueva'>, [string, string]> = {
  existe: ['ya existía', 'ya existían'],
  conflicto: ['tenía el bus ocupado', 'tenían el bus ocupado'],
  pasada: ['ya había pasado', 'ya habían pasado'],
}

const salidas = (n: number) => (n === 1 ? '1 salida' : `${n} salidas`)

/**
 * Lo que dice el aviso tras crear: cuántas se crearon y, de las omitidas,
 * cuántas por cada motivo. Éxito si se creó alguna; si no, informativo
 * cuando todas ya existían y advertencia si hubo bus ocupado u hora pasada.
 */
export function mensajeResultado(r: ResultadoProgramacion): { severidad: 'success' | 'info' | 'warn'; texto: string } {
  const motivos = (['existe', 'conflicto', 'pasada'] as const)
    .map((e) => ({ e, n: r.omitidas.filter((o) => o.estado === e).length }))
    .filter((m) => m.n > 0)
    .map(({ e, n }) => `${n} ${MOTIVO[e][n === 1 ? 0 : 1]}`)
  const omitidas = motivos.length ? ` No se ${r.omitidas.length === 1 ? 'creó 1' : `crearon ${r.omitidas.length}`}: ${unir(motivos)}.` : ''
  if (r.creadas > 0) return { severidad: 'success', texto: `Se ${r.creadas === 1 ? 'creó 1 salida' : `crearon ${salidas(r.creadas)}`}.${omitidas}` }
  return { severidad: r.omitidas.some(problema) ? 'warn' : 'info', texto: `No se creó ninguna salida.${omitidas}` }
}

/** `a`, `a y b`, `a, b y c`. */
const unir = (partes: string[]) => (partes.length < 2 ? (partes[0] ?? '') : `${partes.slice(0, -1).join(', ')} y ${partes[partes.length - 1]}`)
