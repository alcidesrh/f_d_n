<!--
  Crear o editar una caja de la administración: título, ícono, si se
  muestra y quién la ve. Emite `guardar` con el payload completo (acceso
  incluido); la página decide si crea o guarda.
-->
<template>
  <Dialog :visible="visible" modal :header="inicial ? 'Editar caja' : 'Nueva caja'" class="w-[min(44rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)">
    <form class="flex flex-col gap-4" @submit.prevent="guardar">
      <div class="vista-previa">
        <span class="vista-previa-icono"><icon :name="borrador.icono || 'help-outline'" sm color="text-inherit" /></span>
        <span class="truncate font-semibold">{{ borrador.titulo || "Título de la caja" }}</span>
      </div>

      <Tabs v-model:value="pestana">
        <TabList>
          <Tab value="datos">Caja</Tab>
          <Tab value="icono">Ícono</Tab>
          <Tab value="acceso">Quién la ve<span v-if="borrador.acceso.roles.length || borrador.acceso.usuarios.length" class="ml-1 text-primary">●</span></Tab>
        </TabList>
        <TabPanels class="!px-0">
          <TabPanel value="datos" class="flex flex-col gap-4">
            <label class="flex flex-col gap-1">
              <span class="text-sm font-medium">Título</span>
              <InputText v-model="borrador.titulo" maxlength="80" autofocus fluid />
            </label>
            <label class="flex items-center gap-2 text-sm">
              <ToggleSwitch v-model="borrador.activa" />
              Se muestra (apagada, solo se ve al configurar)
            </label>
          </TabPanel>
          <TabPanel value="icono">
            <IconPicker v-model="borrador.icono" height="18rem" />
          </TabPanel>
          <TabPanel value="acceso">
            <AccesoEditor v-model="borrador.acceso" />
          </TabPanel>
        </TabPanels>
      </Tabs>

      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
        <Button type="submit" label="Guardar" :disabled="!valido" :loading="guardando" />
      </div>
    </form>
  </Dialog>
</template>

<script setup lang="ts">
import type { Acceso, Seccion, SeccionPayload } from "@/core/administracion/api";
import { accesoPayload } from "@/core/administracion/disposicion";
import AccesoEditor from "./AccesoEditor.vue";

const props = defineProps<{ visible: boolean; inicial: Seccion | null; guardando?: boolean }>();
const emit = defineEmits<{ "update:visible": [visible: boolean]; guardar: [payload: SeccionPayload] }>();

interface Borrador {
  titulo: string;
  icono: string;
  color: string;
  activa: boolean;
  acceso: Acceso;
}

const nuevo = (): Borrador => ({ titulo: "", icono: "folder-outline", color: "blue", activa: true, acceso: { roles: [], usuarios: [] } });
const borrador = ref<Borrador>(nuevo());
const pestana = ref("datos");

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return;
    const s = props.inicial;
    borrador.value = s ? { titulo: s.titulo, icono: s.icono, color: s.color, activa: s.activa, acceso: s.acceso ?? { roles: [], usuarios: [] } } : nuevo();
    pestana.value = "datos";
  },
  { immediate: true },
);

const valido = computed(() => borrador.value.titulo.trim() !== "" && !!borrador.value.icono);

function guardar() {
  if (!valido.value) return;
  const b = borrador.value;
  emit("guardar", { titulo: b.titulo.trim(), icono: b.icono, color: b.color, activa: b.activa, acceso: accesoPayload(b.acceso) });
}
</script>

<style scoped>
.vista-previa {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.625rem 0.75rem;
  border-radius: 0.75rem;
  border: 1px solid var(--p-surface-200);
  border-top: 4px solid var(--p-surface-500);
  background: var(--p-surface-50);
  color: var(--p-surface-900);
}
.vista-previa-icono {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  background: var(--p-surface-500);
  color: var(--p-surface-50);
}
</style>
