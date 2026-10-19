<!--
  Catálogo de encomiendas especiales (el «Registrar Especial» del legado,
  ADR-035): tipos con precio fijo por unidad (sobre, llanta, bicicleta…).
  Se crean, se editan (nombre, precio, si se envían por cobrar) y se
  desactivan; uno inactivo ya no se ofrece al registrar, pero las
  encomiendas que lo usaron no cambian. Permiso `encomienda.especiales`.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <IconField class="min-w-0 flex-1 @xl:max-w-sm">
        <InputIcon><icon name="search" /></InputIcon>
        <InputText v-model="busqueda" placeholder="Buscar por nombre o descripción" aria-label="Buscar especial" fluid />
      </IconField>
      <label class="flex items-center gap-2 text-sm">
        <Checkbox v-model="verInactivos" binary />
        <span>Ver inactivos ({{ inactivos }})</span>
      </label>
      <Button class="ml-auto" label="Nuevo especial" size="small" @click="abrir(null)">
        <template #icon><icon name="add" class="mr-1" color="text-white" /></template>
      </Button>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <DataTable :value="visibles" :loading="cargando" size="small" data-key="id" paginator :rows="25" :rows-per-page-options="[25, 50, 100]" sort-field="nombre" :sort-order="1" striped-rows class="panel" :row-class="(e: EspecialAdmin) => (e.activo ? '' : 'opacity-60')">
      <template #empty><div class="p-4 text-center text-muted-color">{{ especiales.length ? "Ningún especial coincide." : "No hay especiales." }}</div></template>
      <Column field="nombre" header="Nombre" sortable>
        <template #body="{ data }">
          <span class="font-medium">{{ data.nombre }}</span>
          <span v-if="data.descripcion" class="block text-xs text-muted-color">{{ data.descripcion }}</span>
        </template>
      </Column>
      <Column field="precio.centavos" header="Precio" sortable class="whitespace-nowrap text-right tabular-nums" header-class="[&>div]:justify-end">
        <template #body="{ data }">{{ data.precio.texto }}</template>
      </Column>
      <Column header="Por cobrar" class="text-center" header-class="[&>div]:justify-center">
        <template #body="{ data }">
          <icon :name="data.permitePorCobrar ? 'check' : 'block'" :color="data.permitePorCobrar ? 'text-green-600' : 'text-muted-color'" :aria-label="data.permitePorCobrar ? 'Se envía por cobrar' : 'No se envía por cobrar'" />
        </template>
      </Column>
      <Column header="Estado">
        <template #body="{ data }"><Tag :value="data.activo ? 'Activo' : 'Inactivo'" :severity="data.activo ? 'success' : 'secondary'" class="!text-xs" /></template>
      </Column>
      <Column class="w-0 whitespace-nowrap text-right">
        <template #body="{ data }">
          <Button text rounded size="small" severity="secondary" :aria-label="`Editar ${data.nombre}`" v-tooltip.left="'Editar'" @click="abrir(data)">
            <template #icon><icon name="edit-outline" /></template>
          </Button>
        </template>
      </Column>
    </DataTable>

    <Dialog :visible="form !== null" modal :header="form?.id ? 'Editar especial' : 'Nuevo especial'" class="w-[min(28rem,calc(100vw-1rem))]" @update:visible="!$event && (form = null)">
      <form v-if="form" id="form-especial" class="flex flex-col gap-3" @submit.prevent="guardar">
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Nombre</span>
          <InputText v-model="form.nombre" maxlength="100" :invalid="intentado && !form.nombre.trim()" autofocus fluid />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Descripción <span class="font-normal text-muted-color">(opcional)</span></span>
          <InputText v-model="form.descripcion" maxlength="255" fluid />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Precio por unidad</span>
          <InputNumber v-model="form.precio" mode="currency" currency="GTQ" locale="es-GT" :min="0" :max="100000" :invalid="intentado && form.precio === null" fluid />
        </label>
        <label class="flex cursor-pointer items-center gap-2 text-sm"><Checkbox v-model="form.permitePorCobrar" binary />Se puede enviar por cobrar</label>
        <label class="flex cursor-pointer items-center gap-2 text-sm"><Checkbox v-model="form.activo" binary />Activo (se ofrece al registrar)</label>
        <Message v-if="form.legado && form.id" severity="secondary" size="small" :closable="false">Viene del sistema anterior. Cambiar el precio no altera las encomiendas ya registradas.</Message>
      </form>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text @click="form = null" />
        <Button type="submit" form="form-especial" label="Guardar" :loading="guardando" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { crearEspecial, editarEspecial, fetchEspeciales } from "@/core/encomienda/api";
import type { EspecialAdmin } from "@/core/encomienda/types";
import { errorVenta } from "@/core/venta/api";
import { notify } from "@/core/notify";

interface Formulario {
  id: number | null;
  nombre: string;
  descripcion: string;
  precio: number | null;
  permitePorCobrar: boolean;
  activo: boolean;
  legado: boolean;
}

const especiales = ref<EspecialAdmin[]>([]);
const busqueda = ref("");
const verInactivos = ref(false);
const cargando = ref(false);
const guardando = ref(false);
const intentado = ref(false);
const error = ref("");
const form = ref<Formulario | null>(null);

const inactivos = computed(() => especiales.value.filter((e) => !e.activo).length);
const visibles = computed(() => {
  const q = busqueda.value.trim().toLowerCase();
  return especiales.value.filter((e) => (verInactivos.value || e.activo) && (!q || `${e.nombre} ${e.descripcion ?? ""}`.toLowerCase().includes(q)));
});

onMounted(cargar);

async function cargar() {
  cargando.value = true;
  error.value = "";
  try {
    especiales.value = await fetchEspeciales();
  } catch (e) {
    error.value = errorVenta(e)?.error ?? (e instanceof Error ? e.message : String(e));
  } finally {
    cargando.value = false;
  }
}

function abrir(e: EspecialAdmin | null) {
  intentado.value = false;
  form.value = e
    ? { id: e.id, nombre: e.nombre, descripcion: e.descripcion ?? "", precio: e.precio.centavos / 100, permitePorCobrar: e.permitePorCobrar, activo: e.activo, legado: e.legado }
    : { id: null, nombre: "", descripcion: "", precio: null, permitePorCobrar: true, activo: true, legado: false };
}

async function guardar() {
  const f = form.value;
  if (!f) return;
  intentado.value = true;
  if (!f.nombre.trim() || f.precio === null) return;
  guardando.value = true;
  try {
    const pedido = { nombre: f.nombre.trim(), descripcion: f.descripcion.trim() || null, precio: f.precio, permitePorCobrar: f.permitePorCobrar, activo: f.activo };
    const guardado = f.id ? await editarEspecial(f.id, pedido) : await crearEspecial(pedido);
    especiales.value = f.id ? especiales.value.map((e) => (e.id === guardado.id ? guardado : e)) : [...especiales.value, guardado];
    notify.success(f.id ? "Especial actualizado" : "Especial creado");
    form.value = null;
  } catch (e) {
    notify.error(errorVenta(e)?.error ?? "No se pudo guardar.");
  } finally {
    guardando.value = false;
  }
}
</script>

<style scoped>
.panel {
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.75rem);
  overflow: hidden;
}
</style>
