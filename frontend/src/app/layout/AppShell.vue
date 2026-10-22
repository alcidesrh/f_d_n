<template>
  <div class="shell" :class="{ 'shell--sin-derecha': !hasRightSidebar }">
    <AppHeader :right-sidebar="hasRightSidebar ? rightSidebar : null">
      <template #menu-content>
        <NavArea area="topbar_right" />
      </template>
    </AppHeader>
    <div class="body-row">
      <!-- Debajo de `lg` los sidebars son drawers: el fondo los cierra. -->
      <Transition name="fade">
        <div v-if="drawerOpen" class="shell-backdrop" aria-hidden="true" @click="closeDrawers" />
      </Transition>
      <SidebarLeft />
      <main class="main">
        <div class="main-inner">
          <slot />
        </div>
      </main>
      <Sidebar v-if="panel" :key="String(route.name)" :store="rightSidebar" nomini>
        <template #menu-content>
          <component :is="panel" />
        </template>
      </Sidebar>
      <!-- Sin menús en el área derecha para el usuario, no hay barra (ni su botón en la cabecera). -->
      <SidebarRight v-else-if="hasRightMenus" />
    </div>
    <ChatAviso />
    <ChatVentana v-if="ventana.esVentana && session.user" />
    <EstacionTrabajoDialog v-if="session.user" />
    <SolicitarAutorizacionDialog v-if="session.user" />
  </div>
</template>

<script setup lang="ts">
import { defineAsyncComponent, provide } from 'vue'
import { useUiStore } from '@/app/ui'
import { useSessionStore } from '@/core/auth/session'
import { useAccesoStore } from '@/core/acceso/store'
import { filtrarMenusCon, useUserMenusStore } from '@/core/navigation/userMenus'
import { useChatStore } from '@/core/chat/store'
import { useCuentaStore } from '@/core/cuenta/store'
import { useAutorizacionesStore } from '@/core/autorizacion/store'
import { useMetadataStore } from '@/core/metadata/sincronizacion'
import EstacionTrabajoDialog from '@/features/acceso/EstacionTrabajoDialog.vue'
import ChatAviso from '@/features/chat/ChatAviso.vue'
import ChatVentana from '@/features/chat/ChatVentana.vue'
import { useVentanaChat } from '@/features/chat/ventana'
import SolicitarAutorizacionDialog from '@/shared/autorizacion/SolicitarAutorizacionDialog.vue'
import { LISTADO_DE_REGISTROS, REPORTES_ADJUNTABLES } from '@/shared/chat/integracion'
import AppHeader from './AppHeader.vue'
import NavArea from './navigation/NavArea.vue'
import Sidebar from './Sidebar.vue'
import SidebarLeft from './SidebarLeft.vue'
import SidebarRight from './SidebarRight.vue'
import { defineSidebarStore } from './sidebarStore'

const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const userMenus = useUserMenusStore()
const chat = useChatStore()
const cuenta = useCuentaStore()
const metadata = useMetadataStore()
const ventana = useVentanaChat()
const autorizaciones = useAutorizacionesStore()

// El selector de registros del chat usa el listado genérico (una feature no importa a otra).
provide(LISTADO_DE_REGISTROS, defineAsyncComponent(() => import('@/features/entity-crud/ListPage.vue')))

provide(REPORTES_ADJUNTABLES, [
  { id: 'cuadre-venta-boletos', nombre: 'Cuadre de venta', icono: 'request-quote-outline', componente: defineAsyncComponent(() => import('@/features/reporte/CuadreVentaBoletosPage.vue')) },
  { id: 'detalle-factura-boletos', nombre: 'Detalle de facturas', icono: 'receipt-long-outline', componente: defineAsyncComponent(() => import('@/features/reporte/DetalleFacturaBoletosPage.vue')) },
  { id: 'cuadre-voucher', nombre: 'Cuadre de voucher', icono: 'confirmation-number-outline', componente: defineAsyncComponent(() => import('@/features/reporte/CuadreVoucherPage.vue')) },
])

// Los menús solo muestran las pantallas que el usuario puede abrir (ADR-039).
const acceso = useAccesoStore()
filtrarMenusCon((nombre) => {
  try {
    const r = router.resolve({ name: nombre })
    return acceso.puedeEntrar({ name: r.name, params: r.params, meta: r.meta })
  } catch {
    // Una ruta con parámetros obligatorios: el ítem se pinta deshabilitado de todos modos.
    return true
  }
})
const dejarDeEscuchar = acceso.suscribir(() => void userMenus.load())
/** Al volver a la pestaña, por si cambiaron sus accesos mientras no miraba. */
const alVolver = () => document.visibilityState === 'visible' && session.user && void acceso.cargar()
document.addEventListener('visibilitychange', alVolver)

// Accesos, menús y chat del usuario: se cargan al entrar al shell y al cambiar de usuario.
// La metadata del cliente (ADR-036) se escucha mientras hay sesión.
watch(
  () => session.user,
  (user) => {
    chat.detener()
    metadata.detener()
    if (!user) {
      cuenta.limpiar()
      acceso.limpiar()
      return userMenus.clear()
    }
    void acceso.cargar().then(() => userMenus.load())
    void cuenta.cargar()
    void autorizaciones.cargarResumen()
    void chat.iniciar()
    metadata.iniciar()
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  chat.detener()
  metadata.detener()
  dejarDeEscuchar()
  document.removeEventListener('visibilitychange', alVolver)
})

/** Panel propio de la ruta (`meta.panel`), con su propio store de ancho/modo. */
const panel = computed(() =>
  route.meta.panel ? defineAsyncComponent(route.meta.panel.component) : null,
)

/** Barra derecha: el panel de la ruta o, si no tiene, los menús del área derecha del usuario. */
const hasRightMenus = computed(() => userMenus.areas.sidebar_right.length > 0)
const hasRightSidebar = computed(() => Boolean(panel.value) || hasRightMenus.value)

const rightSidebar = computed(() => {
  if (!route.meta.panel) return defineSidebarStore('right')()
  const store = defineSidebarStore('right', `panel:${String(route.name)}`)()
  store.open = route.meta.panel.width ?? store.open
  return store
})

// Drawers (debajo de `lg`) --------------------------------------------------
const ui = useUiStore()
const leftSidebar = defineSidebarStore('left')()
const drawerOpen = computed(() => leftSidebar.drawer || (hasRightSidebar.value && rightSidebar.value.drawer))

function closeDrawers() {
  leftSidebar.drawer = false
  rightSidebar.value.drawer = false
}

// Uno a la vez; se cierran al navegar y al pasar a escritorio.
watch(
  () => leftSidebar.drawer,
  (open) => open && (rightSidebar.value.drawer = false),
)
watch(
  () => rightSidebar.value.drawer,
  (open) => open && (leftSidebar.drawer = false),
)
watch(() => route.fullPath, closeDrawers)
watch(
  () => ui.isMobile,
  (mobile) => !mobile && closeDrawers(),
)

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && drawerOpen.value) closeDrawers()
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>
