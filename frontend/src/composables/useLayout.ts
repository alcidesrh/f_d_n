import type { LayoutName } from '@/types/vue-router'

export function useLayout() {
  const route = useRoute()
  const router = useRouter()

  const currentLayout = computed<LayoutName>(() => (route.meta.layout as LayoutName) || 'default')
  const pageTitle = computed(() => route.meta.title || '')
  const crumbs = computed(() => route.meta.crumbs || ['Andén'])

  function setLayout(layout: LayoutName) {
    route.meta.layout = layout
  }

  function setCrumbs(newCrumbs: string[]) {
    route.meta.crumbs = newCrumbs
  }

  function setTitle(newTitle: string) {
    route.meta.title = newTitle
    document.title = `${newTitle} | FDN`
  }

  return {
    currentLayout,
    pageTitle,
    crumbs,
    setLayout,
    setCrumbs,
    setTitle,
    route,
    router,
  }
}
