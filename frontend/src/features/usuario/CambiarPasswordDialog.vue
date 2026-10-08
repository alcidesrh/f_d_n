<!--
  "Cambiar contraseña" desde el listado de usuarios (`listActions`). Permiso
  `usuario.editar` (`CambiarPasswordController`); al cambiarla se cierran las
  sesiones abiertas de ese usuario.
-->
<template>
  <Dialog
    :visible="visible"
    modal
    header="Cambiar contraseña"
    class="w-[min(26rem,calc(100vw-1rem))]"
    @update:visible="emit('update:visible', $event)"
    @after-hide="emit('after-hide')"
  >
    <p class="mb-4 text-sm">
      Usuario <strong>{{ usuario.username }}</strong>
      <span v-if="nombre" class="text-muted-color"> · {{ nombre }}</span>
    </p>
    <div class="form-fluid">
      <FormKit :key="clave" type="form" :actions="false" @submit="guardar">
        <FormKit
          type="Password"
          name="password"
          label="Nueva contraseña"
          :validation="`required|length:${MINIMO}`"
          :validation-messages="{ length: `Al menos ${MINIMO} caracteres.` }"
          autocomplete="new-password"
          fluid
        />
        <FormKit
          type="Password"
          name="password_confirm"
          label="Repetir contraseña"
          validation="required|confirm"
          :validation-messages="{ confirm: 'Las contraseñas no coinciden.' }"
          autocomplete="new-password"
          fluid
        />
        <div class="mt-2 flex justify-end gap-2">
          <Button
            type="button"
            label="Cancelar"
            severity="secondary"
            text
            @click="emit('update:visible', false)"
          />
          <Button type="submit" label="Guardar" :loading="guardando" />
        </div>
      </FormKit>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { http } from '@/core/http'
import { notify } from '@/core/notify'

/** Igual que `CambiarPasswordController::MINIMO`. */
const MINIMO = 6

interface UsuarioFila {
  id: string | number
  username?: string
  nombre?: string | null
  apellido?: string | null
}

const props = defineProps<{ item: unknown; visible: boolean }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; 'after-hide': [] }>()

const usuario = computed(() => props.item as UsuarioFila)
const nombre = computed(() =>
  [usuario.value.nombre, usuario.value.apellido].filter(Boolean).join(' '),
)
const guardando = ref(false)
const clave = ref(0)

// Formulario vacío cada vez que se abre.
watch(
  () => props.visible,
  (v) => v && clave.value++,
)

async function guardar(v: { password: string }) {
  const id = String(usuario.value.id).split('/').pop()
  guardando.value = true
  try {
    const r = await http.post<{ sesionesCerradas: number }>(`/usuarios/${id}/password`, {
      password: v.password,
    })
    notify.success(
      r.sesionesCerradas
        ? 'Contraseña cambiada. Se cerraron sus sesiones abiertas.'
        : 'Contraseña cambiada.',
    )
    emit('update:visible', false)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo cambiar la contraseña.')
  } finally {
    guardando.value = false
  }
}
</script>
