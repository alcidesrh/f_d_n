<!--
  "Mi cuenta" (`/mi-cuenta`): lo que el usuario puede editar de sí mismo,
  agrupado en secciones (`secciones.ts`): perfil, seguridad, notificaciones y
  apariencia. La abierta va en `?seccion=` (la ayuda enlaza directo). El
  usuario, los roles y los permisos los administra otra persona.
-->
<template>
  <div>
    <PageHead />
    <div v-if="cuenta.cuenta" class="form-fluid @container">
      <div class="grid grid-cols-1 items-start gap-4 @4xl:grid-cols-[16rem_minmax(0,1fr)] @4xl:gap-6">
        <aside class="flex min-w-0 flex-col gap-3 @4xl:sticky @4xl:top-4">
          <div class="flex items-center gap-3 rounded-xl border border-surface-200 bg-surface-0 p-3">
            <ChatAvatar :id="cuenta.cuenta.id" :nombre="cuenta.nombre" :foto="cuenta.foto" tamano="lg" />
            <div class="min-w-0">
              <div class="truncate font-semibold">{{ cuenta.nombre }}</div>
              <div class="truncate text-sm text-muted-color">@{{ cuenta.cuenta.username }}</div>
              <!-- Dónde trabaja (solo lectura). -->
              <div v-for="v in vinculos" :key="v.etiqueta" class="truncate text-xs text-muted-color" :title="`${v.etiqueta}: ${v.valor}`">{{ v.valor }}</div>
            </div>
          </div>

          <!-- En el teléfono, una fila que se desliza; desde @4xl, la lista a la izquierda. -->
          <nav ref="menu" aria-label="Secciones de Mi cuenta" class="-mx-1 overflow-x-auto px-1 pb-1 @4xl:mx-0 @4xl:overflow-visible @4xl:p-0">
            <ul class="m-0 flex list-none gap-1 p-0 @4xl:flex-col">
              <li v-for="s in SECCIONES" :key="s.id" class="shrink-0">
                <RouterLink
                  :to="{ query: { ...route.query, seccion: s.id } }"
                  replace
                  class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap no-underline transition-colors"
                  :class="activa === s.id ? 'bg-primary-50 text-primary-700' : 'text-surface-700 hover:bg-surface-100'"
                  :aria-current="activa === s.id ? 'page' : undefined"
                >
                  <icon :name="s.icono" size="1.1rem" />
                  {{ s.titulo }}
                </RouterLink>
              </li>
            </ul>
          </nav>
        </aside>

        <section :key="activa" class="min-w-0" :aria-labelledby="`seccion-${activa}`">
          <header class="mb-4 border-b border-surface-200 pb-3">
            <h2 :id="`seccion-${activa}`" class="m-0 text-base font-semibold">{{ seccion.titulo }}</h2>
            <p class="m-0 text-sm text-muted-color">{{ seccion.descripcion }}</p>
          </header>
          <PerfilSeccion v-if="activa === 'perfil'" />
          <SeguridadSeccion v-else-if="activa === 'seguridad'" />
          <NotificacionesSeccion v-else-if="activa === 'notificaciones'" />
          <component :is="editorApariencia" v-else-if="activa === 'apariencia' && editorApariencia" />
        </section>
      </div>
    </div>
    <div v-else class="flex justify-center py-12">
      <ProgressSpinner style="width: 2rem; height: 2rem" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, nextTick, onMounted, useTemplateRef, watch } from 'vue'
import { useCuentaStore } from '@/core/cuenta/store'
import ChatAvatar from '@/shared/chat/ChatAvatar.vue'
import { EDITOR_DE_APARIENCIA } from '@/shared/cuenta/integracion'
import PageHead from '@/shared/ui/PageHead.vue'
import NotificacionesSeccion from './NotificacionesSeccion.vue'
import PerfilSeccion from './PerfilSeccion.vue'
import { SECCIONES, seccionDe } from './secciones'
import SeguridadSeccion from './SeguridadSeccion.vue'

const route = useRoute()
const cuenta = useCuentaStore()
const editorApariencia = inject(EDITOR_DE_APARIENCIA, null)

const activa = computed(() => seccionDe(route.query.seccion))
const seccion = computed(() => SECCIONES.find((s) => s.id === activa.value)!)

onMounted(() => void cuenta.cargar())

// En el teléfono la fila de secciones se desliza: que la abierta quede a la vista.
const menu = useTemplateRef<HTMLElement>('menu')
watch(
  [activa, menu],
  async () => {
    await nextTick()
    menu.value?.querySelector('[aria-current="page"]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' })
  },
  { immediate: true },
)

/** Dónde trabaja: empresa, estación o agencia. */
const vinculos = computed(() =>
  [
    { etiqueta: 'Empresa', valor: cuenta.cuenta?.empresa },
    { etiqueta: 'Estación', valor: cuenta.cuenta?.estacion },
    { etiqueta: 'Agencia', valor: cuenta.cuenta?.agencia },
  ].filter((v): v is { etiqueta: string; valor: string } => Boolean(v.valor)),
)
</script>
