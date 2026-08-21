export interface Usuario {
  '@id'?: string
  username?: any
  plainPassword: any
  apiTokens?: string[]
  userRoles?: any
  permisos?: string[]
  directActions?: any
  deniedActions?: any
  ventas?: string[]
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
  readonly fullName?: any
  readonly id?: any
  readonly token?: any
}

export interface Role {
  '@id'?: string
  nombre?: any
  parents?: any
  children?: any
  permisos?: string[]
  actions?: string[]
  label?: any
  readonly id?: any
}

export interface Permiso {
  '@id'?: string
  roles?: string[]
  parents?: any
  children?: any
  actions?: string[]
  nombre?: any
  nota?: any
  label?: any
  status?: string
  readonly id?: any
}

export interface ApiToken {
  '@id'?: string
  usuario?: string
  expira?: any
  token?: any
  activo?: any
  label?: any
  createdAt?: any
  updatedAt?: any
  readonly id?: any
  readonly valid?: any
}

export interface Action {
  '@id'?: string
  ruta?: any
  nombre?: any
  roles?: string[]
  permisos?: string[]
  icon?: string
  rutaParams?: any
  label?: any
  status?: string
  readonly id?: any
}
