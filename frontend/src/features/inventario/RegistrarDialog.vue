<!--
  Registrar un cambio en un almacén (ADR-071): una entrada (lo comprado, con
  su costo y su factura), una salida (lo que se gastó), un envío a otro
  almacén de la empresa (queda en camino hasta que lo reciben) o un conteo
  (lo que hay de verdad; se anota la diferencia). Lo que se ofrece sale de
  lo que puede en ese almacén; el backend vuelve a decidirlo.
-->
<template>
  <Dialog :visible="!!almacen" modal :header="`Registrar en ${almacen?.nombre ?? ''}`" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div v-if="almacen" class="flex flex-col gap-3">
      <SelectButton v-model="b.tipo" :options="opciones" option-label="etiqueta" option-value="valor" :allow-empty="false" aria-label="Qué se registra" />
      <p class="m-0 text-xs text-muted-color">{{ REGISTRABLES[b.tipo].ayuda }}</p>

      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Artículo</span>
        <Select v-model="b.articulo" :options="articulos" option-label="nombre" option-value="id" filter :filter-fields="['nombre', 'codigo', 'categoria']" placeholder="Elija el artículo" fluid>
          <template #option="{ option }">
            <div class="flex flex-col">
              <span>{{ option.nombre }}</span>
              <span class="text-xs text-muted-color">{{ option.codigo }} · en {{ option.unidad }}</span>
            </div>
          </template>
        </Select>
        <span v-if="actual" class="text-xs text-muted-color">Hay {{ cantidad(actual.cantidad) }} {{ unidad }} a {{ quetzales(actual.costoPromedio, true) }} c/u.</span>
      </label>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">{{ b.tipo === "ajuste" ? "Lo contado" : "Cantidad" }}<template v-if="unidad"> ({{ unidad }})</template></span>
          <InputText v-model="b.cantidad" inputmode="decimal" fluid autofocus />
        </label>
        <label v-if="b.tipo === 'entrada'" class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Costo por unidad (Q)</span>
          <InputText v-model="b.costo" inputmode="decimal" fluid />
        </label>
        <label v-if="b.tipo === 'envio'" class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">A dónde</span>
          <Select v-model="b.destino" :options="destinos" option-label="nombre" option-value="id" :loading="cargandoDestinos" placeholder="Elija el almacén" empty-message="No hay otro almacén activo de la empresa." fluid />
        </label>
      </div>
      <p v-if="b.tipo === 'ajuste' && diferencia !== null" class="m-0 text-sm">
        Diferencia con el sistema: <strong>{{ conSigno(String(diferencia)) }} {{ unidad }}</strong>
      </p>

      <label v-if="b.tipo !== 'ajuste'" class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">{{ b.tipo === "entrada" ? "Factura (opcional)" : "Documento o guía (opcional)" }}</span>
        <InputText v-model="b.documento" maxlength="80" fluid />
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">{{ b.tipo === "ajuste" ? "Por qué" : "Motivo (opcional)" }}</span>
        <Textarea v-model="b.motivo" rows="2" auto-resize maxlength="500" fluid :placeholder="PISTAS[b.tipo]" />
      </label>
    </div>
    <template #footer>
      <span v-if="falta && intentado" class="mr-auto self-center text-xs text-red-600">{{ falta }}</span>
      <Button label="Cancelar" severity="secondary" text @click="emit('cerrar')" />
      <Button label="Registrar" :loading="enviando" @click="enviar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { fetchDestinos, registrar } from "@/core/inventario/api";
import { borradorVacio, cantidad, conSigno, faltaParaRegistrar, quetzales, REGISTRABLES, registroDe, tiposPermitidos } from "@/core/inventario/reglas";
import type { Almacen, ArticuloBreve, Borrador, Existencia, Movimiento, PuedeEnAlmacen, TipoRegistrable } from "@/core/inventario/tipos";
import { notify } from "@/core/notify";

const props = defineProps<{
  almacen: (Almacen & { puede: PuedeEnAlmacen }) | null;
  articulos: ArticuloBreve[];
  existencias: Existencia[];
  inicial?: { tipo?: TipoRegistrable; articulo?: number } | null;
}>();
const emit = defineEmits<{ cerrar: []; registrado: [m: Movimiento] }>();

const PISTAS: Record<TipoRegistrable, string> = {
  entrada: "Compra local, donación…",
  salida: "Se instaló en el bus 120, se gastó en taquilla…",
  envio: "Para la avería del bus 120…",
  ajuste: "Conteo de fin de mes, merma, rotura…",
};

const b = ref<Borrador>(borradorVacio("salida"));
const enviando = ref(false);
const intentado = ref(false);
const destinos = shallowRef<Almacen[]>([]);
const cargandoDestinos = ref(false);

const permitidos = computed(() => (props.almacen ? tiposPermitidos(props.almacen.puede, props.almacen.activo) : []));
const opciones = computed(() => permitidos.value.map((valor) => ({ valor, etiqueta: REGISTRABLES[valor].etiqueta })));
const actual = computed(() => props.existencias.find((e) => e.articulo.id === b.value.articulo) ?? null);
const unidad = computed(() => props.articulos.find((a) => a.id === b.value.articulo)?.unidad ?? actual.value?.articulo.unidad ?? "");
const falta = computed(() => faltaParaRegistrar(b.value));
const diferencia = computed(() => {
  const contado = Number(b.value.cantidad.replace(/,/g, ""));
  if (b.value.cantidad.trim() === "" || !Number.isFinite(contado)) return null;
  return Math.round((contado - Number(actual.value?.cantidad ?? 0)) * 100) / 100;
});

watch(
  () => props.almacen,
  (a) => {
    if (!a) return;
    const tipo = props.inicial?.tipo && permitidos.value.includes(props.inicial.tipo) ? props.inicial.tipo : (permitidos.value[0] ?? "salida");
    b.value = borradorVacio(tipo, props.inicial?.articulo ?? null);
    intentado.value = false;
    destinos.value = [];
  },
);

watch(
  () => [b.value.tipo, props.almacen?.id] as const,
  async ([tipo, id]) => {
    if (tipo !== "envio" || !id || destinos.value.length) return;
    cargandoDestinos.value = true;
    try {
      destinos.value = await fetchDestinos(id);
    } catch (e) {
      notify.error(e instanceof Error ? e.message : String(e));
    } finally {
      cargandoDestinos.value = false;
    }
  },
);

async function enviar() {
  intentado.value = true;
  if (!props.almacen || falta.value) return;
  enviando.value = true;
  try {
    const m = await registrar(registroDe(props.almacen.id, b.value));
    notify.success(`${m.etiqueta} registrada: quedan ${cantidad(m.saldo)} ${m.articulo.unidad} de «${m.articulo.nombre}».`);
    emit("registrado", m);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  } finally {
    enviando.value = false;
  }
}
</script>
