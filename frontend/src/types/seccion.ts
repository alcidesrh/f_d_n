export type Seccion = {
  label?: string
  name: string
  icon?: string
  perm?: string[]
  to?: string
  children?: Seccion[]
}
