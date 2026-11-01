/** Configuración de los proveedores externos (ADR-047): certificador FEL y pasarela de pago. */

export interface EmisorEnLinea {
  /** NIT de la empresa cuyas ventas en línea se facturan a nombre de otra. */
  empresa: string
  /** NIT de la empresa que las factura. */
  emisor: string
}

export interface ConfiguracionForcon {
  activo: boolean
  url: string
  usuario: string | null
  /** Hay clave global guardada (la clave nunca viaja al navegador). */
  conClave: boolean
  emisoresEnLinea: EmisorEnLinea[]
  actualizadaEn: string | null
  actualizadaPor: string | null
  /** `credencial`: `propia`, `ilegible` (cifrada con otro `APP_SECRET`: volver a cargarla) o null. */
  empresas: Array<{ nombre: string; nit: string; credencial: 'propia' | 'ilegible' | null }>
}

/** Lo que se guarda: `clave` vacía conserva la guardada; `usuario` vacío quita la credencial global. */
export interface CambiosForcon {
  activo: boolean
  url: string
  usuario: string
  clave: string
  emisoresEnLinea: EmisorEnLinea[]
}

export interface DatosEstablecimiento {
  nombreComercial: string
  correo: string
  direccion: string
  codigoPostal: string
  municipio: string
  departamento: string
  pais: string
  razonSocial: string | null
}

export interface ConfiguracionCybersource {
  activo: boolean
  url: string
  orgId: string | null
  actualizadaEn: string | null
  actualizadaPor: string | null
  /** `comercio`: merchant id cargado con `app:pago:credencial`, o null. */
  empresas: Array<{ nombre: string; comercio: string | null }>
}

export interface CambiosCybersource {
  activo: boolean
  url: string
  orgId: string
}
