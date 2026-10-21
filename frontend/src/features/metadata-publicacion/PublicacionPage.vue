<!--
  Publicación de la metadata del cliente (ADR-036): en qué modo está, lo que
  falta publicar y las últimas revisiones. Publicar a mano manda a cada
  navegador lo vigente, con una nota, con aviso («Recargar») o pidiendo que
  vuelva a cargar todo. Pide `config.editar` (lo decide el backend).
-->
<template>
  <div class="@container flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <Tag v-if="resumen" :value="resumen.modo === 'manual' ? 'Modo manual' : 'Modo automático'" :severity="resumen.modo === 'manual' ? 'warn' : 'info'" />
      <span v-if="resumen" class="text-sm text-muted-color">Revisión vigente: {{ resumen.revision }}</span>
      <Button class="ml-auto" severity="secondary" text size="small" :loading="cargando" aria-label="Actualizar" v-tooltip.left="'Actualizar'" @click="cargar">
        <template #icon><icon name="refresh" /></template>
      </Button>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Skeleton v-if="cargando && !resumen" height="12rem" />

    <template v-if="resumen">
      <p class="text-sm text-muted-color">{{ explicacion }}</p>

      <div class="columnas">
        <section class="panel flex flex-col gap-3" aria-labelledby="publicacion-pendiente">
          <h2 id="publicacion-pendiente" class="titulo">Pendiente de publicar</h2>
          <ul v-if="pendiente.length" class="flex flex-col gap-1.5 text-sm">
            <li v-for="linea in pendiente" :key="linea.texto" class="flex items-start gap-2">
              <icon :name="linea.icono" class="mt-0.5 shrink-0 text-muted-color" />
              <span class="min-w-0">{{ linea.texto }}</span>
            </li>
          </ul>
          <p v-else class="text-sm text-muted-color">No hay cambios sin publicar: cada navegador ya tiene lo vigente o lo toma al usarlo.</p>

          <div class="flex flex-col gap-1">
            <label for="publicacion-nota" class="text-sm font-medium">Nota (opcional)</label>
            <Textarea id="publicacion-nota" v-model="nota" rows="2" auto-resize :maxlength="NOTA_MAXIMA" placeholder="Qué cambió, para quien vea el aviso" fluid />
            <small class="self-end text-muted-color tabular-nums">{{ nota.length }}/{{ NOTA_MAXIMA }}</small>
          </div>
          <label class="flex items-start gap-2 text-sm" for="publicacion-aviso">
            <Checkbox v-model="aviso" binary input-id="publicacion-aviso" class="mt-0.5" />
            <span>
              <span class="block font-medium">Avisar en cada navegador</span>
              <span class="text-muted-color">Muestra «Recargar» con la nota. Nunca recarga solo.</span>
            </span>
          </label>
          <label class="flex items-start gap-2 text-sm" for="publicacion-completa">
            <Checkbox v-model="completa" binary input-id="publicacion-completa" class="mt-0.5" />
            <span>
              <span class="block font-medium">Recargar todo</span>
              <span class="text-muted-color">Cada navegador vuelve a pedir el schema, la configuración y las listas guardadas, aunque no se vean cambios.</span>
            </span>
          </label>
          <div class="flex justify-end">
            <Button label="Publicar" :loading="publicando" :disabled="!sePuedePublicar" @click="publicar">
              <template #icon><icon name="published-with-changes" /></template>
            </Button>
          </div>
        </section>

        <section class="panel flex min-w-0 flex-col gap-2" aria-labelledby="publicacion-historial">
          <h2 id="publicacion-historial" class="titulo">Últimas revisiones</h2>
          <p v-if="!resumen.historial.length" class="text-sm text-muted-color">Todavía no hay revisiones.</p>
          <ol class="flex flex-col">
            <li v-for="r in resumen.historial" :key="r.revision" class="revision">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold tabular-nums">#{{ r.revision }}</span>
                <Tag :value="ORIGENES[r.origen].etiqueta" :severity="ORIGENES[r.origen].severidad" class="!text-xs" />
                <Tag v-if="r.aviso" value="Con aviso" severity="warn" class="!text-xs" />
                <Tag v-if="r.completa" value="Recargar todo" severity="danger" class="!text-xs" />
                <span class="ml-auto text-xs text-muted-color">{{ fechaRevision(r.creadaEn) }}</span>
              </div>
              <div v-for="linea in describirCambios(r.cambios)" :key="linea.texto" class="flex items-start gap-2 text-sm">
                <icon :name="linea.icono" class="mt-0.5 shrink-0 text-muted-color" />
                <span class="min-w-0 break-words">{{ linea.texto }}</span>
              </div>
              <p v-if="r.nota" class="nota">{{ r.nota }}</p>
              <span v-if="r.usuario" class="text-xs text-muted-color">Por {{ r.usuario }}</span>
            </li>
          </ol>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, shallowRef, watch } from 'vue'
import { HttpError } from '@/core/http'
import {
  describirCambios,
  fechaRevision,
  fetchPublicacion,
  NOTA_MAXIMA,
  notaLimpia,
  ORIGENES,
  publicarMetadata,
  puedePublicar,
  type ResumenPublicacion,
} from '@/core/metadata/publicacion'
import { useMetadataStore } from '@/core/metadata/sincronizacion'
import { notify } from '@/core/notify'

const resumen = shallowRef<ResumenPublicacion | null>(null)
const cargando = ref(false)
const publicando = ref(false)
const error = ref('')
const nota = ref('')
const aviso = ref(false)
const completa = ref(false)

const explicacion = computed(() =>
  resumen.value?.modo === 'manual'
    ? 'Modo manual: lo que se guarda en la configuración de entidades espera aquí hasta que lo publiques. El schema de la API se publica solo al desplegar. El modo se elige en el servidor (METADATA_PUBLICACION).'
    : 'Modo automático: cada navegador se pone al día solo al guardarse la configuración de una entidad, y el schema de la API al desplegar. Publicar aquí sirve para avisar con una nota o para que todos vuelvan a cargar todo.',
)
const pendiente = computed(() => (resumen.value ? describirCambios(resumen.value.pendiente, 12) : []))
const sePuedePublicar = computed(
  () => !!resumen.value && !publicando.value && puedePublicar(resumen.value.pendiente, { aviso: aviso.value, completa: completa.value }),
)

function mensaje(causa: unknown): string {
  if (causa instanceof HttpError && causa.status === 403) return 'Publicar la configuración pide el permiso config.editar.'
  return causa instanceof Error ? causa.message : String(causa)
}

async function cargar() {
  cargando.value = true
  try {
    resumen.value = await fetchPublicacion()
    error.value = ''
  } catch (causa) {
    error.value = mensaje(causa)
  } finally {
    cargando.value = false
  }
}

async function publicar() {
  publicando.value = true
  try {
    resumen.value = await publicarMetadata({ nota: notaLimpia(nota.value), aviso: aviso.value, completa: completa.value })
    notify.success(`Revisión ${resumen.value.revision} publicada`)
    nota.value = ''
    aviso.value = false
    completa.value = false
  } catch (causa) {
    if (causa instanceof HttpError && causa.status === 409) notify.warning(causa.message)
    else notify.error(mensaje(causa))
  } finally {
    publicando.value = false
  }
}

// Otra revisión (guardado automático, despliegue, otro administrador): lo pendiente cambió.
const metadata = useMetadataStore()
watch(
  () => metadata.revision,
  (revision) => {
    if (revision !== null && resumen.value && revision !== resumen.value.revision) void cargar()
  },
)

onMounted(cargar)
</script>

<style scoped>
.panel {
  background: var(--p-content-background);
  border-radius: var(--p-content-border-radius, 0.75rem);
  padding: 1rem;
}
.titulo {
  font-size: 1rem;
  font-weight: 600;
}
.columnas {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0.75rem;
  align-items: start;
}
@container (min-width: 56rem) {
  .columnas {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }
}
.revision {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.625rem 0;
  border-top: 1px solid var(--p-content-border-color);
}
.revision:first-child {
  border-top: 0;
  padding-top: 0;
}
.nota {
  font-size: 0.875rem;
  padding: 0.375rem 0.625rem;
  border-left: 3px solid var(--p-primary-color);
  background: var(--p-content-hover-background);
  border-radius: 0.25rem;
  white-space: pre-line;
}
</style>
