export interface Cliente {
  '@id'?: string
  nombre?: any
  apellido?: any
  email?: any
  nit?: any
  telefono?: any
  direccion?: any
  localidad?: string
  label?: any
  createdAt?: any
  updatedAt?: any
  status?: string
  readonly id?: any
}

export interface Asiento {
  '@id'?: string
  numero?: any
  clase?: any
  fila?: any
  columna?: any
  bus?: string
  label?: any
  readonly id?: any
}

export interface Boleto {
  '@id'?: string
  recorrido?: string
  cliente?: string
  legacyId?: any
  venta?: any
  asiento?: string
  servicio?: string
  label?: any
  createdAt?: any
  updatedAt?: any
  status?: string
  readonly id?: any
}

export interface Venta {
  '@id'?: string
  usuario?: string
  boletos?: string[]
  factura?: string
  enclave?: string
  empresa?: string
  label?: any
  createdAt?: any
  updatedAt?: any
  status?: string
  readonly id?: any
}

export interface Factura {
  '@id'?: string
  dte?: any
  uuid?: any
  serie?: any
  fecha?: any
  emisorNit?: any
  emisorNombre?: any
  establecimientoCodigo?: any
  emisorNombreComercial?: any
  receptopNit?: any
  receptorNombre?: any
  createdAt?: any
  updatedAt?: any
  readonly id?: any
}
