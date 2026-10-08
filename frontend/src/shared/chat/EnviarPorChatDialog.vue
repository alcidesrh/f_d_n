<!--
  "Enviar por chat": manda registros (los seleccionados en un listado, una
  salida…) a una o varias personas o grupos, con una nota opcional. Cada
  destinatario los recibe como tarjetas vivas, vistas con sus permisos.
-->
<template>
  <Dialog :visible="visible" modal :header="titulo" class="w-[min(30rem,calc(100vw-1rem))]" :pt="{ content: { class: '!pb-2' } }" @update:visible="emit('update:visible', $event)" @show="preparar">
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap gap-1.5">
        <span v-for="r in referencias.slice(0, 6)" :key="`${r.tipo}:${r.id}`" class="ref">{{ etiqueta(r.tipo) }} {{ r.id }}</span>
        <span v-if="referencias.length > 6" class="ref">+{{ referencias.length - 6 }}</span>
      </div>

      <div v-if="elegidos.length" class="flex flex-wrap gap-1.5">
        <span v-for="d in elegidos" :key="d.clave" class="elegido">
          {{ d.nombre }}
          <button type="button" class="tap-target" :aria-label="`Quitar ${d.nombre}`" @click="alternar(d)"><icon name="close" size=".85rem" color="text-current" /></button>
        </span>
      </div>

      <IconField>
        <InputIcon><icon name="search" size="1rem" /></InputIcon>
        <InputText v-model="busqueda" placeholder="Buscar persona, estación o grupo" class="w-full" autofocus />
      </IconField>

      <div class="destinos">
        <p v-if="cargando" class="p-4 text-center text-sm text-muted-color">Cargando contactos…</p>
        <button v-for="d in visibles" v-else :key="d.clave" type="button" class="destino" :aria-pressed="esElegido(d)" @click="alternar(d)">
          <ChatAvatar :id="d.id" :nombre="d.nombre" :grupo="d.grupo" :ambito="d.ambito" tamano="sm" />
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm">{{ d.nombre }}</span>
            <span class="block truncate text-xs text-muted-color">{{ d.detalle }}</span>
          </span>
          <Checkbox :model-value="esElegido(d)" binary readonly :tabindex="-1" />
        </button>
        <p v-if="!cargando && !visibles.length" class="p-4 text-center text-sm text-muted-color">Sin resultados.</p>
      </div>

      <Textarea v-model="nota" rows="2" auto-resize placeholder="Nota (opcional)" :maxlength="4000" />
    </div>

    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
      <Button :label="elegidos.length > 1 ? `Enviar a ${elegidos.length}` : 'Enviar'" :loading="enviando" :disabled="!elegidos.length || !referencias.length" @click="enviar">
        <template #icon><icon name="send" size="1rem" color="text-current" class="mr-1.5" /></template>
      </Button>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { useChatStore } from '@/core/chat/store'
import { coincide } from '@/core/chat/modelo'
import type { Ambito, Referencia } from '@/core/chat/types'
import { notify } from '@/core/notify'
import ChatAvatar from './ChatAvatar.vue'

const props = defineProps<{ visible: boolean; referencias: Referencia[] }>()
const emit = defineEmits<{ 'update:visible': [boolean]; enviado: [canales: number[]] }>()

interface Destino {
  clave: string
  id: number
  nombre: string
  detalle: string
  grupo: boolean
  ambito: Ambito | null
}

const AMBITOS = { administracion: 'Administración', estacion: 'Estación', agencia: 'Agencia' } as const
const MAX_VISIBLES = 40

const chat = useChatStore()
const busqueda = ref('')
const nota = ref('')
const elegidos = ref<Destino[]>([])
const cargando = ref(false)
const enviando = ref(false)

const titulo = computed(() => (props.referencias.length === 1 ? 'Enviar por chat' : `Enviar ${props.referencias.length} registros por chat`))
const etiqueta = (tipo: string) => (tipo === 'BoletoAsiento' ? 'Boleto' : tipo.replace(/([a-z])([A-Z])/g, '$1 $2'))

/** Grupos y conversaciones recientes primero; luego el resto del directorio. */
const destinos = computed<Destino[]>(() => {
  const grupos = chat.canales
    .filter((c) => c.tipo === 'grupo')
    .map((c) => ({ clave: `c${c.id}`, id: c.id, nombre: c.nombre, detalle: `Grupo · ${c.miembros.length} miembros`, grupo: true, ambito: null }))
  const recientes = chat.canales.filter((c) => c.tipo === 'directo' && c.contacto).map((c) => c.contacto!.id)
  const orden = new Map(recientes.map((id, i) => [id, i]))
  const personas = [...(chat.contactos ?? [])]
    .sort((a, b) => (orden.get(a.id) ?? Infinity) - (orden.get(b.id) ?? Infinity))
    .map((p) => ({ clave: `u${p.id}`, id: p.id, nombre: p.nombre, detalle: p.lugar ?? AMBITOS[p.ambito], grupo: false, ambito: p.ambito }))
  return [...grupos, ...personas]
})
const visibles = computed(() => destinos.value.filter((d) => !busqueda.value || coincide(`${d.nombre} ${d.detalle}`, busqueda.value)).slice(0, MAX_VISIBLES))

const esElegido = (d: Destino) => elegidos.value.some((e) => e.clave === d.clave)
function alternar(d: Destino) {
  elegidos.value = esElegido(d) ? elegidos.value.filter((e) => e.clave !== d.clave) : [...elegidos.value, d]
}

async function preparar() {
  busqueda.value = ''
  nota.value = ''
  elegidos.value = []
  cargando.value = !chat.contactos
  try {
    await Promise.all([chat.listo ? null : chat.refrescar(true), chat.cargarContactos()])
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    cargando.value = false
  }
}

async function enviar() {
  enviando.value = true
  try {
    const canales = await chat.compartir(
      { usuarios: elegidos.value.filter((d) => !d.grupo).map((d) => d.id), canales: elegidos.value.filter((d) => d.grupo).map((d) => d.id) },
      props.referencias,
      nota.value,
    )
    notify.success(elegidos.value.length === 1 ? `Enviado a ${elegidos.value[0]!.nombre}` : `Enviado a ${elegidos.value.length} destinatarios`)
    emit('update:visible', false)
    emit('enviado', canales)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    enviando.value = false
  }
}
</script>

<style scoped>
.ref {
  padding: 0.15rem 0.55rem;
  border-radius: 0.4rem;
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--p-surface-700);
  background: var(--p-surface-100);
}
.elegido {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  padding: 0.15rem 0.2rem 0.15rem 0.6rem;
  border-radius: 999px;
  font-size: 0.8rem;
  color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  button {
    display: grid;
    place-items: center;
    min-width: 1.25rem;
    min-height: 1.25rem;
    border-radius: 50%;
  }
}
.destinos {
  max-height: min(18rem, 40dvh);
  overflow-y: auto;
  margin: 0 -0.5rem;
}
.destino {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  width: 100%;
  padding: 0.45rem 0.5rem;
  border-radius: 0.6rem;
  text-align: left;
  cursor: pointer;
  &:hover {
    background: var(--p-surface-100);
  }
}
</style>
