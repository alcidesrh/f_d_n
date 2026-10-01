/** Textos largos de las páginas informativas (por idioma). */
export interface Seccion {
  titulo: string
  parrafos?: string[]
  items?: string[]
}

export interface Servicio {
  /** Ancla en /servicios#id e ícono. */
  id: 'oro-gran-lujo' | 'oro' | 'platino' | 'economico' | 'renta' | 'encomiendas'
  nombre: string
  resumen: string
  texto: string
  destacados: string[]
}

export interface Contenido {
  nosotros: {
    titulo: string
    entradilla: string
    historia: string[]
    secciones: Seccion[]
  }
  servicios: {
    titulo: string
    entradilla: string
    lista: Servicio[]
  }
  politicas: {
    titulo: string
    entradilla: string
    secciones: Seccion[]
  }
}

/** Datos de contacto (iguales en todos los idiomas). */
export const CONTACTO = {
  telefono: '+502 3763 7025',
  telefonoEnlace: 'tel:+50237637025',
  whatsapp: 'https://wa.me/50237637025',
  renta: '+502 7947 7070',
  rentaEnlace: 'tel:+50279477070',
  direccion: '17 calle 8-46, zona 1, Ciudad de Guatemala',
  facebook: 'https://www.facebook.com/TRANSPORTESFUENTEDELNORTE/',
} as const
