<!--
  Configuraciones del listado de la entidad (ADR-043): elegir cuál se edita,
  crear una nueva (copia de la elegida), cambiar su nombre y sus roles, y
  eliminarla. La que no tiene roles es la por defecto: la ven los usuarios
  sin un rol asociado a otra; un rol está en una sola. Lo que se hace aquí se
  guarda en el acto; columnas y opciones, con "Guardar cambios".
-->
<template>
  <div class="list-configs">
    <label class="list-configs__select">
      <span class="cell__label">Configuración del listado</span>
      <Select :model-value="store.listConfigId" :options="opciones" option-label="label" option-value="value" size="small" fluid aria-label="Configuración del listado" @update:model-value="elegir">
        <template #option="{ option }">
          <div class="flex min-w-0 flex-col">
            <span class="truncate">{{ option.label }}</span>
            <span class="truncate text-xs text-surface-500">{{ option.detalle }}</span>
          </div>
        </template>
      </Select>
    </label>
    <p class="list-configs__roles text-xs text-surface-500">{{ detalle(store.listConfig) }}</p>
    <div class="flex gap-1">
      <Button label="Nueva" size="small" severity="secondary" outlined @click="abrir('crear')">
        <template #icon><icon name="add" /></template>
      </Button>
      <Button label="Nombre y roles" size="small" severity="secondary" outlined :disabled="!store.listConfig" @click="abrir('editar')">
        <template #icon><icon name="group-outline" /></template>
      </Button>
      <Button v-tooltip.bottom="store.listConfig?.porDefecto ? 'La por defecto no se elimina' : 'Eliminar'" size="small" severity="danger" text aria-label="Eliminar configuración" :disabled="!store.listConfig || store.listConfig.porDefecto" @click="eliminar">
        <template #icon><icon name="delete-outline" /></template>
      </Button>
    </div>

    <Dialog v-model:visible="dialogo" modal :header="modo === 'crear' ? 'Nueva configuración del listado' : 'Nombre y roles'" :style="{ width: 'min(32rem, calc(100vw - 2rem))' }">
      <form class="flex flex-col gap-3" @submit.prevent="guardar">
        <label class="flex flex-col gap-1">
          <span class="text-sm">Nombre</span>
          <InputText v-model="nombre" maxlength="120" autofocus fluid />
        </label>
        <label v-if="!esPorDefecto" class="flex flex-col gap-1">
          <span class="text-sm">Roles que la ven</span>
          <MultiSelect v-model="roles" :options="rolesOpciones" option-label="label" option-value="value" option-disabled="disabled" filter display="chip" placeholder="Elegir roles" fluid />
          <small class="text-surface-500">Un rol está en una sola configuración por entidad. Con roles en varias, al usuario le toca la primera de la lista.</small>
        </label>
        <Message v-else severity="secondary" size="small">Es la por defecto: la ven los usuarios sin un rol asociado a otra configuración, y no lleva roles.</Message>
        <small v-if="modo === 'crear'" class="text-surface-500">Copia columnas y opciones de «{{ store.listConfig?.nombre }}».</small>
        <Message v-if="error" severity="error" size="small">{{ error }}</Message>
        <div class="flex justify-end gap-2">
          <Button label="Cancelar" severity="secondary" text type="button" @click="dialogo = false" />
          <Button :label="modo === 'crear' ? 'Crear' : 'Guardar'" type="submit" :loading="guardando" :disabled="!nombre.trim() || (!esPorDefecto && roles.length === 0)" />
        </div>
      </form>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { useConfirm } from "primevue/useconfirm";
import { notify } from "@/core/notify";
import type { ListConfigDto } from "./api";
import { useEntityConfigStore } from "./store";

const store = useEntityConfigStore();
const confirm = useConfirm();

const nombreRol = (id: number) => {
  const rol = store.roles.find((r) => r.id === id);
  return rol ? (rol.descripcion ?? rol.nombre) : `Rol ${id}`;
};

function detalle(config: ListConfigDto | null): string {
  if (!config) return "";
  return config.porDefecto ? "Por defecto: usuarios sin un rol asociado a otra" : `Roles: ${config.roles.map(nombreRol).join(", ")}`;
}

const opciones = computed(() => store.listConfigs.map((config, i) => ({ label: `${i + 1}. ${config.nombre}`, detalle: detalle(config), value: config.id })));

const dialogo = ref(false);
const modo = ref<"crear" | "editar">("crear");
const nombre = ref("");
const roles = ref<number[]>([]);
const error = ref("");
const guardando = ref(false);
const esPorDefecto = computed(() => modo.value === "editar" && Boolean(store.listConfig?.porDefecto));

/** Los roles de otra configuración no se pueden elegir: un rol está en una sola. */
const rolesOpciones = computed(() => {
  const propia = modo.value === "editar" ? store.listConfigId : null;
  return store.roles.map((rol) => {
    const otra = store.listConfigs.find((config) => config.id !== propia && config.roles.includes(rol.id));
    return { value: rol.id, label: otra ? `${rol.descripcion ?? rol.nombre} (en «${otra.nombre}»)` : (rol.descripcion ?? rol.nombre), disabled: Boolean(otra) };
  });
});

function abrir(nuevoModo: "crear" | "editar") {
  modo.value = nuevoModo;
  nombre.value = nuevoModo === "editar" ? (store.listConfig?.nombre ?? "") : "";
  roles.value = nuevoModo === "editar" ? [...(store.listConfig?.roles ?? [])] : [];
  error.value = "";
  dialogo.value = true;
}

async function guardar() {
  guardando.value = true;
  error.value = "";
  try {
    const datos = { nombre: nombre.value.trim(), roles: esPorDefecto.value ? [] : roles.value };
    if (modo.value === "crear") await store.crearListado(datos);
    else await store.guardarDatosListado(datos);
    notify.success(modo.value === "crear" ? `Configuración «${datos.nombre}» creada` : "Configuración guardada");
    dialogo.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e);
  } finally {
    guardando.value = false;
  }
}

/** Cambiar de configuración descarta las columnas y opciones sin guardar de la actual. */
function elegir(id: number) {
  if (id === store.listConfigId) return;
  if (!store.listDirty) return store.chooseListConfig(id);
  confirm.require({
    header: "Cambios sin guardar",
    message: `Se perderán los cambios de «${store.listConfig?.nombre}».`,
    acceptLabel: "Descartar",
    rejectLabel: "Seguir editando",
    accept: () => store.chooseListConfig(id),
  });
}

function eliminar() {
  const config = store.listConfig;
  if (!config) return;
  confirm.require({
    header: "Eliminar configuración",
    message: `Se eliminará «${config.nombre}». Sus roles verán la configuración por defecto.`,
    acceptLabel: "Eliminar",
    rejectLabel: "Cancelar",
    acceptProps: { severity: "danger" },
    accept: async () => {
      try {
        await store.eliminarListado();
        notify.success(`Configuración «${config.nombre}» eliminada`);
      } catch (e) {
        notify.error(e instanceof Error ? e.message : String(e));
      }
    },
  });
}
</script>

<style scoped>
.list-configs {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.5rem 1rem;
  margin-bottom: 0.75rem;
}
.list-configs__select {
  display: flex;
  min-width: min(100%, 16rem);
  flex-direction: column;
  gap: 0.2rem;
}
.list-configs__roles {
  flex: 1 1 12rem;
  min-width: 0;
  margin: 0;
  padding-bottom: 0.4rem;
}
.cell__label {
  font-size: 0.7rem;
  letter-spacing: 0.02em;
  color: var(--p-text-muted-color);
  white-space: nowrap;
}
</style>
