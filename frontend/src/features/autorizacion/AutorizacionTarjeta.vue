<!--
  Una autorización (ADR-040): qué se pide y por qué regla, quién y por qué
  la pidió, sobre qué boletos o salida, y en qué quedó (quién la resolvió,
  su nota o motivo, cuándo vence o cuándo se usó). Las acciones las decide
  el backend (`acciones`): aprobar/rechazar quien puede resolverla,
  retirar quien la pidió.
-->
<template>
  <article class="aut" :class="{ 'aut--destacada': destacada }" :style="{ '--acento': `var(--c-${acento})` }">
    <header class="flex flex-wrap items-start justify-between gap-2">
      <div class="flex min-w-0 items-start gap-2">
        <icon :name="DISPENSAS[a.dispensa]?.icono ?? 'verified-user-outline'" class="aut__icono" />
        <div class="min-w-0">
          <div class="font-semibold">
            {{ queSePide(a) }} <span class="font-normal text-muted-color">· #{{ a.id }}</span>
          </div>
          <div class="text-xs text-muted-color">
            {{ a.dispensaNombre }}<template v-if="a.empresa"> · {{ a.empresa.nombre }}</template
            ><template v-if="a.estacion"> · {{ a.estacion.nombre }}</template>
          </div>
        </div>
      </div>
      <div class="flex flex-col items-end gap-1">
        <Tag :value="ESTADOS[a.estado].etiqueta" :severity="ESTADOS[a.estado].severidad" />
        <span v-if="vigencia(a)" class="text-xs text-muted-color">{{ vigencia(a) }}</span>
      </div>
    </header>

    <blockquote class="aut__motivo">
      <span class="text-xs text-muted-color">{{ a.solicitante.nombre }} · {{ fecha(a.creadaEn) }}</span>
      <p class="m-0">{{ a.motivo }}</p>
    </blockquote>

    <ul v-if="a.detalle.boletos?.length" class="aut__registros">
      <li v-for="b in a.detalle.boletos" :key="b.id">
        <span class="font-medium tabular-nums">Boleto {{ b.id }}</span>
        <span>asiento {{ b.asiento }} · {{ b.trayecto }}</span>
        <span class="text-muted-color"
          >{{ b.ruta }}<template v-if="b.fecha"> · {{ fecha(b.fecha) }}</template> · salida {{ b.salida }}</span
        >
        <span v-if="b.pasajero" class="text-muted-color">{{ b.pasajero }}</span>
      </li>
    </ul>
    <p v-else-if="a.detalle.salida" class="m-0 text-sm">
      Salida {{ a.detalle.salida.id }} · {{ a.detalle.salida.ruta }}<template v-if="a.detalle.salida.fecha"> · {{ fecha(a.detalle.salida.fecha) }}</template
      ><template v-if="a.detalle.salida.bus"> · bus {{ a.detalle.salida.bus }}</template>
    </p>

    <p v-if="a.resueltaPor" class="m-0 text-sm">
      <span class="text-muted-color"
        >{{ a.estado === "rechazada" ? "Rechazó" : "Aprobó" }} {{ a.resueltaPor.nombre }}<template v-if="a.resueltaEn"> · {{ fecha(a.resueltaEn) }}</template
        >:</span
      >
      {{ a.respuesta ?? "sin nota" }}
    </p>
    <p v-if="a.usadaEn" class="m-0 text-xs text-muted-color">Usada el {{ fecha(a.usadaEn) }}.</p>
    <p v-else-if="a.estado === 'aprobada' && propia" class="m-0 text-sm">Ya puede repetir la operación: la autorización es de un solo uso.</p>

    <footer class="flex flex-wrap justify-end gap-2">
      <Button label="Bitácora" size="small" severity="secondary" text @click="emit('bitacora', a)">
        <template #icon><icon name="history" class="mr-1" /></template>
      </Button>
      <Button v-if="a.acciones.retirar" label="Retirar" size="small" severity="secondary" outlined @click="emit('retirar', a)" />
      <template v-if="a.acciones.aprobar">
        <Button label="Rechazar" size="small" severity="danger" outlined @click="emit('resolver', a, 'rechazar')" />
        <Button label="Aprobar" size="small" @click="emit('resolver', a, 'aprobar')">
          <template #icon><icon name="check" class="mr-1" /></template>
        </Button>
      </template>
    </footer>
  </article>
</template>

<script setup lang="ts">
import { DISPENSAS, ESTADOS, queSePide, vigencia } from "@/core/autorizacion/reglas";
import type { Autorizacion } from "@/core/autorizacion/types";

const props = defineProps<{ a: Autorizacion; destacada?: boolean; propia?: boolean }>();
const emit = defineEmits<{
  resolver: [a: Autorizacion, como: "aprobar" | "rechazar"];
  retirar: [a: Autorizacion];
  bitacora: [a: Autorizacion];
}>();

const ACENTOS: Record<string, string> = { pendiente: "warning", aprobada: "success", rechazada: "danger", usada: "info" };
const acento = computed(() => ACENTOS[props.a.estado] ?? "info");

const fecha = (iso: string) => new Intl.DateTimeFormat("es-GT", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit" }).format(new Date(iso));
</script>

<style scoped>
.aut {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.9rem 1rem;
  border: 1px solid var(--p-content-border-color);
  border-left: 3px solid var(--acento);
  border-radius: var(--p-content-border-radius, 0.75rem);
  background: var(--p-content-background);
}
.aut--destacada {
  box-shadow: 0 0 0 2px var(--p-primary-color);
}
.aut__icono {
  flex: none;
  margin-top: 0.1rem;
  color: var(--acento);
  font-size: 1.25rem;
}
.aut__motivo {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  margin: 0;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  background: color-mix(in srgb, var(--p-surface-500) 7%, transparent);
}
.aut__registros {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 0.85rem;
}
.aut__registros li {
  display: flex;
  flex-wrap: wrap;
  gap: 0 0.6rem;
}
</style>
