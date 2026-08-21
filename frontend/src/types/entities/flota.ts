export interface Bus {
  '@id'?: string
  matricula?: any
  gama?: any
  empresa?: string
  asientos?: string[]
  piloto?: string
  pilotoAux?: any
  marca?: any
  codigo?: any
  anoFabricacion?: any
  numeroSeguro?: any
  fechaVencimientoTarjetaOperaciones?: any
  numeroTarjetaRodaje?: any
  numeroTarjetaOperaciones?: any
  descripcion?: any
  label?: any
  readonly id?: any
}

export interface BusMarca {
  '@id'?: string
  label?: any
  readonly nombre?: any
  readonly id?: any
}

export interface Piloto {
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
  readonly fechaNacimiento?: any
  readonly numeroLicencia?: any
  readonly fechaVencimientoLicencia?: any
  readonly dpi?: any
  readonly seguroSocial?: any
  readonly codigo?: any
  readonly empresa?: string
  readonly id?: any
}

export interface Servicio {
  '@id'?: string
  fecha?: any
  legacyId?: any
  empresa?: string
  recorrido?: string
  bus?: string
  piloto?: string
  boletos?: string[]
  label?: any
  createdAt?: any
  updatedAt?: any
  status?: string
  readonly id?: any
}

export interface Trayecto {
  '@id'?: string
  origen?: any
  destino?: any
  distanciaKm?: any
  duracionEstimadaMinutos?: any
  activo?: any
  legacyId?: any
  recorridos?: string[]
  label?: any
  readonly trayectosHijos?: any
  readonly trayectosPadres?: any
  readonly id?: any
}

export interface Recorrido {
  '@id'?: string
  nombre?: any
  precioClaseA?: any
  precioClaseB?: any
  empresa?: string
  bus?: string
  trayecto?: string
  subrecorridos?: any
  label?: any
  recorridos?: any
  readonly id?: any
}

export interface RecorridoMatrioska {
  '@id'?: string
  recorrido?: string
  subrecorridos?: any
  posicion?: any
  readonly id?: any
}
