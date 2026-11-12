<!--
  Anular una encomienda que no ha viajado (en la bodega de su origen). Si se
  pagó, su factura se anula en el certificador (necesita el motivo). La que ya
  viajó se anula con una autorización fuera de estado (ADR-058): sin una
  aprobada, el bloqueo ofrece pedirla.
-->
<template>
  <Dialog :visible="visible" modal header="Anular encomienda" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="motivo = ''">
    <div v-if="encomienda" class="flex flex-col gap-3">
      <p class="m-0 text-sm">
        <b class="font-mono">{{ encomienda.codigo }}</b> · {{ encomienda.origen.nombre }} → {{ encomienda.destino.nombre }} · {{ encomienda.total.texto }}
      </p>
      <Message v-if="encomienda.fueraDeEstado" severity="warn" :closable="false">{{ encomienda.acciones.anular }} Se anula con una autorización.</Message>
      <Message v-if="encomienda.estadoFacturacion === 'certificada'" severity="warn" :closable="false">También se anula su factura en el certificador.</Message>
      <label class="flex flex-col gap-1">
        <span class="text-sm font-medium">Motivo</span>
        <Textarea v-model="motivo" rows="3" maxlength="255" auto-resize placeholder="P. ej. el cliente desistió del envío" />
      </label>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Anular" severity="danger" :disabled="motivo.trim().length < 5" :loading="enviando" @click="anular" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useAutorizacionesStore } from "@/core/autorizacion/store";
import { anularEncomienda } from "@/core/encomienda/api";
import type { FilaEncomienda } from "@/core/encomienda/types";
import { errorVenta } from "@/core/venta/api";
import { notify } from "@/core/notify";

const props = defineProps<{ visible: boolean; encomienda: FilaEncomienda | null }>();
const emit = defineEmits<{ "update:visible": [visible: boolean]; listo: [] }>();

const motivo = ref("");
const enviando = ref(false);

async function anular() {
  if (!props.encomienda) return;
  enviando.value = true;
  try {
    await anularEncomienda(props.encomienda.id, motivo.value.trim());
    notify.success(`Encomienda ${props.encomienda.codigo} anulada`);
    emit("listo");
    emit("update:visible", false);
  } catch (x) {
    // Bloqueada por una regla que admite autorización: se ofrece pedirla (y este diálogo se cierra).
    if (useAutorizacionesStore().ofrecer(x)) emit("update:visible", false);
    else notify.error(errorVenta(x)?.error ?? "No se pudo anular.");
  } finally {
    enviando.value = false;
  }
}
</script>
