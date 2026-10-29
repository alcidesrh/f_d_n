/** Accesos (ADR-039): lo que puede hacer el usuario con sesión y la administración de roles y asignaciones. */

/** Sobre qué rige una asignación: `null` = todas (también las que se den de alta después). */
export interface Ambito {
  empresas: number[] | null
  estaciones: number[] | null
  agencia: number | null
}

export interface PermisoRecurso {
  ver: boolean
  editar: boolean
  eliminar: boolean
}

export interface OpcionEstacion {
  id: number
  nombre: string
  departamento: string | null
}

/** `GET /api/me/acceso`. */
export interface MiAcceso {
  usuario: { id: number; username: string }
  sistema: boolean
  /** Código de capacidad → ámbitos donde la tiene. */
  capacidades: Record<string, Ambito[]>
  /** Entidad de la API genérica (`BoletoTarifa`) → qué puede hacer con ella. */
  recursos: Record<string, PermisoRecurso>
  agencia: { id: number; nombre: string } | null
  estacionTrabajo: OpcionEstacion | null
  /** Las estaciones de trabajo que puede elegir. */
  estaciones: OpcionEstacion[]
  /** Cambia con cualquier cambio de lo que puede (para refrescar sin recargar). */
  huella: string
}

/** Una capacidad, o cualquiera de varias. */
export type Exige = string | readonly string[]

// ---- Administración ------------------------------------------------------

export interface DefinicionCapacidad {
  codigo: string
  nombre: string
  area: string
  dimensiones: Array<'empresa' | 'estacion'>
  limites: string[]
  /** Entidad del CRUD genérico que la usa, si es de ahí. */
  recurso: string | null
  operacion: 'ver' | 'editar' | 'eliminar' | null
}

export interface DefinicionLimite {
  codigo: string
  nombre: string
  capacidad: string
}

export interface OpcionLugar {
  id: number
  nombre: string
  departamento?: string | null
  activo?: boolean
}

export interface CatalogoAcceso {
  capacidades: DefinicionCapacidad[]
  limites: DefinicionLimite[]
  empresas: OpcionLugar[]
  estaciones: OpcionLugar[]
  puede: { roles: boolean; asignar: boolean }
}

export interface Rol {
  id: number
  nombre: string
  descripcion: string | null
  sistema: boolean
  base: boolean
  capacidades: string[]
  limites: Record<string, number>
  /** Usuarios con una asignación vigente del rol. */
  usuarios: number
}

export interface RolPayload {
  nombre: string
  descripcion: string | null
  capacidades: string[]
  limites: Record<string, number | null>
}

export interface Asignacion {
  id: number
  rol: { id: number; nombre: string; descripcion: string | null; sistema: boolean }
  /** De un usuario de agencia: el alcance es su agencia. */
  agencia: string | null
  empresas: OpcionLugar[] | null
  estaciones: OpcionLugar[] | null
  desde: string | null
  hasta: string | null
  motivo: string | null
  otorgadaPor: string | null
  creada: string
  modificada: string
  vigente: boolean
  puedeEditar: boolean
}

export interface AsignacionPayload {
  rol: number
  empresas: number[] | null
  estaciones: number[] | null
  desde: string | null
  hasta: string | null
  motivo: string | null
}

export interface CapacidadEfectiva {
  codigo: string
  nombre: string
  area: string
  ambitos: Ambito[]
  roles: string[]
}

/** `GET /api/acceso/usuarios/{id}`. */
export interface AccesosUsuario {
  usuario: {
    id: number
    username: string
    nombre: string
    activo: boolean
    agencia: { id: number; nombre: string } | null
    estacion: { id: number; nombre: string } | null
  }
  asignaciones: Asignacion[]
  efectivo: CapacidadEfectiva[]
  puede: { asignar: boolean; activar: boolean }
}

export interface FilaQuienPuede {
  usuario: { id: number; username: string; nombre: string; agencia: string | null }
  rol: string
  asignacion: number
  ambito: Ambito
}

export interface FilaSinAlcance {
  id: number
  username: string
  nombre: string
  agencia: string | null
  estacion: string | null
  asignaciones: number
}
