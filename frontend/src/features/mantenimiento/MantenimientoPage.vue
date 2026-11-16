<!--
  Mantenimiento de flota (ADR-065): cada bus con su odómetro y en qué va con
  cada plan (vencido, por vencer, al día o sin registro), primero los que más
  urgen. El estado lo calcula el backend. Los planes y las órdenes se
  mantienen en sus listados genéricos; «Registrar» abre la orden nueva con el
  bus y el plan ya puestos. El odómetro se corrige en el formulario del bus.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="e in ESTADOS"
        :key="e"
        type="button"
        class="rounded-full border px-2.5 py-1 text-xs font-semibold pointer-coarse:py-2"
        :class="filtro.estado === e ? 'border-primary bg-primary text-primary-contrast' : 'border-surface-300 bg-transparent text-color'"
        :aria-pressed="filtro.estado === e"
        @click="filtro.estado = filtro.estado === e ? null : e"
      >
        {{ ESTADO[e].etiqueta }} · {{ cuenta[e] }}
      </button>
      <span class="ml-auto flex items-center gap-1">
        <RouterLink :to="{ name: 'entity-list', params: { entity: 'orden-mantenimiento' } }" class="text-sm">Órdenes</RouterLink>
        <template v-if="datos?.puede.planes">
          <span class="text-muted-color">·</span>
          <RouterLink :to="{ name: 'entity-list', params: { entity: 'plan-mantenimiento' } }" class="text-sm">Planes</RouterLink>
        </template>
        <Button severity="secondary" text size="small" :loading="cargando" aria-label="Actualizar" @click="cargar">
          <template #icon><icon name="refresh" /></template>
        </Button>
      </span>
    </div>

    <IconField>
      <InputIcon><icon name="search" /></InputIcon>
      <InputText v-model="filtro.texto" placeholder="Código o matrícula del bus" autocomplete="off" fluid aria-label="Buscar bus" />
    </IconField>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-else-if="datos && datos.planes.length === 0" severity="info" :closable="false">
      Todavía no hay planes de mantenimiento.
      <template v-if="datos.puede.planes">Cree el primero (por ejemplo «Cambio de aceite», cada 10,000 km) en <RouterLink :to="{ name: 'entity-list', params: { entity: 'plan-mantenimiento' } }">Planes</RouterLink>.</template>
    </Message>
    <Skeleton v-if="cargando && !datos" height="16rem" />

    <p v-if="datos && datos.planes.length > 0 && visibles.length === 0" class="m-0 text-sm text-muted-color">Ningún bus con ese filtro.</p>

    <ul v-if="datos && datos.planes.length > 0" class="m-0 flex list-none flex-col gap-2 p-0">
      <li v-for="b in visibles" :key="b.id" class="bus">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
          <span class="min-w-0">
            <strong>{{ b.codigo }}</strong><span v-if="b.matricula" class="text-muted-color"> · {{ b.matricula }}</span>
          </span>
          <Tag :value="ESTADO[b.estado].etiqueta" :severity="ESTADO[b.estado].severidad" class="!text-xs" />
          <span class="ml-auto text-sm tabular-nums text-muted-color">
            {{ b.odometroKm.toLocaleString("es-GT") }} km
            <RouterLink v-if="datos.puede.odometro" :to="{ name: 'entity-form', params: { entity: 'bus', id: b.id } }" class="ml-1 text-xs">Corregir</RouterLink>
          </span>
        </div>
        <ul class="m-0 mt-2 flex list-none flex-col gap-1 p-0">
          <li v-for="p in b.planes" :key="p.plan" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <span class="inline-block size-2 shrink-0 rounded-full" :class="PUNTO[p.estado]" aria-hidden="true" />
            <span class="font-medium">{{ planes.get(p.plan)?.nombre ?? `Plan #${p.plan}` }}</span>
            <span class="text-muted-color">{{ detalle(p) }}<template v-if="p.ultimaFecha"> · último el {{ fecha(p.ultimaFecha) }}<template v-if="p.ultimoKm !== null"> a los {{ p.ultimoKm.toLocaleString("es-GT") }} km</template></template></span>
            <RouterLink v-if="datos.puede.registrar" :to="{ name: 'entity-form', params: { entity: 'orden-mantenimiento' }, query: { bus: b.id, plan: p.plan } }" class="ml-auto text-xs">Registrar</RouterLink>
          </li>
        </ul>
      </li>
    </ul>

    <p v-if="datos && datos.planes.length > 0" class="m-0 text-xs text-muted-color">
      {{ datos.planes.map((p) => `${p.nombre}: ${intervalo(p).toLowerCase()}`).join(" · ") }}. Avisa cuando falta el 10 % del intervalo. El odómetro suma cada madrugada lo que el bus recorrió según su GPS.
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, shallowRef } from "vue";
import { HttpError } from "@/core/http";
import { fetchMantenimiento } from "@/core/mantenimiento/api";
import { conteo, detalle, ESTADO, ESTADOS, filtrar, intervalo, type EstadoMantenimiento, type RespuestaMantenimiento } from "@/core/mantenimiento/estado";

const PUNTO: Record<EstadoMantenimiento, string> = {
  vencido: "bg-red-500",
  por_vencer: "bg-amber-500",
  sin_registro: "bg-surface-400",
  al_dia: "bg-green-500",
};

const datos = shallowRef<RespuestaMantenimiento | null>(null);
const cargando = ref(false);
const error = ref("");
const filtro = reactive<{ estado: EstadoMantenimiento | null; texto: string }>({ estado: null, texto: "" });

const planes = computed(() => new Map((datos.value?.planes ?? []).map((p) => [p.id, p])));
const cuenta = computed(() => conteo(datos.value?.buses ?? []));
const visibles = computed(() => filtrar(datos.value?.buses ?? [], filtro));

/** `2026-06-01` → «1 jun 2026», sin pasar por la zona horaria. */
function fecha(dia: string): string {
  const [a, m, d] = dia.split("-").map(Number);
  return new Intl.DateTimeFormat("es-GT", { day: "numeric", month: "short", year: "numeric" }).format(new Date(a ?? 0, (m ?? 1) - 1, d ?? 1));
}

async function cargar() {
  cargando.value = true;
  try {
    datos.value = await fetchMantenimiento();
    error.value = "";
  } catch (causa) {
    error.value = causa instanceof HttpError && causa.status === 403 ? "No tiene permiso para ver el mantenimiento de la flota." : causa instanceof Error ? causa.message : String(causa);
  } finally {
    cargando.value = false;
  }
}

onMounted(() => void cargar());
</script>

<style scoped>
.bus {
  background: var(--p-content-background);
  border-radius: var(--p-content-border-radius, 0.75rem);
  padding: 0.875rem 1rem;
}
</style>
