/** Borrado de usuarios con sus dependencias (`EliminarUsuariosController`). */
import { http } from '@/core/http'

/** Qué hacer con los registros de una tabla que apuntan al usuario. */
export type AccionDependencia = 'desvincular' | 'eliminar'

export interface RegistroDependiente {
  id: number
  etiqueta: string
}

/** Una tabla con registros que dependen de los usuarios seleccionados. */
export interface GrupoDependencia {
  tabla: string
  columna: string
  /** Nombre corto de la entidad (si la tabla es una entidad). */
  entidad: string | null
  anulable: boolean
  total: number
  acciones: AccionDependencia[]
  /** Muestra de hasta 50 registros. */
  registros: RegistroDependiente[]
}

export interface ResultadoEliminacion {
  usuarios: number
  tablas: Record<string, { accion: AccionDependencia; filas: number }>
}

export const analizarEliminacion = (ids: number[]) =>
  http.post<{ grupos: GrupoDependencia[] }>('/usuarios/eliminacion/analizar', { ids })

export const eliminarUsuarios = (ids: number[], decisiones: Record<string, AccionDependencia>) =>
  http.post<ResultadoEliminacion>('/usuarios/eliminacion', { ids, decisiones })
