/**
 * Mensajes predeterminados del chat (ADR-045): instancias con nombre,
 * audiencia (usuarios y grupos) y contenido que salen solas a los avisos del
 * sistema. REST `/api/chat/predeterminados` (`chat.predeterminados`) y reglas
 * puras del formulario (con tests).
 */
import { http, request } from '@/core/http'
import type { Archivo } from './types'

const BASE = '/chat/predeterminados'

export const CAPACIDAD = 'chat.predeterminados'

export type CodigoTipo = 'libre' | 'salida_iniciada' | 'anomalia_reasignacion' | 'anomalia_tarifa' | 'ventas_dia' | 'desempeno_dia'

export interface TipoMensaje {
  codigo: CodigoTipo
  nombre: string
  descripcion: string
  exigeEmpresa: boolean
  exigeRuta: boolean
  conHora: boolean
  conDias: boolean
  exigeContenido: boolean
  esAnomalia: boolean
}

export interface Opcion {
  id: number
  nombre: string
}

export interface OpcionesPredeterminados {
  tipos: TipoMensaje[]
  horaCierre: string
  usuarios: { id: number; label: string; usuario: string }[]
  grupos: Opcion[]
  empresas: Opcion[]
  rutas: Opcion[]
}

export interface MensajePredeterminado {
  id: number
  nombre: string
  tipo: CodigoTipo
  tipoNombre: string
  activo: boolean
  empresa: Opcion | null
  ruta: Opcion | null
  hora: string | null
  dias: number[]
  texto: string | null
  archivos: Archivo[]
  usuarios: { id: number; label: string }[]
  grupos: Opcion[]
  destinatarios: number
  ultimoEnvio: string | null
  envios: number
  creadoPor: string | null
  actualizadoEn: string
}

/** Lo que edita el formulario. */
export interface Borrador {
  nombre: string
  activo: boolean
  tipo: CodigoTipo
  empresa: number | null
  ruta: number | null
  hora: string
  dias: number[]
  texto: string
  archivos: Archivo[]
  usuarios: number[]
  grupos: number[]
}

export const fetchPredeterminados = () => http.get<{ mensajes: MensajePredeterminado[]; opciones: OpcionesPredeterminados }>(BASE)
export const crearPredeterminado = (b: Borrador) => http.post<MensajePredeterminado>(BASE, carga(b))
export const editarPredeterminado = (id: number, b: Borrador) => request<MensajePredeterminado>(`${BASE}/${id}`, { method: 'PUT', body: carga(b) })
export const activarPredeterminado = (id: number, activo: boolean) => http.post<MensajePredeterminado>(`${BASE}/${id}/activo`, { activo })
export const eliminarPredeterminado = (id: number) => http.delete(`${BASE}/${id}`)
export const probarPredeterminado = (id: number) => http.post<{ enviado: boolean }>(`${BASE}/${id}/probar`, {})

export const DIAS = [
  { valor: 1, corto: 'Lun', nombre: 'lunes' },
  { valor: 2, corto: 'Mar', nombre: 'martes' },
  { valor: 3, corto: 'Mié', nombre: 'miércoles' },
  { valor: 4, corto: 'Jue', nombre: 'jueves' },
  { valor: 5, corto: 'Vie', nombre: 'viernes' },
  { valor: 6, corto: 'Sáb', nombre: 'sábado' },
  { valor: 7, corto: 'Dom', nombre: 'domingo' },
] as const

export function borradorDe(m: MensajePredeterminado | null): Borrador {
  return {
    nombre: m?.nombre ?? '',
    activo: m?.activo ?? true,
    tipo: m?.tipo ?? 'libre',
    empresa: m?.empresa?.id ?? null,
    ruta: m?.ruta?.id ?? null,
    hora: m?.hora ?? '',
    dias: [...(m?.dias ?? [])],
    texto: m?.texto ?? '',
    archivos: [...(m?.archivos ?? [])],
    usuarios: m?.usuarios.map((u) => u.id) ?? [],
    grupos: m?.grupos.map((g) => g.id) ?? [],
  }
}

/** El cuerpo de crear y editar: solo lo que el tipo usa. */
export function carga(b: Borrador) {
  return {
    nombre: b.nombre.trim(),
    activo: b.activo,
    tipo: b.tipo,
    empresa: b.empresa,
    ruta: b.ruta,
    hora: b.hora || null,
    dias: b.dias,
    texto: b.texto.trim(),
    archivos: b.archivos.map((a) => a.id),
    usuarios: b.usuarios,
    grupos: b.grupos,
  }
}

/**
 * Lo que falta para guardar, en el orden del formulario (nombre, audiencia,
 * contenido). Vacío: se puede guardar; el backend vuelve a validar.
 */
export function faltantes(b: Borrador, tipo: TipoMensaje | undefined): string[] {
  const r: string[] = []
  if (!b.nombre.trim()) r.push('Póngale un nombre.')
  if (!b.usuarios.length && !b.grupos.length) r.push('Elija a quién va dirigido.')
  if (!tipo) return [...r, 'Elija el predeterminado.']
  if (tipo.exigeEmpresa && b.empresa === null) r.push('Elija la empresa.')
  if (tipo.exigeRuta && b.ruta === null) r.push('Elija la ruta.')
  if (tipo.codigo === 'libre' && !b.hora) r.push('Elija la hora a la que sale.')
  if (tipo.exigeContenido && !b.texto.trim() && !b.archivos.length) r.push('Escriba un texto o adjunte una imagen o un archivo.')
  return r
}

/** «Lun a vie», «Lun, mié y vie», «Todos los días». */
export function textoDias(dias: number[]): string {
  const orden = [...new Set(dias)].sort((a, b) => a - b)
  if (!orden.length || orden.length === 7) return 'Todos los días'
  const corto = (d: number) => DIAS.find((x) => x.valor === d)?.corto ?? String(d)
  const seguidos = orden.length >= 3 && orden.every((d, i) => i === 0 || d === orden[i - 1]! + 1)
  if (seguidos) return `${corto(orden[0]!)} a ${corto(orden[orden.length - 1]!).toLowerCase()}`
  const nombres = orden.map((d, i) => (i === 0 ? corto(d) : corto(d).toLowerCase()))
  return nombres.length === 1 ? nombres[0]! : `${nombres.slice(0, -1).join(', ')} y ${nombres[nombres.length - 1]}`
}

/** Cuándo sale, en una línea. */
export function cuandoSale(m: Pick<MensajePredeterminado, 'tipo' | 'hora' | 'dias' | 'ruta'>, horaCierre: string): string {
  switch (m.tipo) {
    case 'libre':
      return `${textoDias(m.dias)} a las ${m.hora ?? '—'}`
    case 'salida_iniciada':
      return `Al pasar la hora de cada salida${m.ruta ? ` de ${m.ruta.nombre}` : ''}`
    case 'anomalia_reasignacion':
    case 'anomalia_tarifa':
      return 'Cada madrugada, si encuentra alguna'
    default:
      return `Todos los días a las ${m.hora ?? horaCierre}`
  }
}

/** «3 usuarios y 1 grupo · 12 destinatarios». */
export function textoAudiencia(m: Pick<MensajePredeterminado, 'usuarios' | 'grupos' | 'destinatarios'>): string {
  const partes = [
    m.usuarios.length ? `${m.usuarios.length} ${m.usuarios.length === 1 ? 'usuario' : 'usuarios'}` : null,
    m.grupos.length ? `${m.grupos.length} ${m.grupos.length === 1 ? 'grupo' : 'grupos'}` : null,
  ].filter(Boolean)
  return `${partes.join(' y ') || 'Nadie'} · ${m.destinatarios} ${m.destinatarios === 1 ? 'destinatario' : 'destinatarios'}`
}
