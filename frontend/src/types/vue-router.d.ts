import 'vue-router'

export type LayoutName = 'default' | 'auth' | 'blank' | 'formdemo'

declare module 'vue-router' {
  interface RouteMeta {
    crumbs?: string[]
    title?: string
    subtitle?: string
    layout?: LayoutName
    requiresAuth?: boolean
    icon?: string
  }
}
