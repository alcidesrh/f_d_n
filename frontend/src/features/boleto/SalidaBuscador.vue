<!--
  Filtro de salida de los boletos: se escribe la ruta, una parada, el bus o
  el id de la salida (la flecha, sin escribir, ofrece las de hoy). Salen las
  más cercanas a ahora primero, en cualquier estado y de la empresa elegida.
  Borrar el texto suelta la salida.
-->
<template>
  <AutoComplete
    :model-value="modelValue"
    :suggestions="sugerencias"
    :option-label="etiqueta"
    :delay="300"
    dropdown
    dropdown-mode="current"
    force-selection
    placeholder="Salida: ruta, parada, bus o id"
    size="small"
    class="salida-buscador"
    input-class="w-full"
    aria-label="Salida"
    :loading="buscando"
    @complete="buscar($event.query)"
    @update:model-value="(v: unknown) => emit('update:modelValue', v && typeof v === 'object' ? (v as SalidaFila) : null)"
  >
    <template #option="{ option }">
      <div class="flex min-w-0 flex-col">
        <span class="truncate font-medium">{{ cuando(option) }} · {{ option.ruta.nombre }}</span>
        <span class="truncate text-xs text-muted-color">
          #{{ option.id }}<template v-if="option.bus"> · bus {{ option.bus.codigo }}</template><template v-if="option.empresa"> · {{ option.empresa.nombre }}</template> · {{ etiquetaEstado(option.estado).etiqueta }}
        </span>
      </div>
    </template>
    <template #empty>
      <span class="px-3 py-2 text-sm text-muted-color">{{ error || "Ninguna salida" }}</span>
    </template>
  </AutoComplete>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { consultaSalidas, porCercania } from "@/core/boleto/listado";
import { fetchSalidas } from "@/core/salida/api";
import { aDia, etiquetaEstado } from "@/core/salida/filtro";
import type { SalidaFila } from "@/core/salida/types";
import { fecha, hora } from "@/core/venta/modelo";

const props = defineProps<{ modelValue: SalidaFila | null; empresa: number | null; rango: { desde: string; hasta: string } | null }>();
const emit = defineEmits<{ "update:modelValue": [SalidaFila | null] }>();

const sugerencias = ref<SalidaFila[]>([]);
const buscando = ref(false);
const error = ref("");

const cuando = (s: SalidaFila) => `${fecha(s.fecha)} ${hora(s.fecha)}`;
const etiqueta = (s: SalidaFila) => `${cuando(s)} · ${s.ruta.nombre}`;

let pedido = 0;
async function buscar(texto: string) {
  const n = ++pedido;
  buscando.value = true;
  error.value = "";
  try {
    const r = await fetchSalidas(consultaSalidas(texto, props.empresa, aDia(new Date()), props.rango), true);
    if (n === pedido) sugerencias.value = porCercania(r.items, new Date()).slice(0, 30);
  } catch (e) {
    if (n === pedido) {
      sugerencias.value = [];
      error.value = e instanceof Error ? e.message : String(e);
    }
  } finally {
    if (n === pedido) buscando.value = false;
  }
}
</script>

<style scoped>
.salida-buscador {
  width: 100%;
  min-width: 0;
}
@container (width >= 36rem) {
  .salida-buscador {
    width: 22rem;
  }
}
</style>
