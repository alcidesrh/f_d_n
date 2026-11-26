<!--
  "Notificaciones" de Mi cuenta: los mensajes del chat en este dispositivo
  (`core/chat/push.ts`) y los reportes programados que recibe
  (`ReportesProgramados`, ADR-044).
-->
<template>
  <div class="flex flex-col gap-6">
    <section v-if="estadoPush !== 'no-disponible'">
      <div class="flex items-start justify-between gap-4">
        <label for="notificaciones-chat" class="min-w-0">
          <span class="block font-medium">Mensajes del chat en este dispositivo</span>
          <span class="block text-sm text-muted-color">
            {{
              estadoPush === 'bloqueadas'
                ? esAppNativa()
                  ? 'Están bloqueadas para esta app: permítalas en los ajustes del teléfono.'
                  : 'El navegador las tiene bloqueadas para este sitio: permítalas en su configuración.'
                : 'Avisa quién escribe y el inicio del mensaje aunque la app esté cerrada. Se desactivan al cerrar sesión.'
            }}
          </span>
        </label>
        <ToggleSwitch
          input-id="notificaciones-chat"
          :model-value="estadoPush === 'activas'"
          :disabled="estadoPush === 'bloqueadas' || cambiandoPush"
          @update:model-value="alternarPush"
        />
      </div>
    </section>
    <p v-else class="m-0 text-sm text-muted-color">Este navegador no puede mostrar avisos del chat con la app cerrada.</p>

    <ReportesProgramados />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { activarPush, desactivarPush, estadoPush, revisarPush } from '@/core/chat/push'
import { esAppNativa } from '@/core/chat/pushNativo'
import { notify } from '@/core/notify'
import ReportesProgramados from './ReportesProgramados.vue'
import { mensajeDeError } from './secciones'

const cambiandoPush = ref(false)

onMounted(() => void revisarPush().catch(() => undefined))

async function alternarPush(activar: boolean) {
  cambiandoPush.value = true
  try {
    if (!activar) await desactivarPush()
    else if ((await activarPush()) === 'activas') notify.success('Notificaciones activadas en este dispositivo.')
  } catch (e) {
    notify.error(mensajeDeError(e, 'No se pudieron cambiar las notificaciones.'))
  } finally {
    cambiandoPush.value = false
  }
}
</script>
