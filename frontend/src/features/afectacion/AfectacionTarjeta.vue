<!--
  Una afectación (ADR-070): qué pasó y dónde, su prioridad, quién la reportó
  (o si la detectó el sistema), en qué va el centinela (avisos, escalamiento,
  próximo aviso) y, resuelta, quién la resolvió y cómo. Editar y resolver
  los decide el backend (`puede`): quien tiene la capacidad `target` del
  tipo donde cae.
-->
<template>
  <article class="afe" :class="{ 'afe--destacada': destacada }" :style="{ '--acento': `var(--c-${acento})` }">
    <header class="flex flex-wrap items-start justify-between gap-2">
      <div class="flex min-w-0 items-start gap-2">
        <icon :name="ICONOS[a.tipo.sujeto]" class="afe__icono" />
        <div class="min-w-0">
          <div class="font-semibold">
            {{ a.tipo.nombre }} <span class="font-normal text-muted-color">· #{{ a.id }}</span>
          </div>
          <div class="text-xs text-muted-color">
            {{ a.lugar }}<template v-if="a.empresa && a.tipo.sujeto !== 'empresa'"> · {{ a.empresa.nombre }}</template>
          </div>
        </div>
      </div>
      <div class="flex flex-wrap items-center justify-end gap-1">
        <Tag v-if="a.estado !== 'resuelta'" :value="PRIORIDADES[a.tipo.prioridad].etiqueta" :severity="PRIORIDADES[a.tipo.prioridad].severidad" />
        <Tag v-tooltip.top="ESTADOS[a.estado].explicacion" :value="ESTADOS[a.estado].etiqueta" :severity="ESTADOS[a.estado].severidad" />
      </div>
    </header>

    <blockquote class="afe__texto">
      <span class="text-xs text-muted-color">{{ a.automatica ? "La detectó el sistema" : a.reportadaPor?.nombre }} · {{ fecha(a.creadaEn) }}</span>
      <p class="m-0 whitespace-pre-line">{{ a.descripcion }}</p>
    </blockquote>

    <p v-if="a.estado === 'resuelta'" class="m-0 text-sm">
      <span class="text-muted-color"
        >Resolvió {{ a.resueltaPor?.nombre ?? "—" }}<template v-if="a.resueltaEn"> · {{ fecha(a.resueltaEn) }}</template
        >:</span
      >
      {{ a.resolucion ?? "sin nota" }}
    </p>
    <p class="m-0 text-xs text-muted-color">{{ enQueVa(a, ahora) }}</p>

    <footer v-if="a.puede.editar || a.puede.resolver" class="flex flex-wrap justify-end gap-2">
      <Button v-if="a.puede.editar" label="Corregir" size="small" severity="secondary" outlined @click="emit('editar', a)" />
      <Button v-if="a.puede.resolver" label="Resolver" size="small" @click="emit('resolver', a)">
        <template #icon><icon name="check" class="mr-1" /></template>
      </Button>
    </footer>
  </article>
</template>

<script setup lang="ts">
import { enQueVa, ESTADOS, PRIORIDADES } from "@/core/afectacion/reglas";
import type { Afectacion, PrioridadAfectacion, SujetoAfectacion } from "@/core/afectacion/tipos";

const props = defineProps<{ a: Afectacion; ahora: Date; destacada?: boolean }>();
const emit = defineEmits<{
  editar: [a: Afectacion];
  resolver: [a: Afectacion];
}>();

const ICONOS: Record<SujetoAfectacion, string> = { estacion: "location-city", bus: "directions-bus-outline", empresa: "domain" };
const ACENTOS: Record<PrioridadAfectacion, string> = { urgente: "danger", grave: "warning", normal: "info" };
const acento = computed(() => (props.a.estado === "resuelta" ? "success" : ACENTOS[props.a.tipo.prioridad]));

const fecha = (iso: string) => new Intl.DateTimeFormat("es-GT", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit" }).format(new Date(iso));
</script>

<style scoped>
.afe {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.9rem 1rem;
  border: 1px solid var(--p-content-border-color);
  border-left: 3px solid var(--acento);
  border-radius: var(--p-content-border-radius, 0.75rem);
  background: var(--p-content-background);
}
.afe--destacada {
  box-shadow: 0 0 0 2px var(--p-primary-color);
}
.afe__icono {
  flex: none;
  margin-top: 0.1rem;
  color: var(--acento);
  font-size: 1.25rem;
}
.afe__texto {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  margin: 0;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  background: color-mix(in srgb, var(--p-surface-500) 7%, transparent);
}
</style>
