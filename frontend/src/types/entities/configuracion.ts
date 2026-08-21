export interface Agnostic {
  '@id'?: string
  data?: any
  readonly id?: any
}

export interface Icon {
  '@id'?: string
  icon?: string
  name?: any
  label?: any
  readonly id?: any
}

export interface CollectionFieldConfig {
  '@id'?: string
  entityConfig?: any
  isSortable?: any
  filterable?: any
  field?: any
  position?: any
  visible?: any
  label?: any
  attrs?: any
  readonly id?: any
  readonly name?: any
}

export interface FormFieldConfig {
  '@id'?: string
  groupName?: any
  entityConfig?: any
  field?: any
  position?: any
  visible?: any
  label?: any
  attrs?: any
  data?: any
  readonly id?: any
  readonly name?: any
}

export interface LayoutProfile {
  '@id'?: string
  enabled?: any
  layoutSchema?: string
  roleAssignments?: any
  usuarioAssignments?: any
  nombre?: any
  nota?: any
  label?: any
  status?: string
  createdAt?: any
  updatedAt?: any
  readonly id?: any
}

export interface LayoutProfileRole {
  '@id'?: string
  layoutProfile?: string
  role?: string
  position?: any
  label?: any
  readonly id?: any
}

export interface LayoutProfileUsuario {
  '@id'?: string
  layoutProfile?: string
  usuario?: string
  position?: any
  label?: any
  readonly id?: any
}

export interface LayoutSchema {
  '@id'?: string
  area?: any
  items?: any
  nombre?: any
  nota?: any
  label?: any
  status?: string
  createdAt?: any
  updatedAt?: any
  readonly id?: any
}

export interface LayoutSchemaItem {
  '@id'?: string
  layoutSchema?: string
  vueRoute?: string
  position?: any
  label?: any
  readonly id?: any
}

export interface VueRoute {
  '@id'?: string
  nombre?: any
  vueRouteName?: any
  path?: any
  params?: any
  icon?: string
  roles?: string[]
  usuariosPermitidos?: any
  usuariosDenegados?: any
  vueRoute?: string
  hijos?: any
  readonly id?: any
}
