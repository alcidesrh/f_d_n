<!--
  A dónde lleva un enlace: una ruta de la aplicación (las de vue-router, con
  sus parámetros: `entity` se elige de las entidades del schema) o un path
  escrito a mano (`/reporte/ventas?dias=7`, o una dirección http/https).
  Con `opcional`, también "Ninguno" (el "Agregar nuevo" de un enlace).
-->
<template>
  <div class="flex flex-col gap-2">
    <SelectButton v-model="modo" :options="modos" option-label="label" option-value="value" :allow-empty="false" size="small" :aria-label="`${titulo}: tipo de destino`" />

    <template v-if="modo === 'ruta'">
      <Select v-model="ruta" :options="rutas" option-label="label" option-value="name" filter auto-filter-focus :filter-fields="['label', 'path', 'name']" placeholder="Elegí una pantalla" fluid :aria-label="`${titulo}: pantalla`" :invalid="!!modelo?.ruta && !existe(modelo.ruta)">
        <template #option="{ option }">
          <div class="flex min-w-0 flex-col">
            <span class="truncate">{{ option.label }}</span>
            <span class="truncate text-xs text-muted-color">{{ option.path }}</span>
          </div>
        </template>
      </Select>
      <small v-if="modelo?.ruta && !existe(modelo.ruta)" class="text-red-600">La ruta «{{ modelo.ruta }}» ya no existe en la aplicación.</small>
      <div v-for="p in parametros" :key="p.nombre" class="grid grid-cols-[7rem_minmax(0,1fr)] items-center gap-2">
        <label :for="`${uid}-${p.nombre}`" class="truncate text-sm">{{ p.nombre }}<span v-if="!p.opcional" class="text-red-600">*</span></label>
        <Select v-if="p.nombre === 'entity'" :input-id="`${uid}-${p.nombre}`" :model-value="valor(p.nombre)" :options="entidades" option-label="label" option-value="value" filter auto-filter-focus placeholder="Entidad" fluid size="small" @update:model-value="ponerParametro(p.nombre, $event)" />
        <InputText v-else :id="`${uid}-${p.nombre}`" :model-value="valor(p.nombre)" size="small" fluid :placeholder="p.opcional ? 'Opcional' : ''" @update:model-value="ponerParametro(p.nombre, $event ?? '')" />
      </div>
    </template>

    <template v-else-if="modo === 'path'">
      <InputText :model-value="modelo?.path ?? ''" placeholder="/reporte/ventas?dias=7" fluid :invalid="!!modelo?.path && !destinoValido(modelo)" :aria-label="`${titulo}: path`" @update:model-value="modelo = { ruta: null, parametros: {}, path: ($event ?? '').trim() }" />
      <small class="text-muted-color">Empieza con «/» (dentro de la aplicación) o es una dirección http/https (abre en otra pestaña).</small>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Destino } from "@/core/administracion/api";
import { destinoValido } from "@/core/administracion/disposicion";
import { useSchemaStore } from "@/core/entities/schema";
import { entitySlug } from "@/core/entities/slug";
import { useRutasApp } from "./rutasApp";

const props = withDefaults(defineProps<{ titulo: string; opcional?: boolean }>(), { opcional: false });
const modelo = defineModel<Destino | null>({ required: true });

const uid = useId();
const schema = useSchemaStore();
const { rutas, parametrosDe, existe } = useRutasApp();

const entidades = computed(() =>
  Object.values(schema.entities)
    .filter((e) => e.queryCollection)
    .map((e) => ({ value: entitySlug(e.name), label: e.name }))
    .sort((a, b) => a.label.localeCompare(b.label)),
);

type Modo = "ruta" | "path" | "ninguno";
const modos = computed(() => [{ label: "Pantalla", value: "ruta" }, { label: "Path", value: "path" }, ...(props.opcional ? [{ label: "Ninguno", value: "ninguno" }] : [])]);

const modo = computed<Modo>({
  get: () => (modelo.value?.path !== null && modelo.value?.path !== undefined && !modelo.value.ruta ? "path" : modelo.value || !props.opcional ? "ruta" : "ninguno"),
  set: (m) => {
    if (m === "ninguno") modelo.value = null;
    else if (m === "path") modelo.value = { ruta: null, parametros: {}, path: "" };
    else modelo.value = { ruta: "", parametros: {}, path: null };
  },
});

const ruta = computed({
  get: () => modelo.value?.ruta || null,
  set: (nombre: string | null) => {
    const anteriores = modelo.value?.parametros ?? {};
    const nuevos = parametrosDe(nombre);
    // Conserva los valores de los parámetros que la nueva ruta también tiene (`entity` de lista a formulario).
    const parametros = Object.fromEntries(nuevos.filter((p) => anteriores[p.nombre]).map((p) => [p.nombre, anteriores[p.nombre]!]));
    modelo.value = { ruta: nombre ?? "", parametros, path: null };
  },
});

const parametros = computed(() => parametrosDe(modelo.value?.ruta));
const valor = (nombre: string) => modelo.value?.parametros?.[nombre] ?? "";

function ponerParametro(nombre: string, valor: string | null) {
  if (!modelo.value) return;
  const parametros = { ...modelo.value.parametros };
  if (valor) parametros[nombre] = valor;
  else delete parametros[nombre];
  modelo.value = { ...modelo.value, parametros };
}
</script>
