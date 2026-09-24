<template>
  <div class="flex flex-col gap-4">
    <template v-if="!store.cargado && store.entidades.length === 0">
      <Skeleton height="8rem" />
      <Skeleton height="14rem" />
    </template>

    <template v-else>
      <Message v-if="store.error" severity="warn" :closable="false">
        {{ store.error }}
      </Message>

      <!-- Indicadores por entidad -->
      <div class="card">
        <div class="flex items-center justify-between mb-3">
          <div class="text-base font-semibold">Indicadores nuevo vs legado</div>
          <Tag
            :value="`Nuevo ${formatearNumero(store.indicadores.total?.nuevo ?? 0)} / Legado ${formatearNumero(store.indicadores.total?.legado ?? 0)}`"
            severity="info"
          />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
          <div
            v-for="fila in filasIndicadores"
            :key="fila.nombre"
            class="flex flex-col gap-1 border border-surface-200 dark:border-surface-700 rounded-lg p-3"
          >
            <div class="flex items-center justify-between text-sm">
              <span class="font-medium">{{ fila.etiqueta }}</span>
              <span class="text-xs text-muted-color">{{ fila.nombre }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-green-600 dark:text-green-400 font-mono">
                {{ formatearNumero(fila.conteo.nuevo) }} nuevo
              </span>
              <span class="text-muted-color font-mono">
                {{ formatearNumero(fila.conteo.legado) }} legado
              </span>
            </div>
            <ProgressBar
              :value="porcentajeNuevo(fila.conteo)"
              :show-value="false"
              class="h-1.5"
            />
          </div>
        </div>
      </div>

      <!-- Proceso actual -->
      <div class="card">
        <div class="flex items-center justify-between mb-3">
          <div class="text-base font-semibold">Proceso en curso</div>
          <Tag
            v-if="store.estado.actual"
            :severity="severidadEstado(store.estado.actual.estado)"
            :value="etiquetaEstado(store.estado.actual.estado)"
          />
          <Tag v-else value="Sin proceso activo" severity="secondary" />
        </div>

        <template v-if="store.estado.actual">
          <div class="flex flex-col gap-2 text-sm">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
              <span class="font-medium">{{
                etiquetaTipo(store.estado.actual)
              }}</span>
              <span class="font-mono text-xs text-muted-color">
                {{ store.estado.actual.id }}
              </span>
              <span v-if="store.estado.actual.duracion != null" class="text-xs">
                ⏱ {{ store.estado.actual.duracion }} s
              </span>
              <span
                v-if="store.estado.actual.solicitud_cancelacion"
                class="text-xs text-amber-600 dark:text-amber-400"
              >
                Cancelación solicitada…
              </span>
            </div>
            <div
              v-if="
                store.estado.actual.total != null &&
                store.estado.actual.total > 0
              "
              class="flex flex-col gap-1"
            >
              <ProgressBar
                :value="porcentajeProgreso(store.estado.actual)"
                class="h-2"
              />
              <div class="text-xs text-muted-color">
                {{ formatearNumero(store.estado.actual.procesados) }} /
                {{ formatearNumero(store.estado.actual.total) }} procesados
              </div>
            </div>
            <div v-else class="text-xs text-muted-color">
              Procesados:
              {{ formatearNumero(store.estado.actual.procesados) }}
            </div>
            <div
              v-if="contadoresVisibles(store.estado.actual).length > 0"
              class="flex flex-wrap gap-2"
            >
              <Tag
                v-for="[clave, valor] in contadoresVisibles(
                  store.estado.actual,
                )"
                :key="clave"
                :value="`${clave}: ${formatearNumero(valor)}`"
                severity="secondary"
                size="small"
              />
            </div>
            <Button
              v-if="store.estado.ejecutando"
              label="Cancelar proceso"
              icon="pi pi-stop"
              severity="danger"
              size="small"
              class="self-start"
              :disabled="!!store.estado.actual.solicitud_cancelacion"
              @click="cancelar"
            />
          </div>
        </template>
        <div v-else class="text-sm text-muted-color">
          No hay ninguna migración en ejecución. Usá el panel derecho para
          arrancar una (entidad, estáticos, IAM, configuración o completa).
        </div>
      </div>

      <!-- Consola -->
      <div class="card">
        <div class="flex items-center justify-between mb-3">
          <div class="text-base font-semibold">Consola</div>
          <Tag
            v-if="store.logJobId && !store.logFin && store.estado.ejecutando"
            value="En vivo"
            severity="info"
            size="small"
          />
        </div>
        <pre
          ref="consola"
          class="consola"
          :class="{ vacia: !store.log && !store.logJobId }"
          >{{
            store.log ||
            "Sin actividad todavía. Al lanzar una migración el log aparecerá aquí."
          }}</pre>
      </div>

      <!-- Historial -->
      <div class="card">
        <div class="text-base font-semibold mb-3">Historial reciente</div>
        <div
          v-if="store.estado.recientes.length === 0"
          class="text-sm text-muted-color"
        >
          Sin ejecuciones registradas.
        </div>
        <DataTable
          v-else
          :value="store.estado.recientes"
          size="small"
          striped-rows
          class="w-full"
        >
          <Column field="id" header="Job">
            <template #body="{ data }">
              <span class="font-mono text-xs">{{ data.id }}</span>
            </template>
          </Column>
          <Column header="Tipo">
            <template #body="{ data }">
              {{ etiquetaTipo(data) }}
            </template>
          </Column>
          <Column header="Estado">
            <template #body="{ data }">
              <Tag
                :severity="severidadEstado(data.estado)"
                :value="etiquetaEstado(data.estado)"
                size="small"
              />
            </template>
          </Column>
          <Column header="Procesados">
            <template #body="{ data }">
              {{ formatearNumero(data.procesados) }}
            </template>
          </Column>
          <Column header="Duración">
            <template #body="{ data }">
              {{ data.duracion != null ? `${data.duracion} s` : "—" }}
            </template>
          </Column>
          <Column header="Acciones">
            <template #body="{ data }">
              <Button
                label="Re-ejecutar"
                icon="pi pi-refresh"
                size="small"
                text
                :disabled="store.estado.ejecutando"
                @click="reejecutar(data)"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from "vue";
import { useMigracionStore } from "./store";
import { cancelarJob as cancelar, reejecutarJob as reejecutar } from "./actions";
import { etiquetaEstado, etiquetaTipo, formatearNumero, porcentajeNuevo, porcentajeProgreso, severidadEstado } from "./labels";
import type {
  ConteoMigracion,
  EntidadMigracion,
  JobMigracion,
} from "./types";

defineOptions({ name: "MigracionView" });

const store = useMigracionStore();
const consola = ref<HTMLElement | null>(null);

interface FilaIndicador {
  nombre: string;
  etiqueta: string;
  conteo: ConteoMigracion;
}

const filasIndicadores = computed<FilaIndicador[]>(() => {
  const porNombre = new Map<string, EntidadMigracion>();
  for (const e of store.entidades) porNombre.set(e.nombre, e);
  const nombres = Object.keys(store.indicadores).filter((k) => k !== "total");
  return nombres.map((nombre) => ({
    nombre,
    etiqueta: porNombre.get(nombre)?.etiqueta ?? nombre,
    conteo: store.indicadores[nombre] ?? { nuevo: -1, legado: -1 },
  }));
});



function contadoresVisibles(job: JobMigracion): Array<[string, number]> {
  return Object.entries(job.contadores ?? {}).filter(
    ([, v]) => typeof v === "number",
  ) as Array<[string, number]>;
}







// Autoscroll de la consola al final cuando crece el log.
watch(
  () => store.log,
  () => {
    void nextTick(() => {
      const el = consola.value;
      if (el) el.scrollTop = el.scrollHeight;
    });
  },
);

onMounted(() => {
  void store.inicializar();
});

onBeforeUnmount(() => {
  store.detenerPolling();
});
</script>

<style scoped>
.consola {
  margin: 0;
  min-height: 8rem;
  max-height: 24rem;
  overflow: auto;
  padding: 0.75rem;
  border-radius: 8px;
  background: var(--p-surface-950);
  color: var(--p-surface-50);
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 1.2rem;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
}
.consola.vacia {
  color: var(--p-surface-500);
}
</style>
