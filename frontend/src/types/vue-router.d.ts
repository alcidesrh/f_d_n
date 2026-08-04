import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    crumbs?: string[]
    title?: string
  }
}
