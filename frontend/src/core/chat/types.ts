/** Chat interno (ADR-026): tipos del transporte `/api/chat/*`. */

export type Ambito = 'administracion' | 'estacion' | 'agencia'

export interface Perfil {
  id: number
  nombre: string
  ambito: Ambito
  /** Estación, agencia o empresa. */
  lugar: string | null
}

export interface Canal {
  id: number
  /** `sistema`: avisos automáticos del usuario, solo lectura. */
  tipo: 'directo' | 'grupo' | 'sistema'
  nombre: string
  /** El otro miembro, en los directos. */
  contacto: Perfil | null
  miembros: Perfil[]
  noLeidos: number
  leidoHasta: number
  /** Hasta qué mensaje leyó cada uno de los demás miembros (el "visto"). */
  lecturas: { usuario: number; hasta: number }[]
  /** ISO */
  actividad: string
  ultimo: { id: number; autor: number | null; extracto: string; fecha: string } | null
}

/** Referencia a un registro del sistema: lo que se guarda en el mensaje. */
export interface Referencia {
  /** Nombre de la entidad (`BoletoAsiento`, `Salida`, `Bus`…). */
  tipo: string
  id: number
}

/** Una referencia ya resuelta con los permisos de quien lee. */
export interface Adjunto extends Referencia {
  estado: 'ok' | 'sin_acceso' | 'no_existe'
  /** Siempre trae `titulo`; el resto depende del tipo. */
  datos: ({ titulo: string } & Record<string, unknown>) | null
}

/** Foto o documento enviado en un mensaje. `url` es relativa a la API y vence (firmada). */
export interface Archivo {
  id: number
  nombre: string
  tipo: string
  tamano: number
  ancho: number | null
  alto: number | null
  imagen: boolean
  url: string
}

export interface Mensaje {
  id: number
  canal: number
  /** null: aviso del sistema. */
  autor: Perfil | null
  texto: string
  /** ISO */
  fecha: string
  adjuntos: Adjunto[]
  archivos: Archivo[]
  /** El mensaje al que responde. */
  respuesta: { id: number; autor: string; extracto: string } | null
}

export interface Bandeja {
  yo: Perfil
  canales: Canal[]
}

/** Lo que llega por Mercure en `/chat/usuarios/{id}`. */
export type Aviso =
  | { tipo: 'mensaje'; canal: number; mensaje: number; autor: Perfil | null; extracto: string }
  | { tipo: 'leido'; canal: number; usuario: number; hasta: number }
  | { tipo: 'canal'; canal: number }

export interface Destinos {
  usuarios: number[]
  canales: number[]
}
