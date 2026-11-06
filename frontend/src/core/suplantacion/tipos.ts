/** Suplantación de usuarios: un administrador del sistema entra como otro usuario. */

/** Qué puede hacer quien suplanta en el chat y el correo del usuario. */
export type ModoChat = 'permitir' | 'lectura' | 'bloquear'

/** `suplantacion` de `GET /api/me/acceso`: quién entró como este usuario. */
export interface SuplantacionEnCurso {
  actor: { id: number; username: string; nombre: string }
  motivo: string | null
  /** ISO 8601: cuándo vence el token (no se renueva). */
  expira: string | null
  chat: ModoChat
}

/** `GET/PUT /api/suplantacion/configuracion`. */
export interface ConfiguracionSuplantacion {
  activa: boolean
  duracionMinutos: number
  chat: ModoChat
  avisarUsuario: boolean
  exigirMotivo: boolean
  actualizadaEn: string | null
  actualizadaPor: string | null
  catalogo: {
    chat: Array<{ valor: ModoChat; etiqueta: string }>
    duracion: { min: number; max: number }
  }
}

export type CambiosSuplantacion = Partial<
  Pick<
    ConfiguracionSuplantacion,
    'activa' | 'duracionMinutos' | 'chat' | 'avisarUsuario' | 'exigirMotivo'
  >
>
