<!--
  Reportar una afectación (ADR-070): qué pasó (los tipos que el usuario puede
  reportar, los más urgentes primero), dónde (la estación, el bus o la
  empresa, según el tipo; propone su estación de trabajo) y el detalle.
  Creada, el centinela avisa enseguida a quien la atiende.
-->
<template>
  <Dialog :visible="visible" modal header="Reportar una afectación" class="w-[min(34rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <Skeleton v-if="!datos && !error" height="12rem" />
    <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-else-if="datos && datos.tipos.length === 0" severity="info" :closable="false"> Su usuario no puede reportar afectaciones. Pida a quien administra los accesos la capacidad «Reportar afectaciones de la estación» o «Reportar averías y desgaste de los buses». </Message>
    <div v-else-if="datos" class="flex flex-col gap-3">
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">¿Qué pasó?</span>
        <Select v-model="tipoId" :options="opcionesTipo" option-label="etiqueta" option-value="id" filter placeholder="Elija el tipo" fluid autofocus />
        <small v-if="tipo?.descripcion" class="text-muted-color">{{ tipo.descripcion }}</small>
      </label>

      <label v-if="tipo?.sujeto === 'bus'" class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Bus</span>
        <Select v-model="r.bus" :options="buses" option-label="etiqueta" option-value="id" filter placeholder="Elija el bus" fluid />
      </label>
      <label v-if="tipo && tipo.sujeto !== 'empresa'" class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">{{ tipo.sujeto === "bus" ? "Estación donde está (opcional)" : "Estación" }}</span>
        <Select v-model="r.estacion" :options="estaciones" option-label="nombre" option-value="id" filter :show-clear="tipo.sujeto === 'bus'" placeholder="Elija la estación" fluid />
      </label>
      <label v-if="tipo && tipo.sujeto !== 'bus' && empresas.length > 1" class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Empresa</span>
        <Select v-model="r.empresa" :options="empresas" option-label="nombre" option-value="id" placeholder="Elija la empresa" fluid />
      </label>

      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Detalle</span>
        <Textarea v-model="r.descripcion" rows="3" auto-resize maxlength="2000" fluid placeholder="Qué equipo, desde cuándo, qué se intentó" />
      </label>
      <p v-if="tipo" class="m-0 text-xs text-muted-color">Prioridad {{ PRIORIDADES[tipo.prioridad].etiqueta.toLowerCase() }}: se avisa enseguida a quien la atiende y, si no se resuelve, se insiste y se escala.</p>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('cerrar')" />
      <Button v-if="datos && datos.tipos.length > 0" v-tooltip.top="falta ?? undefined" label="Reportar" :disabled="!!falta" :loading="enviando" @click="enviar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { fetchParaReportar, reportar } from "@/core/afectacion/api";
import { busesPara, empresasPara, estacionesPara, faltaParaReportar, PRIORIDADES, propuesta, reporteDe } from "@/core/afectacion/reglas";
import type { Afectacion, ParaReportar } from "@/core/afectacion/tipos";
import { notify } from "@/core/notify";

const props = defineProps<{ visible: boolean }>();
const emit = defineEmits<{ cerrar: []; reportada: [a: Afectacion] }>();

const datos = shallowRef<ParaReportar | null>(null);
const error = ref("");
const enviando = ref(false);
const tipoId = ref<number | null>(null);
const r = reactive<{ descripcion: string; empresa: number | null; estacion: number | null; bus: number | null }>({ descripcion: "", empresa: null, estacion: null, bus: null });

const tipo = computed(() => datos.value?.tipos.find((t) => t.id === tipoId.value) ?? null);
const opcionesTipo = computed(() => (datos.value?.tipos ?? []).map((t) => ({ id: t.id, etiqueta: `${t.nombre} · ${PRIORIDADES[t.prioridad].etiqueta}` })));
const empresas = computed(() => (datos.value ? empresasPara(tipo.value, datos.value) : []));
const estaciones = computed(() => (datos.value ? (tipo.value?.sujeto === "bus" ? datos.value.estaciones : estacionesPara(tipo.value, datos.value)) : []));
const buses = computed(() => (datos.value ? busesPara(tipo.value, datos.value).map((b) => ({ id: b.id, etiqueta: b.matricula ? `${b.codigo} · ${b.matricula}` : b.codigo })) : []));
const falta = computed(() => (datos.value ? faltaParaReportar(tipo.value, r, datos.value) : "Cargando…"));

async function cargar() {
  error.value = "";
  try {
    datos.value = await fetchParaReportar();
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e);
  }
}

// Al cambiar de tipo se proponen de nuevo el lugar y la empresa; el detalle se conserva.
watch(tipo, (t) => {
  if (!datos.value) return;
  const p = propuesta(t, datos.value);
  r.empresa = p.empresa;
  r.estacion = t?.sujeto === "bus" ? (datos.value.estacion?.id ?? null) : p.estacion;
  r.bus = null;
});

watch(
  () => props.visible,
  (v) => {
    if (!v) return;
    tipoId.value = null;
    Object.assign(r, { descripcion: "", empresa: null, estacion: null, bus: null });
    void cargar();
  },
  { immediate: true },
);

async function enviar() {
  if (!tipo.value || falta.value) return;
  enviando.value = true;
  try {
    const a = await reportar(reporteDe(tipo.value, r));
    notify.success(`Afectación #${a.id} reportada: ya se avisa a quien la atiende.`);
    emit("reportada", a);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  } finally {
    enviando.value = false;
  }
}
</script>
