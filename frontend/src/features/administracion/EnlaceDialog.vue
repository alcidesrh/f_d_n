<!--
  Crear o editar un enlace de una caja: texto, a dónde lleva (una pantalla
  de la aplicación con sus parámetros o un path escrito a mano), su
  "Agregar nuevo" opcional, otros nombres para la búsqueda, ícono, si se
  muestra y quién lo ve. Emite `guardar` con el payload completo.
-->
<template>
  <Dialog :visible="visible" modal :header="inicial ? 'Editar enlace' : `Nuevo enlace en «${caja}»`" class="w-[min(46rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)">
    <form class="flex flex-col gap-4" @submit.prevent="guardar">
      <Tabs v-model:value="pestana">
        <TabList>
          <Tab value="datos">Enlace</Tab>
          <Tab value="icono">Ícono</Tab>
          <Tab value="acceso">Quién lo ve<span v-if="borrador.acceso.roles.length || borrador.acceso.usuarios.length" class="ml-1 text-primary">●</span></Tab>
        </TabList>
        <TabPanels class="!px-0">
          <TabPanel value="datos" class="flex flex-col gap-4">
            <div class="grid grid-cols-[auto_minmax(0,1fr)] items-end gap-3">
              <button type="button" class="icono-actual tap-target" aria-label="Cambiar ícono" v-tooltip.top="'Cambiar ícono'" @click="pestana = 'icono'">
                <icon :name="borrador.icono || 'help-outline'" />
              </button>
              <label class="flex flex-col gap-1">
                <span class="text-sm font-medium">Texto</span>
                <InputText v-model="borrador.etiqueta" maxlength="80" autofocus fluid />
              </label>
            </div>

            <fieldset class="grupo">
              <legend>Al pulsarlo abre</legend>
              <DestinoEditor v-model="borrador.abrir" titulo="Abrir" />
            </fieldset>

            <fieldset class="grupo">
              <legend>«Agregar nuevo» (el botón +)</legend>
              <DestinoEditor v-model="borrador.agregar" titulo="Agregar nuevo" opcional />
            </fieldset>

            <label class="flex flex-col gap-1">
              <span class="text-sm font-medium">Otros nombres para buscarlo</span>
              <AutoComplete v-model="borrador.claves" multiple :typeahead="false" placeholder="Escribí y Enter (p. ej. el nombre del legado)" fluid />
            </label>

            <label class="flex items-center gap-2 text-sm">
              <ToggleSwitch v-model="borrador.activo" />
              Se muestra (apagado, solo se ve al configurar)
            </label>
          </TabPanel>
          <TabPanel value="icono">
            <IconPicker v-model="borrador.icono" height="18rem" @select="pestana = 'datos'" />
          </TabPanel>
          <TabPanel value="acceso">
            <AccesoEditor v-model="borrador.acceso" sujeto="lo ve" />
            <p class="mt-3 text-xs text-muted-color">Además, solo lo ve quien ve su caja.</p>
          </TabPanel>
        </TabPanels>
      </Tabs>

      <div class="flex items-center justify-end gap-2">
        <small v-if="!valido && borrador.etiqueta.trim()" class="mr-auto text-muted-color">Completá a dónde lleva (y su «Agregar», si tiene).</small>
        <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
        <Button type="submit" label="Guardar" :disabled="!valido" :loading="guardando" />
      </div>
    </form>
  </Dialog>
</template>

<script setup lang="ts">
import type { Acceso, Destino, Enlace, EnlacePayload } from "@/core/administracion/api";
import { accesoPayload } from "@/core/administracion/disposicion";
import AccesoEditor from "./AccesoEditor.vue";
import DestinoEditor from "./DestinoEditor.vue";
import { useRutasApp } from "./rutasApp";

const props = defineProps<{ visible: boolean; inicial: Enlace | null; caja: string; guardando?: boolean }>();
const emit = defineEmits<{ "update:visible": [visible: boolean]; guardar: [payload: EnlacePayload] }>();

interface Borrador {
  etiqueta: string;
  icono: string;
  claves: string[];
  abrir: Destino | null;
  agregar: Destino | null;
  activo: boolean;
  acceso: Acceso;
}

const nuevo = (): Borrador => ({ etiqueta: "", icono: "link", claves: [], abrir: { ruta: "", parametros: {}, path: null }, agregar: null, activo: true, acceso: { roles: [], usuarios: [] } });
const borrador = ref<Borrador>(nuevo());
const pestana = ref("datos");
const { valido: destinoValido } = useRutasApp();

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return;
    const e = props.inicial;
    borrador.value = e
      ? { etiqueta: e.etiqueta, icono: e.icono, claves: [...e.claves], abrir: { ...e.abrir }, agregar: e.agregar ? { ...e.agregar } : null, activo: e.activo, acceso: e.acceso ?? { roles: [], usuarios: [] } }
      : nuevo();
    pestana.value = "datos";
  },
  { immediate: true },
);

const valido = computed(() => {
  const b = borrador.value;
  return b.etiqueta.trim() !== "" && !!b.icono && destinoValido(b.abrir) && (b.agregar === null || destinoValido(b.agregar));
});

function guardar() {
  if (!valido.value) return;
  const b = borrador.value;
  emit("guardar", {
    etiqueta: b.etiqueta.trim(),
    icono: b.icono,
    claves: b.claves.map((c) => c.trim()).filter(Boolean),
    abrir: b.abrir!,
    agregar: b.agregar,
    activo: b.activo,
    acceso: accesoPayload(b.acceso),
  });
}
</script>

<style scoped>
.grupo {
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.75rem;
  padding: 0.5rem 0.75rem 0.75rem;
}
.grupo legend {
  padding-inline: 0.25rem;
  font-size: 0.875rem;
  font-weight: 500;
}
.icono-actual {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--p-content-border-color);
  background: var(--p-surface-50);
}
.icono-actual:hover {
  border-color: var(--p-primary-color);
}
</style>
