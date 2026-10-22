<!--
  Pedir una autorización (ADR-040): se abre solo cuando una operación quedó
  bloqueada por una regla que admite excepción (`useAutorizacionesStore().ofrecer(error)`)
  o desde un botón que la ofrece directo. Muestra qué se bloqueó y por qué,
  qué se pide, y el motivo; si ya hay una pendiente para eso, lo dice. Lo
  monta el shell una vez. Aprobada, la operación se repite y la usa sola.
-->
<template>
  <Dialog :visible="!!ofrecida" modal header="Pedir autorización" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && cerrar()">
    <div v-if="ofrecida" class="flex flex-col gap-4">
      <div class="sa-bloqueo">
        <icon :name="DISPENSAS[a!.dispensa]?.icono ?? 'block'" class="sa-bloqueo__icono" />
        <div class="flex flex-col gap-1">
          <strong class="text-sm">{{ ofrecida.mensaje }}</strong>
          <span class="text-xs text-muted-color">{{ DISPENSAS[a!.dispensa]?.explicacion }}</span>
        </div>
      </div>

      <dl class="sa-pide">
        <dt>Se pide</dt>
        <dd>{{ queSePide(a!) }}</dd>
        <dt>Regla</dt>
        <dd>{{ a!.dispensaNombre }}</dd>
        <template v-if="a!.sujeto === 'boletos'">
          <dt>Boletos</dt>
          <dd class="tabular-nums">{{ a!.ids.join(', ') }}</dd>
        </template>
      </dl>

      <Message v-if="a!.pendiente" severity="info" :closable="false">
        Ya pidió la autorización #{{ a!.pendiente }} para esto; espere a que la resuelvan.
        <RouterLink :to="{ name: 'autorizaciones', query: { id: a!.pendiente } }" class="underline" @click="cerrar">Ver la solicitud</RouterLink>
      </Message>
      <template v-else>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Motivo</span>
          <Textarea v-model="motivo" rows="3" auto-resize maxlength="500" placeholder="Por qué hace falta, p. ej. «El pasajero perdió el bus por un accidente en la ruta»" fluid autofocus />
        </label>
        <p class="m-0 text-xs text-muted-color">
          La resuelve quien aprueba autorizaciones en esta empresa o estación; le avisamos por el chat. Aprobada, repita la operación: la autorización se usa una vez y vence en 24 horas.
        </p>
      </template>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="cerrar" />
      <Button v-if="!a?.pendiente" label="Pedir autorización" :disabled="!motivo.trim()" :loading="enviando" @click="enviar">
        <template #icon><icon name="send" class="mr-1" /></template>
      </Button>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { solicitar } from '@/core/autorizacion/api'
import { DISPENSAS, queSePide } from '@/core/autorizacion/reglas'
import { useAutorizacionesStore } from '@/core/autorizacion/store'
import { notify } from '@/core/notify'

const store = useAutorizacionesStore()
const ofrecida = computed(() => store.ofrecida)
const a = computed(() => store.ofrecida?.autorizable ?? null)
const motivo = ref('')
const enviando = ref(false)

watch(ofrecida, () => (motivo.value = ''))

function cerrar() {
  store.cerrar()
}

async function enviar() {
  if (!a.value) return
  enviando.value = true
  try {
    const s = await solicitar(a.value, motivo.value)
    notify.success(`Autorización #${s.id} pedida. Le avisaremos por el chat cuando la resuelvan.`)
    store.alAvisar()
    cerrar()
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    enviando.value = false
  }
}
</script>

<style scoped>
.sa-bloqueo {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem;
  border-radius: var(--p-content-border-radius, 0.5rem);
  background: var(--c-warning-soft);
}
.sa-bloqueo__icono {
  flex: none;
  color: var(--c-warning-ink);
  font-size: 1.4rem;
}
.sa-pide {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0.25rem 1rem;
  margin: 0;
  font-size: 0.9rem;
}
.sa-pide dt {
  color: var(--p-text-muted-color);
}
.sa-pide dd {
  margin: 0;
}
</style>
