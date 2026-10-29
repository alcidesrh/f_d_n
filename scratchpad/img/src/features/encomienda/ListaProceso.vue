<!--
  Lista marcable de encomiendas en «Procesar por salida»: en bodega
  (resalta las que esta salida lleva a su destino) o a bordo (resalta las que
  bajan aquí). Marcar todas / ninguna arriba.
-->
<template>
  <div class="flex flex-col gap-1">
    <label class="flex items-center gap-2 px-1 text-sm text-muted-color">
      <Checkbox :model-value="todas" :indeterminate="algunas && !todas" binary @update:model-value="alternarTodas" />
      Todas
    </label>
    <ul class="m-0 flex max-h-[28rem] list-none flex-col gap-1 overflow-y-auto p-0">
      <li v-for="e in filas" :key="e.id">
        <label class="fila" :class="{ 'fila-destacada': destacada(e), 'fila-on': modelValue.includes(e.id) }">
          <Checkbox :model-value="modelValue.includes(e.id)" binary @update:model-value="alternar(e.id)" />
          <span class="min-w-0">
            <span class="flex items-baseline gap-2">
              <b class="font-mono">{{ e.codigo }}</b>
              <span class="truncate">→ {{ e.destino.nombre }}</span>
            </span>
            <span class="block truncate text-xs text-muted-color">{{ e.bultos }} {{ e.bultos === 1 ? "bulto" : "bultos" }} · {{ e.contenido }} · {{ e.destinatario.nombre }}</span>
          </span>
          <Tag v-if="modo === 'bordo' && e.bajaAqui" value="Baja aquí" severity="contrast" class="shrink-0" />
          <Tag v-else-if="modo === 'bodega' && !e.llegaADestino" value="Transbordo" severity="warn" class="shrink-0" />
          <Tag v-if="e.porCobrar" value="Por cobrar" severity="secondary" class="shrink-0" />
        </label>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import type { FilaProceso } from "@/core/encomienda/types";

const props = defineProps<{ filas: FilaProceso[]; modelValue: number[]; modo: "bodega" | "bordo" }>();
const emit = defineEmits<{ "update:modelValue": [ids: number[]] }>();

const ids = computed(() => props.filas.map((e) => e.id));
const todas = computed(() => ids.value.length > 0 && ids.value.every((id) => props.modelValue.includes(id)));
const algunas = computed(() => ids.value.some((id) => props.modelValue.includes(id)));
const destacada = (e: FilaProceso) => (props.modo === "bordo" ? e.bajaAqui : e.llegaADestino);

function alternar(id: number) {
  emit("update:modelValue", props.modelValue.includes(id) ? props.modelValue.filter((x) => x !== id) : [...props.modelValue, id]);
}
function alternarTodas(si: boolean) {
  const fuera = props.modelValue.filter((id) => !ids.value.includes(id));
  emit("update:modelValue", si ? [...fuera, ...ids.value] : fuera);
}
</script>

<style scoped>
.fila {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
  cursor: pointer;
}
.fila-destacada {
  border-left: 3px solid var(--p-primary-color);
}
.fila-on {
  background: var(--p-highlight-background);
}
</style>
