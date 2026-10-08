<!--
  Aviso de mensaje nuevo fuera de la conversación en pantalla: tarjeta flotante
  que lleva a la conversación. Con la pestaña oculta y permiso del navegador,
  además, notificación del sistema.
-->
<template>
  <Transition name="aviso">
    <button v-if="visible && entrante" :key="entrante.n" type="button" class="aviso" @click="abrir">
      <ChatAvatar :id="entrante.autor?.id ?? 0" :nombre="entrante.autor?.nombre ?? 'Sistema'" :sistema="!entrante.autor" :ambito="entrante.autor?.ambito" />
      <span class="min-w-0 flex-1 text-left">
        <span class="aviso__quien">{{ entrante.autor?.nombre ?? 'Aviso del sistema' }}<span v-if="grupo" class="aviso__grupo"> · {{ grupo }}</span></span>
        <span class="aviso__texto">{{ entrante.extracto }}</span>
      </span>
      <span class="aviso__cerrar tap-target" role="button" aria-label="Descartar" @click.stop="visible = false"><icon name="close" size="1rem" color="text-current" /></span>
    </button>
  </Transition>
</template>

<script setup lang="ts">
import { useChatStore } from '@/core/chat/store'
import ChatAvatar from '@/shared/chat/ChatAvatar.vue'

const DURACION_MS = 7000

const chat = useChatStore()
const router = useRouter()
const entrante = computed(() => chat.entrante)
const visible = ref(false)
const grupo = computed(() => {
  const c = entrante.value ? chat.canal(entrante.value.canal) : null
  return c?.tipo === 'grupo' ? c.nombre : null
})
let temporizador: ReturnType<typeof setTimeout> | undefined

watch(
  () => entrante.value?.n,
  (n) => {
    if (!n || !entrante.value) return
    visible.value = true
    clearTimeout(temporizador)
    temporizador = setTimeout(() => (visible.value = false), DURACION_MS)
    notificarSistema()
  },
)

function notificarSistema() {
  const e = entrante.value
  if (!e || document.visibilityState === 'visible' || typeof Notification === 'undefined' || Notification.permission !== 'granted') return
  const n = new Notification(e.autor?.nombre ?? 'Mensaje nuevo', { body: e.extracto, tag: `chat-${e.canal}` })
  n.onclick = () => {
    window.focus()
    void router.push({ name: 'chat', params: { canal: e.canal } })
    n.close()
  }
}

function abrir() {
  visible.value = false
  if (entrante.value) void router.push({ name: 'chat', params: { canal: entrante.value.canal } })
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
