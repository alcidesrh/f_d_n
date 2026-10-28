/** Chat interno (ADR-026): tipos del transporte `/api/chat/*`. */

export type Ambito = 'administracion' | 'estacion' | 'agencia'

export interface Perfil {
  id: number
  nombre: string
  ambito: Ambito
  /** Estación, agencia o empresa. */
  lugar: string | null
  /** Foto de perfil (ruta firmada de la API) o null. */
  foto?: string | null
}

/** Miembro de un canal: en un grupo, por sí mismo o por un grupo miembro (`via`). */
export interface Miembro extends Perfil {
  /** Id del grupo miembro por el que está; null = por sí mismo. */
  via: number | null
  /** En un grupo dirigido: el dueño lo eligió para escribir. */
  emisor: boolean
}

/** Grupo abierto que es miembro de otro grupo. */
export interface Subgrupo {
  id: number
  nombre: string
  foto: string | null
  /** En un grupo dirigido: sus miembros escriben. */
  emisor: boolean
}

/** Abierto: todos escriben. Dirigido: todos reciben; escriben el dueño y los emisores. */
export type ModoGrupo = 'abierto' | 'dirigido'

export interface Canal {
  id: number
  /** `sistema`: avisos automáticos del usuario, solo lectura. */
  tipo: 'directo' | 'grupo' | 'sistema'
  nombre: string
  /** El otro miembro, en los directos. */
  contacto: Perfil | null
  /** Solo los grupos: foto (ruta firmada de la API) o null. */
  foto?: string | null
  miembros: Miembro[]
  /** Solo los grupos. */
  modo: ModoGrupo | null
  /** Solo los grupos: quien lo creó y lo edita. */
  dueno: number | null
  /** Puedo editar el grupo (nombre, modo, miembros). */
  administro: boolean
  /** Puedo escribir (falso en los avisos del sistema y en un dirigido si no soy emisor). */
  puedeEscribir: boolean
  /** Grupos abiertos que son miembros de este. */
  subgrupos: Subgrupo[]
  /** Este grupo es miembro de otro (entonces solo puede ser abierto y no tiene grupos miembros). */
  esSubgrupo: boolean
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

/** Referencia con su etiqueta para mostrarla antes de enviar (el selector, "en pantalla"). */
export interface Registro extends Referencia {
  etiqueta: string
}

/** Un tipo de registro que el usuario puede adjuntar (`GET /api/chat/recursos`). */
export interface Recurso {
  tipo: string
  /** Tiene tarjeta propia (boleto, salida). */
  propia: boolean
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
  /** El mensaje al que responde (si es una edición, el que corrige). */
  respuesta: { id: number; autor: string; extracto: string } | null
  /** El grupo miembro por el que el autor estaba en el grupo al escribir. */
  via: { id: number; nombre: string } | null
  /** Corrige al mensaje de `respuesta`. */
  editado: boolean
  reenviado: boolean
  /** Eliminado para todos: sin texto, registros ni archivos. */
  eliminado: boolean
}

export interface Bandeja {
  yo: Perfil
  canales: Canal[]
}

/** Lo que llega por Mercure en `/chat/usuarios/{id}`. */
export type Aviso =
  | { tipo: 'mensaje'; canal: number; mensaje: number; autor: Perfil | null; extracto: string }
  | { tipo: 'leido'; canal: number; usuario: number; hasta: number }
  /** Un grupo cambió (nombre, foto, modo, miembros) o me agregaron o quitaron. */
  | { tipo: 'canal'; canal: number }
  /** Se eliminó un mensaje. */
  | { tipo: 'cambio'; canal: number; mensaje: number }
  | { tipo: 'presencia'; usuario: number; enLinea: boolean }
  /** Cambiaron sus roles, su alcance o su activación (ADR-039): el cliente vuelve a pedir lo que puede. */
  | { tipo: 'acceso' }
  /** Se pidió, resolvió o retiró una autorización que le toca (ADR-040). */
  | { tipo: 'autorizacion'; id: number }

export interface Destinos {
  usuarios: number[]
  canales: number[]
}
