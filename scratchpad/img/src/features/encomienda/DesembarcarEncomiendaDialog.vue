<!--
  Desembarcar una encomienda que va en un bus: queda en la bodega de la
  estación del usuario (de oficina, la que elija; debe ser una parada de su
  salida). En su destino queda lista para entregar; en otra, para transbordo.
-->
<template>
  <Dialog :visible="visible" modal header="Desembarcar encomienda" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="estacion = estacionUsuario?.id ?? encomienda?.destino.id ?? null">
    <div v-if="encomienda" class="flex flex-col gap-3">
      <p class="m-0 text-sm">
        <b class="font-mono">{{ encomienda.codigo }}</b> · {{ encomienda.origen.nombre }} → {{ encomienda.destino.nombre }}
        <template v-if="encomienda.salida"> · salida {{ encomienda.salida.id }}</template>
      </p>
      <label v-if="!estacionUsuario" class="flex flex-col gap-1">
        <span class="text-sm font-medium">Estación donde se baja</span>
        <estacion-select v-model="estacion" :estaciones="estaciones" :show-clear="false" placeholder="Estación" />
      </label>
      <p v-else class="m-0">Se desembarca en <b>{{ estacionUsuario.nombre }}</b>.</p>
      <Message v-if="estacion && estacion !== encomienda.destino.id" severity="warn" :closable="false">No es su destino: quedará en bodega para embarcarla en otra salida (transbordo).</Message>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Desembarcar" :disabled="!estacion" :loading="enviando" @click="desembarcar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { desembarcarEncomiendas } from "@/core/encomienda/api";
import type { EstacionOpcion, FilaEncomienda } from "@/core/encomienda/types";
import { errorVenta } from "@/core/venta/api";
import { notify } from "@/core/notify";

const props = defineProps<{ visible: boolean; encomienda: FilaEncomienda | null; estacionUsuario: EstacionOpcion | null; estaciones: EstacionOpcion[] }>();
const emit = defineEmits<{ "update:visible": [visible: boolean]; listo: [] }>();

const estacion = ref<number | null>(null);
const enviando = ref(false);

async function desembarcar() {
  if (!props.encomienda || !estacion.value) return;
  enviando.value = true;
  try {
    const r = await desembarcarEncomiendas([props.encomienda.id], props.estacionUsuario ? null : estacion.value);
    if (r.fallidas.length) {
      notify.error(r.fallidas[0]?.error ?? "No se pudo.");
      return;
    }
    notify.success(`Encomienda ${props.encomienda.codigo} desembarcada`);
    emit("listo");
    emit("update:visible", false);
  } catch (x) {
    notify.error(errorVenta(x)?.error ?? "No se pudo desembarcar.");
  } finally {
    enviando.value = false;
  }
}
</script>
