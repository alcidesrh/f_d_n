<!--
  Salidas de un sentido (ida o regreso) para la fecha elegida, como
  acordeón: al tocar una se abre su croquis debajo. Días anterior/siguiente
  cambian la fecha de la búsqueda.
-->
<template>
  <section :aria-labelledby="`lista-${sentido}`" class="flex flex-col gap-3">
    <header class="flex flex-wrap items-end justify-between gap-2">
      <div class="min-w-0">
        <p
          class="m-0 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide"
          :class="sentido === 'ida' ? 'text-marca-700' : 'text-acento-700'"
        >
          <icon
            :name="sentido === 'ida' ? 'arrow-right' : 'arrow-back-up'"
            size="0.95rem"
          />{{ t(`salidas.${sentido}`) }}
        </p>
        <h2
          :id="`lista-${sentido}`"
          class="m-0 text-lg font-medium leading-snug md:text-xl"
        >
          <template v-if="nombres">{{ t("salidas.titulo", nombres) }}</template>
        </h2>
        <p class="m-0 text first-letter:uppercase font-bold">
          {{ diaLargo(dia, region) }}
        </p>
      </div>
      <div v-if="dia" class="flex gap-1">
        <Button
          size="small"
          severity="secondary"
          outlined
          :disabled="esPrimerDia"
          :aria-label="t('salidas.diaAnterior')"
          @click="mover(-1)"
        >
          <icon name="chevron-left" size="1rem" /><span
            class="hidden sm:inline"
            >{{ t("salidas.diaAnterior") }}</span
          >
        </Button>
        <Button
          size="small"
          severity="secondary"
          outlined
          :aria-label="t('salidas.diaSiguiente')"
          @click="mover(1)"
        >
          <span class="hidden sm:inline">{{ t("salidas.diaSiguiente") }}</span
          ><icon name="chevron-right" size="1rem" />
        </Button>
      </div>
    </header>

    <p
      v-if="sentido === 'regreso' && !viaje.busqueda.regreso"
      class="panel m-0 flex items-center gap-2 text-sm text-muted-color"
    >
      <icon name="calendar-repeat" />{{ t("salidas.elijaRegreso") }}
    </p>
    <template v-else-if="lista.cargando && !lista.salidas.length">
      <Skeleton v-for="n in 3" :key="n" height="7.5rem" border-radius="1rem" />
    </template>
    <Message v-else-if="lista.error" severity="error" :closable="false">
      {{ mensajeError }}
      <Button
        :label="t('comun.reintentar')"
        size="small"
        text
        class="ml-1"
        @click="viaje.cargar(sentido)"
      />
    </Message>
    <p
      v-else-if="!lista.salidas.length"
      class="panel m-0 flex items-start gap-2 text-sm text-muted-color"
    >
      <icon name="info" class="mt-0.5" />{{ t("salidas.ninguna") }}
    </p>
    <ul
      v-else
      class="m-0 flex list-none flex-col gap-3 p-0"
      :aria-busy="lista.cargando"
    >
      <li v-for="s in lista.salidas" :key="`${s.id}-${s.trayecto}`">
        <TarjetaSalida
          :s="s"
          :abierta="viaje.abierta[sentido] === s.id"
          :elegida="elegidaEn(s)"
          @alternar="alternar(s)"
        >
          <CroquisSalida :sentido="sentido" :salida="s" />
        </TarjetaSalida>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick } from "vue";
import { useI18n } from "vue-i18n";
import { mensajeDeError } from "@/errores";
import { ErrorPublico } from "@/api";
import { REGION, type Idioma } from "@/i18n";
import { diaISO, diaLargo, sumarDias } from "@/modelo";
import type { Salida } from "@/tipos";
import { useViaje, type Sentido } from "@/viaje";
import CroquisSalida from "./CroquisSalida.vue";
import TarjetaSalida from "./TarjetaSalida.vue";

const props = defineProps<{
  sentido: Sentido;
  nombres: { origen: string; destino: string } | null;
}>();
const { t, te, locale } = useI18n();
const viaje = useViaje();

const lista = computed(() => viaje.listas[props.sentido]);
const region = computed(() => REGION[locale.value as Idioma]);
const dia = computed(() =>
  props.sentido === "ida" ? viaje.busqueda.fecha : viaje.busqueda.regreso,
);
const minimo = computed(() =>
  props.sentido === "ida"
    ? diaISO(new Date())
    : (viaje.busqueda.fecha ?? diaISO(new Date())),
);
const esPrimerDia = computed(() => !dia.value || dia.value <= minimo.value);
const mensajeError = computed(() => {
  const e = lista.value.error;
  return e
    ? mensajeDeError(new ErrorPublico(e.mensaje, e.codigo, 0), t, te).titulo
    : "";
});

function elegidaEn(s: Salida): string | null {
  const e = viaje.elegida[props.sentido];
  return e && e.salida.id === s.id && e.salida.trayecto === s.trayecto
    ? e.asientos.map((a) => a.numero).join(", ")
    : null;
}

async function alternar(s: Salida) {
  viaje.abrir(props.sentido, s.id);
  if (viaje.abierta[props.sentido] !== s.id) return;
  await nextTick();
  document
    .getElementById(`salida-${s.id}-${s.trayecto}-cab`)
    ?.scrollIntoView({ behavior: "smooth", block: "start" });
}

function mover(dias: number) {
  if (!dia.value) return;
  const nuevo = sumarDias(dia.value, dias);
  if (props.sentido === "ida") {
    viaje.busqueda.fecha = nuevo;
    if (viaje.busqueda.regreso && viaje.busqueda.regreso < nuevo)
      viaje.busqueda.regreso = nuevo;
  } else {
    viaje.busqueda.regreso = nuevo;
  }
}
</script>
