<!--
  Aviso de mensaje nuevo fuera de la conversación en pantalla (o de un correo
  nuevo, ADR-048): tarjeta flotante que lleva a la conversación o al correo. Con la pestaña oculta y permiso del navegador,
  además, notificación del sistema. Con la ventana del chat abierta (esquina o
  flotante, aunque esté minimizada) avisa ella: su barra cuenta y destella.
-->
<template>
  <Transition name="aviso">
    <button v-if="visible && actual" :key="actual.clave" type="button" class="aviso" @click="abrir">
      <span v-if="actual.tipo === 'correo'" class="aviso__correo" aria-hidden="true"><icon name="mail-outline" size="1.25rem" color="text-current" /></span>
      <ChatAvatar v-else-if="entrante" :id="entrante.autor?.id ?? 0" :nombre="entrante.autor?.nombre ?? 'Sistema'" :sistema="!entrante.autor" :ambito="entrante.autor?.ambito" :foto="entrante.autor?.foto" />
      <span class="min-w-0 flex-1 text-left">
        <span class="aviso__quien">{{ actual.titulo }}<span v-if="actual.detalle" class="aviso__grupo"> · {{ actual.detalle }}</span></span>
        <span class="aviso__texto">{{ actual.texto }}</span>
      </span>
      <span class="aviso__cerrar tap-target" role="button" aria-label="Descartar" @click.stop="visible = false"><icon name="close" size="1rem" color="text-current" /></span>
    </button>
  </Transition>
</template>

<script setup lang="ts">
import { useChatStore } from '@/core/chat/store'
import { useCorreoStore } from '@/core/correo/store'
import ChatAvatar from '@/shared/chat/ChatAvatar.vue'
import { useVentanaChat } from './ventana'

const DURACION_MS = 7000

const chat = useChatStore()
const correo = useCorreoStore()
const ventana = useVentanaChat()
const router = useRouter()
const entrante = computed(() => chat.entrante)
const visible = ref(false)
/** Lo último que llegó, del chat o del correo. */
const actual = ref<{ tipo: 'chat' | 'correo'; clave: string; titulo: string; detalle: string | null; texto: string } | null>(null)
let temporizador: ReturnType<typeof setTimeout> | undefined

watch(
  () => entrante.value?.n,
  (n) => {
    const e = entrante.value
    if (!n || !e) return
    const c = chat.canal(e.canal)
    actual.value = { tipo: 'chat', clave: `chat-${n}`, titulo: e.autor?.nombre ?? 'Aviso del sistema', detalle: c?.tipo === 'grupo' ? c.nombre : null, texto: e.extracto }
    mostrar(e.autor?.nombre ?? 'Mensaje nuevo', e.extracto, `chat-${e.canal}`)
  },
)

watch(
  () => correo.entrante?.n,
  (n) => {
    const e = correo.entrante
    if (!n || !e) return
    actual.value = { tipo: 'correo', clave: `correo-${n}`, titulo: e.de || 'Correo nuevo', detalle: 'Correo', texto: e.asunto || '(sin asunto)' }
    mostrar(`Correo de ${e.de}`, e.asunto, `correo-${e.id}`)
  },
)

function mostrar(titulo: string, cuerpo: string, etiqueta: string) {
  notificarSistema(titulo, cuerpo, etiqueta)
  if (ventana.esVentana && ventana.abierta) return
  visible.value = true
  clearTimeout(temporizador)
  temporizador = setTimeout(() => (visible.value = false), DURACION_MS)
}

function notificarSistema(titulo: string, cuerpo: string, etiqueta: string) {
  if (document.visibilityState === 'visible' || typeof Notification === 'undefined' || Notification.permission !== 'granted') return
  const destino = actual.value?.tipo
  const n = new Notification(titulo, { body: cuerpo, tag: etiqueta })
  n.onclick = () => {
    window.focus()
    ir(destino)
    n.close()
  }
}

function ir(tipo = actual.value?.tipo) {
  if (tipo === 'correo' && correo.entrante) void router.push({ name: 'correo', params: { correo: correo.entrante.id } })
  else if (entrante.value) void router.push({ name: 'chat', params: { canal: entrante.value.canal } })
}

function abrir() {
  visible.value = false
  ir()
}
onUnmounted(() => clearTimeout(temporizador))
</script>

<style scoped>
.aviso {
  position: fixed;
  right: 1rem;
  bottom: calc(1rem + env(safe-area-inset-bottom));
  z-index: 1100;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: min(22rem, calc(100vw - 2rem));
  padding: 0.75rem 0.5rem 0.75rem 0.85rem;
  border-radius: 1rem;
  background: var(--p-content-background);
  box-shadow: 0 10px 30px rgb(0 0 0 / 0.18), 0 0 0 1px var(--p-surface-200);
  cursor: pointer;
}
.aviso__quien,
.aviso__texto {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.aviso__quien {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-surface-800);
}
.aviso__grupo {
  font-weight: 400;
  color: var(--p-surface-500);
}
.aviso__texto {
  font-size: 0.82rem;
  color: var(--p-surface-600);
}
.aviso__correo {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  color: var(--p-primary-700);
  background: var(--p-primary-50);
}
.aviso__cerrar {
  display: grid;
  place-items: center;
  border-radius: 50%;
  color: var(--p-surface-500);
}
.aviso-enter-active,
.aviso-leave-active {
  transition: opacity 0.25s var(--ease), transform 0.25s var(--ease);
}
.aviso-enter-from,
.aviso-leave-to {
  opacity: 0;
  transform: translateY(0.75rem) scale(0.98);
}
</style>
