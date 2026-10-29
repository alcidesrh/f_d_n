<!--
  Programador de tareas (ADR-044): el latido de cada tarea del catálogo
  (cuándo corre, la última corrida, su resultado y si está detenida) y
  "Correr ahora", que la pone en la cola del worker. Pide `config.editar`
  (lo decide el backend). Se actualiza sola cada 30 s.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <Tag v-if="detenidas" :value="`${detenidas} detenida${detenidas === 1 ? '' : 's'}`" severity="danger" />
      <Tag v-else-if="estado && !sinCorrer" value="Todas al día" severity="success" />
      <span v-if="actualizado" class="text-sm text-muted-color">Actualizado {{ hora(actualizado) }}</span>
      <Button class="ml-auto" severity="secondary" text size="small" :loading="cargando" aria-label="Actualizar" v-tooltip.left="'Actualizar'" @click="cargar">
        <template #icon><icon name="refresh" /></template>
      </Button>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-if="sinCorrer" severity="warn" :closable="false"> Ninguna tarea ha corrido todavía. Revise que estén arriba los contenedores <code>programador</code> y <code>worker</code>. </Message>
    <Message v-if="estado?.fallidos" severity="warn" :closable="false"> {{ estado.fallidos }} trabajo{{ estado.fallidos === 1 ? "" : "s" }} en segundo plano agot{{ estado.fallidos === 1 ? "ó" : "aron" }} sus reintentos (correos, reportes o despliegues de esquemas). Se revisan con <code>messenger:failed:show</code>. </Message>
    <Skeleton v-if="cargando && !estado" height="16rem" />

    <ul v-if="estado" class="m-0 flex list-none flex-col gap-2 p-0">
      <li v-for="t in estado.tareas" :key="t.codigo" class="tarea">
        <div class="flex flex-wrap items-start gap-x-3 gap-y-1">
          <div class="min-w-0 flex-1">
            <span class="block font-medium">{{ t.nombre }}</span>
            <span class="block text-xs text-muted-color"
              >{{ t.cuando }} · <code>{{ t.codigo }}</code></span
            >
          </div>
          <Tag :value="SALUD[salud(t)].etiqueta" :severity="SALUD[salud(t)].severidad" class="!text-xs" />
          <Button label="Correr ahora" size="small" severity="secondary" outlined :loading="encolando === t.codigo" :disabled="encolando !== null" @click="correr(t)">
            <template #icon><icon name="play-arrow" class="mr-1" /></template>
          </Button>
        </div>
        <dl class="datos">
          <div>
            <dt>Última buena</dt>
            <dd>{{ t.ultimoExito ? fecha(t.ultimoExito) : "—" }}</dd>
          </div>
          <div>
            <dt>Último intento</dt>
            <dd>
              {{ t.ultimoInicio ? fecha(t.ultimoInicio) : "—" }}<template v-if="t.resultado === 'omitida'"> (omitida: la anterior seguía)</template><template v-else-if="t.duracionMs !== null"> · {{ duracion(t.duracionMs) }}</template>
            </dd>
          </div>
          <div>
            <dt>Corridas</dt>
            <dd>
              {{ t.corridas }}<template v-if="t.fallosSeguidos"> · {{ t.fallosSeguidos }} fallo{{ t.fallosSeguidos === 1 ? "" : "s" }} seguido{{ t.fallosSeguidos === 1 ? "" : "s" }}</template>
            </dd>
          </div>
        </dl>
        <p v-if="t.resultado === 'error' && t.error" class="m-0 break-words text-sm text-red-600 dark:text-red-400">{{ t.error }}</p>
        <p v-else-if="describirResumen(t.resumen)" class="m-0 break-words text-xs text-muted-color">Última corrida: {{ describirResumen(t.resumen) }}</p>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from "vue";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";
import { describirResumen, duracion, ejecutarTarea, fetchProgramador, nuncaCorrio, salud, SALUD, type EstadoProgramador, type EstadoTarea } from "@/core/programador/tareas";

const CADA_MS = 30_000;

const estado = shallowRef<EstadoProgramador | null>(null);
const cargando = ref(false);
const error = ref("");
const encolando = ref<string | null>(null);
const actualizado = ref<Date | null>(null);

const detenidas = computed(() => estado.value?.tareas.filter((t) => t.detenida).length ?? 0);
const sinCorrer = computed(() => !!estado.value && nuncaCorrio(estado.value.tareas));

const fecha = (iso: string) => new Intl.DateTimeFormat("es-GT", { day: "2-digit", month: "short", hour: "numeric", minute: "2-digit", second: "2-digit" }).format(new Date(iso));
const hora = (d: Date) => new Intl.DateTimeFormat("es-GT", { hour: "numeric", minute: "2-digit", second: "2-digit" }).format(d);

function mensaje(causa: unknown): string {
  if (causa instanceof HttpError && causa.status === 403) return "Ver el programador de tareas pide el permiso config.editar.";
  return causa instanceof Error ? causa.message : String(causa);
}

async function cargar() {
  cargando.value = true;
  try {
    estado.value = await fetchProgramador();
    actualizado.value = new Date();
    error.value = "";
  } catch (causa) {
    error.value = mensaje(causa);
  } finally {
    cargando.value = false;
  }
}

async function correr(t: EstadoTarea) {
  encolando.value = t.codigo;
  try {
    await ejecutarTarea(t.codigo);
    notify.success(`«${t.nombre}» quedó en la cola; el worker la corre en unos segundos.`);
    setTimeout(() => void cargar(), 5_000);
  } catch (causa) {
    notify.error(mensaje(causa));
  } finally {
    encolando.value = null;
  }
}

let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
  void cargar();
  timer = setInterval(() => {
    if (document.visibilityState === "visible") void cargar();
  }, CADA_MS);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.tarea {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  background: var(--p-content-background);
  border-radius: var(--p-content-border-radius, 0.75rem);
  padding: 0.875rem 1rem;
}
.datos {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0.25rem 1rem;
  margin: 0;
  font-size: 0.875rem;
}
.datos dt {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.datos dd {
  margin: 0;
}
@container (min-width: 40rem) {
  .datos {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
