export interface Nacion {
  '@id'?: string
  nombre?: any
  label?: any
  readonly id?: any
}

export interface Localidad {
  '@id'?: string
  nombre?: any
  nacion?: string
  label?: any
  readonly id?: any
}

export interface Parada {
  '@id'?: string
  label?: any
  nombre?: any
  direccion?: any
  latitud?: any
  longitud?: any
  ventas?: string[]
  readonly id?: any
}

export interface Estacion {
  '@id'?: string
  label?: any
  nombre?: any
  direccion?: any
  latitud?: any
  longitud?: any
  ventas?: string[]
  readonly id?: any
}

export interface Enclave {
  '@id'?: string
  nombre?: any
  direccion?: any
  latitud?: any
  longitud?: any
  ventas?: string[]
  label?: any
  readonly id?: any
}
