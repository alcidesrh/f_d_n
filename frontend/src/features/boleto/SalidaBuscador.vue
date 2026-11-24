<!--
  Filtro de salida de los boletos: busca entre las salidas que dejan los
  controles del calendario (fecha, días, hora, empresa y la ruta fijada en la
  leyenda) por ruta, parada, bus o id; la flecha, sin escribir, las ofrece
  todas. Las más cercanas a ahora primero. Borrar el texto suelta la salida.
-->
<template>
  <AutoComplete
    :model-value="modelValue"
    :suggestions="sugerencias"
    :option-label="etiqueta"
    :delay="150"
    dropdown
    force-selection
    placeholder="Salida: ruta, parada, bus o id"
    size="small"
    class="salida-buscador"
    input-class="w-full"
    aria-label="Salida"
    :loading="store.cargando"
    @complete="buscar($event.query)"
    @update:model-value="(v: unknown) => emit('update:modelValue', v && typeof v === 'object' ? (v as SalidaCalendario) : null)"
  >
    <template #option="{ option }">
      <div class="flex min-w-0 flex-col">
        <span class="truncate font-medium">{{ cuando(option) }} · {{ rutaDe(option) }}</span>
        <span class="truncate text-xs text-muted-color">{{ detalle(option) }}</span>
      </div>
    </template>
    <template #empty>
      <span class="px-3 py-2 text-sm text-muted-color">Ninguna salida con estos filtros</span>
    </template>
  </AutoComplete>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { buscarSalidas, porCercania } from "@/core/boleto/listado";
import { puntas } from "@/core/salida/calendario";
import { etiquetaEstado } from "@/core/salida/filtro";
import type { SalidaCalendario } from "@/core/salida/types";
import { fecha, hora } from "@/core/venta/modelo";
import { useCalendarioSalidasStore } from "@/shared/salida-calendario/store";

defineProps<{ modelValue: SalidaCalendario | null }>();
const emit = defineEmits<{ "update:modelValue": [SalidaCalendario | null] }>();

const store = useCalendarioSalidasStore();
const sugerencias = ref<SalidaCalendario[]>([]);

const cuando = (s: SalidaCalendario) => `${fecha(s.fecha)} ${hora(s.fecha)}`;
function rutaDe(s: SalidaCalendario): string {
  const r = store.rutaPorId.get(s.rutaId);
  return r ? `${r.nombre} (${puntas(r)})` : `Ruta ${s.rutaId}`;
}
function detalle(s: SalidaCalendario): string {
  const bus = s.busId !== null ? store.busPorId.get(s.busId)?.codigo : null;
  const empresa = s.empresaId !== null ? store.empresas.get(s.empresaId) : null;
  return [`#${s.id}`, bus ? `bus ${bus}` : null, empresa, etiquetaEstado(s.estado).etiqueta].filter(Boolean).join(" · ");
}
const etiqueta = (s: SalidaCalendario) => `${cuando(s)} · ${rutaDe(s)}`;
/** Lo que se busca: ruta con sus puntas (nombre y alias), bus, empresa e id. */
function describir(s: SalidaCalendario): string {
  const r = store.rutaPorId.get(s.rutaId);
  return [rutaDe(s), r?.origen, r?.destino, detalle(s), cuando(s)].filter(Boolean).join(" ");
}

function buscar(texto: string) {
  sugerencias.value = porCercania(buscarSalidas(store.visibles, texto, describir, store.fijada), new Date()).slice(0, 50);
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
