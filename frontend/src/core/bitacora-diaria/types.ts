/** Bitácora diaria de los usuarios (`/api/bitacora-diaria`, ADR-051). */
export type TipoActividad = 'sesion' | 'peticion' | 'pantalla' | 'sondeo'
export type NivelActividad = 'minimo' | 'medio' | 'maximo'
export type TipoSeleccion = 'usuario' | 'rol' | 'empresa' | 'estacion'

export interface ResumenUsuario {
  usuarioId: number
  username: string | null
  nombre: string
  empresaId: number | null
  estacionId: number | null
  primera: string | null
  ultima: string | null
  minutosActivos: number
  sesiones: number
  peticiones: number
  escrituras: number
  errores: number
  sondeos: number
  pantallas: number
  cambios: number
  /** `entidad.operacion` → cuántas (de la bitácora de negocio). */
  operaciones: Record<string, number>
}

export interface DiaBitacora {
  dia: string
  /** El resumen ya quedó guardado (día cerrado); si no, se calculó al vuelo. */
  guardado: boolean
  usuarios: ResumenUsuario[]
}

interface EntradaBase {
  fecha: string
  accion: string | null
  entidad: string | null
  registroId: number | null
  detalle: Record<string, unknown> | null
  requestId: string | null
}

export interface EntradaActividad extends EntradaBase {
  origen: 'actividad'
  tipo: TipoActividad
  metodo: string | null
  ruta: string | null
  estado: number | null
  duracionMs: number | null
  ip: string | null
  agente: string | null
  /** Hecho por un administrador del sistema que usaba la cuenta (suplantación). */
  suplantadoPor?: { id: number; username: string | null; nombre: string | null } | null
}

export interface EntradaNegocio extends EntradaBase {
  origen: 'negocio'
  tipo: 'negocio'
  etiqueta: string
}

export type EntradaDiaria = EntradaActividad | EntradaNegocio

export interface DiaDeUsuario {
  dia: string
  usuario: { id: number; username: string | null; nombre: string | null }
  resumen: ResumenUsuario | null
  entradas: EntradaDiaria[]
  /** Hubo más entradas de las que se devuelven. */
  cortada: boolean
}

export interface EntradaSeleccion {
  tipo: TipoSeleccion
  id: number
  desde: string | null
  hasta: string | null
  nombre?: string
}

export interface Opcion {
  id: number
  nombre: string
}

export interface ConfiguracionBitacora {
  activa: boolean
  nivel: NivelActividad
  familiasApagadas: TipoActividad[]
  alcance: 'todos' | 'seleccionados'
  seleccion: EntradaSeleccion[]
  retencionMeses: number
  exportarAntesDeBorrar: boolean
  retencionResumenMeses: number
  horaResumen: string
  intervaloPantallaSegundos: number
  maxDetalleKb: number
  camposExtra: string[]
  cambiosEntidades: boolean
  actualizadaEn: string | null
  actualizadaPor: string | null
  catalogo: {
    niveles: Array<{ valor: NivelActividad; etiqueta: string; familias: TipoActividad[] }>
    familias: Array<{ valor: TipoActividad; etiqueta: string }>
    camposBase: string[]
    roles: Opcion[]
    empresas: Opcion[]
    estaciones: Opcion[]
  }
}

export type CambiosBitacora = Partial<Omit<ConfiguracionBitacora, 'catalogo' | 'actualizadaEn' | 'actualizadaPor'>>

export interface UsuarioBuscado extends Opcion {
  username: string
}
