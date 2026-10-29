<!--
  Aprobar (con nota opcional) o rechazar (con motivo) una autorización
  (ADR-040). Aprobada, quien la pidió repite la operación y se usa sola.
-->
<template>
  <Dialog :visible="!!a" modal :header="como === 'aprobar' ? `Aprobar la autorización #${a?.id}` : `Rechazar la autorización #${a?.id}`" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div v-if="a" class="flex flex-col gap-3">
      <p class="m-0 text-sm">
        <strong>{{ a.solicitante.nombre }}</strong> pide: {{ queSePide(a).toLowerCase() }} ({{ a.dispensaNombre.toLowerCase() }}).
      </p>
      <p class="m-0 text-sm text-muted-color">«{{ a.motivo }}»</p>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">{{ como === 'aprobar' ? 'Nota (opcional)' : 'Motivo del rechazo' }}</span>
        <Textarea v-model="texto" rows="3" auto-resize maxlength="500" fluid autofocus />
      </label>
      <p v-if="como === 'aprobar'" class="m-0 text-xs text-muted-color">Sirve una sola vez y vence en 24 horas si no se usa.</p>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('cerrar')" />
      <Button
        :label="como === 'aprobar' ? 'Aprobar' : 'Rechazar'"
        :severity="como === 'aprobar' ? undefined : 'danger'"
        :disabled="como === 'rechazar' && !texto.trim()"
        :loading="enviando"
        @click="enviar"
      />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { aprobar, rechazar } from '@/core/autorizacion/api'
import { queSePide } from '@/core/autorizacion/reglas'
import type { Autorizacion } from '@/core/autorizacion/types'
import { notify } from '@/core/notify'

const props = defineProps<{ a: Autorizacion | null; como: 'aprobar' | 'rechazar' }>()
const emit = defineEmits<{ cerrar: []; resuelta: [a: Autorizacion] }>()

const texto = ref('')
const enviando = ref(false)
watch(
  () => props.a,
  () => (texto.value = ''),
)

async function enviar() {
  if (!props.a) return
  enviando.value = true
  try {
    const r = props.como === 'aprobar' ? await aprobar(props.a.id, texto.value) : await rechazar(props.a.id, texto.value.trim())
    notify.success(props.como === 'aprobar' ? `Autorización #${r.id} aprobada.` : `Autorización #${r.id} rechazada.`)
    emit('resuelta', r)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    enviando.value = false
  }
}
</script>
