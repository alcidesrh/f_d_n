<template>
  <nav>
    <div class="sidebar-header">
      <span class="menu-icon">🔧</span>
      <span class="menu-text" style="font-weight: bold; font-size: 1.05rem"> Migración </span>
    </div>

    <div class="panel-body">
      <!-- Estado del proceso actual -->
      <div class="panel-section">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium">Proceso actual</span>
          <Tag
            v-if="store.estado.actual"
            :severity="severidadEstado(store.estado.actual.estado)"
            :value="etiquetaEstado(store.estado.actual.estado)"
            size="small"
          />
          <Tag v-else value="Inactivo" severity="secondary" size="small" />
        </div>
        <div v-if="store.estado.actual" class="mt-2 flex flex-col gap-1 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-muted-color">
              {{ etiquetaTipo(store.estado.actual) }}
            </span>
            <span class="font-mono">{{ store.estado.actual.id }}</span>
          </div>
          <div class="mt-1 flex gap-2">
            <Button
              v-if="ocupado"
              label="Cancelar"
              severity="danger"
              size="small"
              outlined
              class="w-full"
              :disabled="!!store.estado.actual.solicitud_cancelacion"
              @click="cancelar"
            />
          </div>
        </div>
      </div>

      <Divider />

      <!-- Migración por entidad -->
      <div class="panel-section">
        <div class="text-sm font-medium mb-2">Entidad migrable</div>
        <div class="flex flex-col gap-2">
          <Select
            v-model="entidadSeleccionada"
            :options="store.entidades"
            option-label="etiqueta"
            option-value="nombre"
            placeholder="Elegir entidad"
            :disabled="ocupado"
            show-clear
            filter
            class="w-full"
          />
          <div v-if="entidadMeta?.soportaRangoFechas" class="flex gap-2">
            <DatePicker
              v-model="desdeFecha"
              date-format="yy-mm-dd"
              placeholder="Desde"
              :disabled="ocupado"
              class="w-full"
            />
            <DatePicker
              v-model="hastaFecha"
              date-format="yy-mm-dd"
              placeholder="Hasta"
              :disabled="ocupado"
              class="w-full"
            />
          </div>
          <InputNumber
            v-if="entidadMeta?.soportaCantidad"
            v-model="cantidad"
            :min="1"
            placeholder="Cantidad (vacío = todos)"
            :disabled="ocupado"
            class="w-full"
          />
          <div v-if="entidadMeta" class="text-xs text-muted-color">
            {{ entidadMeta.etiqueta }}: {{ formatearNumero(entidadMeta.totalFuente) }} en legado ·
            dependencias:
            {{ entidadMeta.dependencias.length ? entidadMeta.dependencias.join(", ") : "ninguna" }}
          </div>
          <Button
            label="Migrar entidad"
            icon="pi pi-play"
            size="small"
            :disabled="!entidadMeta || ocupado"
            @click="migrarEntidad"
          />
        </div>
      </div>

      <Divider />

      <!-- Orquestación -->
      <div class="panel-section">
        <div class="text-sm font-medium mb-2">Flujos completos</div>
        <div class="flex flex-col gap-2">
          <Button
            label="Migrar estáticos"
            icon="pi pi-database"
            size="small"
            outlined
            :disabled="ocupado"
            @click="confirmarFlujo('estaticos')"
          />
          <Button
            label="Migrar IAM"
            icon="pi pi-lock"
            size="small"
            outlined
            :disabled="ocupado"
            @click="confirmarFlujo('iam')"
          />
          <Button
            label="Sincronizar configuración"
            icon="pi pi-cog"
            size="small"
            outlined
            :disabled="ocupado"
            @click="confirmarFlujo('config')"
          />
          <div class="flex items-center gap-2 mt-1">
            <Checkbox v-model="cleanTodo" input-id="migracion-clean" binary :disabled="ocupado" />
            <label for="migracion-clean" class="text-sm">Todo (con reset previo)</label>
          </div>
          <Button
            label="Migración completa"
            icon="pi pi-forward"
            size="small"
            :disabled="ocupado"
            @click="confirmarTodo"
          />
        </div>
      </div>

      <Divider />

      <!-- Resets -->
      <div class="panel-section">
        <div class="text-sm font-medium mb-2">Resets</div>
        <template v-if="modoResetDuro">
          <InputText
            v-model="textoReset"
            placeholder='Escribe "RESET" para confirmar'
            class="w-full mb-2"
            :disabled="ocupado"
            @keyup.enter="confirmarResetDuro"
          />
          <div class="flex gap-2">
            <Button
              label="Ejecutar reset duro"
              severity="danger"
              size="small"
              class="w-full"
              :disabled="textoReset !== 'RESET' || ocupado"
              @click="confirmarResetDuro"
            />
            <Button
              label="Cancelar"
              severity="secondary"
              text
              size="small"
              :disabled="ocupado"
              @click="cerrarResetDuro"
            />
          </div>
          <div class="text-xs text-muted-color mt-1">
            Dropea y recrea el esquema completo de la base de datos.
          </div>
        </template>
        <template v-else>
          <Button
            label="Truncar tablas migrables"
            icon="pi pi-trash"
            size="small"
            outlined
            severity="warning"
            class="w-full"
            :disabled="ocupado"
            @click="confirmarTruncar"
          />
          <Button
            label="Reset duro de BD"
            icon="pi pi-exclamation-triangle"
            size="small"
            severity="danger"
            class="w-full mt-2"
            :disabled="ocupado"
            @click="modoResetDuro = true"
          />
        </template>
      </div>

      <div v-if="store.error" class="panel-error mt-3 text-xs">
        {{ store.error }}
      </div>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { useConfirm } from "primevue/useconfirm";
import { useMigracionStore } from "./store";
import { notify } from "@/core/notify";
import type {
  EntidadMigracion,
  EstadoJobMigracion,
  PayloadEjecutar,
  TipoJobMigracion,
} from "./types";

defineOptions({ name: "MigracionPanel" });

const store = useMigracionStore();
const confirm = useConfirm();

const entidadSeleccionada = ref<string | null>(null);
const desdeFecha = ref<Date | null>(null);
const hastaFecha = ref<Date | null>(null);
const cantidad = ref<number | null>(null);
const cleanTodo = ref(false);
const modoResetDuro = ref(false);
const textoReset = ref("");

const ocupado = computed(() => store.estado.ejecutando);

const entidadMeta = computed<EntidadMigracion | null>(() => {
  const nombre = entidadSeleccionada.value;
  if (!nombre) return null;
  return store.entidades.find((e) => e.nombre === nombre) ?? null;
});

function aYmd(d: Date | null): string | null {
  if (!d) return null;
  const anio = d.getFullYear();
  const mes = String(d.getMonth() + 1).padStart(2, "0");
  const dia = String(d.getDate()).padStart(2, "0");
  return `${anio}-${mes}-${dia}`;
}

function formatearNumero(n: number): string {
  return new Intl.NumberFormat("es-AR").format(n);
}

function etiquetaTipo(job: { tipo: TipoJobMigracion; entidad: string | null }): string {
  const nombre: Record<TipoJobMigracion, string> = {
    reset: "Reset duro",
    truncar: "Truncar tablas",
    estaticos: "Estáticos",
    entidad: "Entidad",
    iam: "IAM",
    config: "Configuración",
    todo: "Migración completa",
  };
  return job.entidad ? `${nombre[job.tipo]} → ${job.entidad}` : nombre[job.tipo];
}

function etiquetaEstado(estado: EstadoJobMigracion | string): string {
  return (
    {
      pending: "En cola",
      running: "Ejecutando",
      done: "Completado",
      cancelado: "Cancelado",
      error: "Error",
      abortado: "Abortado",
    }[estado] ?? String(estado)
  );
}

function severidadEstado(
  estado: EstadoJobMigracion | string,
): "success" | "info" | "warn" | "danger" | "secondary" {
  return (
    {
      pending: "warn",
      running: "info",
      done: "success",
      cancelado: "warn",
      error: "danger",
      abortado: "danger",
    }[estado] ?? "secondary"
  );
}

async function iniciar(payload: PayloadEjecutar): Promise<void> {
  try {
    await store.arrancarJob(payload);
    notify.success("Migración iniciada. Seguí el avance en la consola.");
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  }
}

function conConfirmacion(mensaje: string, aceptar: () => void): void {
  confirm.require({
    message: mensaje,
    header: "Confirmar ejecución",
    icon: "pi pi-exclamation-triangle",
    acceptLabel: "Ejecutar",
    rejectLabel: "Cancelar",
    accept: () => aceptar(),
  });
}

function migrarEntidad(): void {
  const meta = entidadMeta.value;
  if (!meta) return;
  const payload: PayloadEjecutar = {
    tipo: "entidad",
    entidad: meta.nombre,
    desde: aYmd(desdeFecha.value),
    hasta: aYmd(hastaFecha.value),
    cantidad: cantidad.value != null && cantidad.value > 0 ? cantidad.value : null,
  };
  conConfirmacion(
    `Migrar entidad "${meta.etiqueta}"${payload.desde ? ` desde ${payload.desde}` : ""}${payload.hasta ? ` hasta ${payload.hasta}` : ""}${payload.cantidad ? ` (máx. ${payload.cantidad})` : ""}?`,
    () => iniciar(payload),
  );
}

function confirmarFlujo(tipo: "estaticos" | "iam" | "config"): void {
  conConfirmacion(`Ejecutar la migración de "${etiquetaTipo({ tipo, entidad: null })}"?`, () =>
    iniciar({ tipo }),
  );
}

function confirmarTodo(): void {
  conConfirmacion(
    cleanTodo.value
      ? "Migración COMPLETA con RESET previo de la base de datos. ¿Continuar?"
      : "Migración completa (estáticos + IAM + configuración + salidas). ¿Continuar?",
    () => iniciar({ tipo: "todo", clean: cleanTodo.value || undefined }),
  );
}

function confirmarTruncar(): void {
  conConfirmacion(
    "Truncar todas las tablas migrables? La migración posterior vuelve a insertar desde cero.",
    () => iniciar({ tipo: "truncar" }),
  );
}

function confirmarResetDuro(): void {
  if (textoReset.value !== "RESET") return;
  void iniciar({ tipo: "reset" });
  cerrarResetDuro();
}

function cerrarResetDuro(): void {
  modoResetDuro.value = false;
  textoReset.value = "";
}

async function cancelar(): Promise<void> {
  const actual = store.estado.actual;
  if (!actual) return;
  try {
    await store.cancelarJob(actual.id);
    notify.info("Cancelación solicitada. El proceso la respetará en la próxima iteración.");
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  }
}
</script>

<style scoped>
.panel-body {
  padding: 0 0.75rem 1rem;
}
.panel-section {
  display: flex;
  flex-direction: column;
}
.panel-error {
  color: var(--p-red-400);
  word-break: break-word;
  background: color-mix(in srgb, var(--p-red-500) 10%, transparent);
  border-radius: 6px;
  padding: 0.4rem 0.5rem;
}
</style>
