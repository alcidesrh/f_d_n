<!--
  Atender una afectación (ADR-070): corregir su detalle o darla por resuelta
  con una nota de qué se hizo. Resuelta, el centinela deja de avisar y quien
  la reportó recibe el aviso.
-->
<template>
  <Dialog :visible="!!a" modal :header="como === 'resolver' ? `Resolver la afectación #${a?.id}` : `Corregir la afectación #${a?.id}`" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div v-if="a" class="flex flex-col gap-3">
      <p class="m-0 text-sm">
        <strong>{{ a.tipo.nombre }}</strong> en {{ a.lugar }}.
      </p>
      <p v-if="como === 'resolver'" class="m-0 text-sm text-muted-color">«{{ a.descripcion }}»</p>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">{{ como === "resolver" ? "Qué se hizo (opcional)" : "Detalle" }}</span>
        <Textarea v-model="texto" rows="3" auto-resize maxlength="2000" fluid autofocus :placeholder="como === 'resolver' ? 'Se cambió el cabezal; se compró una nueva…' : undefined" />
      </label>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('cerrar')" />
      <Button :label="como === 'resolver' ? 'Resolver' : 'Guardar'" :disabled="como === 'editar' && !texto.trim()" :loading="enviando" @click="enviar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { editar, resolver } from "@/core/afectacion/api";
import type { Afectacion } from "@/core/afectacion/tipos";
import { notify } from "@/core/notify";

const props = defineProps<{ a: Afectacion | null; como: "editar" | "resolver" }>();
const emit = defineEmits<{ cerrar: []; atendida: [a: Afectacion] }>();

const texto = ref("");
const enviando = ref(false);
watch(
  () => [props.a, props.como] as const,
  ([a, como]) => (texto.value = como === "editar" ? (a?.descripcion ?? "") : ""),
);

async function enviar() {
  if (!props.a) return;
  enviando.value = true;
  try {
    const r = props.como === "resolver" ? await resolver(props.a.id, texto.value) : await editar(props.a.id, texto.value.trim());
    notify.success(props.como === "resolver" ? `Afectación #${r.id} resuelta.` : `Afectación #${r.id} corregida.`);
    emit("atendida", r);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  } finally {
    enviando.value = false;
  }
}
</script>
