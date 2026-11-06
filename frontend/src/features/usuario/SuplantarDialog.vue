<!--
  «Entrar como este usuario» desde el listado de usuarios (`listActions`):
  solo para el administrador del sistema (`iam.suplantar`). Pide el motivo si
  la configuración lo exige y avisa cuánto dura y qué pasa con el chat. Al
  entrar, la app se recarga como ese usuario; «Volver a mi cuenta» está en el
  aviso fijo de abajo.
-->
<template>
  <Dialog
    :visible="visible"
    modal
    header="Entrar como este usuario"
    class="w-[min(28rem,calc(100vw-1rem))]"
    @update:visible="emit('update:visible', $event)"
    @after-hide="emit('after-hide')"
  >
    <p class="mb-3 text-sm">
      Usuario <strong>{{ usuario.username }}</strong>
      <span v-if="nombre" class="text-muted-color"> · {{ nombre }}</span>
    </p>
    <Skeleton v-if="!config && !error" height="6rem" />
    <template v-else-if="config">
      <Message v-if="!config.activa" severity="warn" :closable="false" class="mb-3"
        >La suplantación está desactivada en la configuración.</Message
      >
      <ul class="mb-3 ml-4 list-disc text-sm text-muted-color">
        <li>Verá y hará exactamente lo que puede este usuario, con sus roles y alcance.</li>
        <li>Dura {{ config.duracionMinutos }} minutos; luego vuelve sola a su cuenta.</li>
        <li>Chat y correo: {{ etiquetaChat }}.</li>
        <li v-if="config.avisarUsuario">Se le avisará al usuario en su chat.</li>
        <li>Todo lo que haga queda en la bitácora a su nombre.</li>
      </ul>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium"
          >Motivo<template v-if="!config.exigirMotivo"> (opcional)</template></span
        >
        <Textarea
          v-model="motivo"
          rows="2"
          auto-resize
          maxlength="255"
          placeholder="Por qué entra a su cuenta"
          fluid
        />
      </label>
    </template>
    <Message v-if="error" severity="error" :closable="false" class="mt-3">{{ error }}</Message>
    <div class="mt-4 flex justify-end gap-2">
      <Button
        type="button"
        label="Cancelar"
        severity="secondary"
        text
        @click="emit('update:visible', false)"
      />
      <Button
        label="Entrar"
        :loading="entrando"
        :disabled="!config?.activa || !motivoValido(motivo, config?.exigirMotivo ?? true)"
        @click="entrar"
      >
        <template #icon><icon name="switch-account" class="mr-1 text-inherit" /></template>
      </Button>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useSessionStore } from '@/core/auth/session'
import { fetchConfiguracionSuplantacion, mensajeDeError } from '@/core/suplantacion/api'
import { motivoValido } from '@/core/suplantacion/reglas'
import type { ConfiguracionSuplantacion } from '@/core/suplantacion/tipos'

interface UsuarioFila {
  id: string | number
  username?: string
  nombre?: string | null
  apellido?: string | null
}

const props = defineProps<{ item: unknown; visible: boolean }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; 'after-hide': [] }>()
const session = useSessionStore()

const usuario = computed(() => props.item as UsuarioFila)
const nombre = computed(() =>
  [usuario.value.nombre, usuario.value.apellido].filter(Boolean).join(' '),
)
const config = ref<ConfiguracionSuplantacion | null>(null)
const motivo = ref('')
const error = ref('')
const entrando = ref(false)
const etiquetaChat = computed(
  () =>
    config.value?.catalogo.chat
      .find((c) => c.valor === config.value?.chat)
      ?.etiqueta.toLowerCase() ?? '',
)

watch(
  () => props.visible,
  async (v) => {
    if (!v) return
    motivo.value = ''
    error.value = ''
    try {
      config.value = await fetchConfiguracionSuplantacion()
    } catch (e) {
      error.value = mensajeDeError(e)
    }
  },
  { immediate: true },
)

async function entrar() {
  entrando.value = true
  error.value = ''
  try {
    // Recarga la app como el usuario: no vuelve de aquí si sale bien.
    await session.suplantar(
      String(usuario.value.id).split('/').pop() ?? '',
      motivo.value.trim() || null,
    )
  } catch (e) {
    error.value = mensajeDeError(e)
    entrando.value = false
  }
}
</script>
