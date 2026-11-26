<!-- "Seguridad" de Mi cuenta: cambiar la contraseña (`MiCuentaController`). -->
<template>
  <section>
    <h3 class="mb-3 text-sm font-semibold">Cambiar contraseña</h3>
    <FormKit :key="`clave-${claveForm}`" type="form" :actions="false" @submit="cambiarClave">
      <div class="grid grid-cols-1 gap-x-4 @xl:grid-cols-3">
        <FormKit type="Password" name="actual" label="Contraseña actual" validation="required" autocomplete="current-password" fluid />
        <FormKit type="Password" name="password" label="Nueva contraseña" validation="required" autocomplete="new-password" fluid />
        <FormKit
          type="Password"
          name="password_confirm"
          label="Repetir contraseña"
          validation="required|confirm"
          :validation-messages="{ confirm: 'Las contraseñas no coinciden.' }"
          autocomplete="new-password"
          fluid
        />
      </div>
      <div class="flex justify-end">
        <Button type="submit" label="Cambiar contraseña" :loading="cambiando" />
      </div>
    </FormKit>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { cambiarPassword } from '@/core/cuenta/api'
import { notify } from '@/core/notify'
import { mensajeDeError } from './secciones'

const cambiando = ref(false)
const claveForm = ref(0)

async function cambiarClave(v: { actual: string; password: string }) {
  cambiando.value = true
  try {
    await cambiarPassword({ actual: v.actual, password: v.password })
    claveForm.value++
    notify.success('Contraseña cambiada.')
  } catch (e) {
    notify.error(mensajeDeError(e, 'No se pudo cambiar la contraseña.'))
  } finally {
    cambiando.value = false
  }
}
</script>
