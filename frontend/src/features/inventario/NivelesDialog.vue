<!--
  Los niveles de un artículo en un almacén (ADR-071): debajo del mínimo se
  crea la afectación del faltante; en el punto de reorden o debajo, toca
  reponer; el máximo es hasta dónde llenar. Vacío = sin poner (el mínimo
  vacío es cero: sin alerta).
-->
<template>
  <Dialog :visible="!!existencia" modal :header="`Niveles de «${existencia?.articulo.nombre ?? ''}»`" class="w-[min(28rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div v-if="existencia" class="flex flex-col gap-3">
      <p class="m-0 text-sm text-muted-color">En {{ almacen?.nombre }} hay {{ cantidad(existencia.cantidad) }} {{ existencia.articulo.unidad }}.</p>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Mínimo</span>
          <InputText v-model="n.minimo" inputmode="decimal" fluid autofocus />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Punto de reorden</span>
          <InputText v-model="n.puntoReorden" inputmode="decimal" fluid />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Máximo</span>
          <InputText v-model="n.maximo" inputmode="decimal" fluid />
        </label>
      </div>
      <p class="m-0 text-xs text-muted-color">Debajo del mínimo se avisa a quien atiende el faltante. En el punto de reorden aparece en «Por reponer». Se repone hasta el máximo.</p>
      <Message v-if="falta" severity="warn" size="small" :closable="false">{{ falta }}</Message>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('cerrar')" />
      <Button label="Guardar" :disabled="!!falta" :loading="enviando" @click="enviar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { guardarNiveles } from "@/core/inventario/api";
import { cantidad, faltaEnNiveles, nivelesDe } from "@/core/inventario/reglas";
import type { Almacen, Existencia, Niveles } from "@/core/inventario/tipos";
import { notify } from "@/core/notify";

const props = defineProps<{ almacen: Almacen | null; existencia: Existencia | null }>();
const emit = defineEmits<{ cerrar: []; guardado: [e: Existencia] }>();

const n = ref<Niveles>(nivelesDe(null));
const enviando = ref(false);
const falta = computed(() => faltaEnNiveles(n.value));

watch(
  () => props.existencia,
  (e) => (n.value = nivelesDe(e)),
);

async function enviar() {
  if (!props.almacen || !props.existencia || falta.value) return;
  enviando.value = true;
  try {
    const e = await guardarNiveles(props.almacen.id, props.existencia.articulo.id, n.value);
    notify.success(`Niveles de «${e.articulo.nombre}» guardados.`);
    emit("guardado", e);
  } catch (err) {
    notify.error(err instanceof Error ? err.message : String(err));
  } finally {
    enviando.value = false;
  }
}
</script>
