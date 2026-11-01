/** Tipos del correo de la empresa (`/api/correo/*`, ADR-048). */

export type Carpeta = 'entrada' | 'enviados' | 'borradores' | 'papelera'
/** Lo que se lista: una carpeta o los destacados (cruza Entrada y Enviados). */
export type Vista = Carpeta | 'destacados'
export type Envio = 'pendiente' | 'enviado' | 'fallido'
export type Origen = 'internos' | 'externos'
export type ModoRespuesta = 'responder' | 'responderTodos' | 'reenviar'
export type Accion = 'leido' | 'noLeido' | 'destacar' | 'quitarDestacado' | 'papelera' | 'restaurar' | 'eliminar'

export interface Direccion {
  direccion: string
  nombre: string | null
}

export interface Adjunto {
  id: number
  nombre: string
  tipo: string
  tamano: number
  imagen: boolean
  /** Relativa a la API y firmada. */
  url: string
}

/** Una fila de la lista. */
export interface Resumen {
  id: number
  carpeta: Carpeta
  de: Direccion
  para: Direccion[]
  asunto: string
  extracto: string
  fecha: string
  leido: boolean
  destacado: boolean
  externo: boolean
  conAdjuntos: boolean
  envio: Envio | null
}

/** El correo completo (o el borrador que se reabre). */
export interface Correo extends Resumen {
  cc: Direccion[]
  cco: Direccion[]
  responderA: string | null
  texto: string
  /** Tal como llegó; `{api}` antecede las URL de las imágenes incrustadas. */
  html: string | null
  errorEnvio: string | null
  adjuntos: Adjunto[]
  origen: number | null
}

export interface Estado {
  direccion: string
  nombre: string
  /** Puede escribir fuera de la empresa (`correo.externo`). */
  externo: boolean
  carpetas: {
    entrada: { noLeidos: number }
    destacados: { total: number }
    enviados: { fallidos: number }
    borradores: { total: number }
    papelera: { total: number }
  }
}

export interface Filtros {
  noLeidos: boolean
  adjuntos: boolean
  origen: Origen | null
  q: string
}

export interface Contacto extends Direccion {
  interno: boolean
}

/** Lo que se guarda de un borrador mientras se escribe. */
export interface Redaccion {
  para: Direccion[]
  cc: Direccion[]
  cco: Direccion[]
  asunto: string
  texto: string
}

/** Aviso por el tópico privado del usuario (el mismo del chat). */
export interface AvisoCorreo {
  tipo: 'correo'
  /** Id del correo nuevo en la Entrada, si es por eso. */
  nuevo?: number | null
  de?: string
  asunto?: string
  /** Cambió la entrega de este enviado. */
  envio?: number
}
