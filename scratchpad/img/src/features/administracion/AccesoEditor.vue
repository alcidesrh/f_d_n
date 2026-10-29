<!--
  Quién ve una caja o un enlace: roles (también los usuarios con un rol
  ascendiente, como en los menús) y usuarios sueltos, buscados en el
  servidor. Sin ninguno, cualquiera con sesión.
-->
<template>
  <div class="flex flex-col gap-3">
    <Message :severity="libre ? 'secondary' : 'info'" :closable="false" size="small">
      <template v-if="libre">Sin roles ni usuarios: {{ sujeto }} cualquiera con sesión.</template>
      <template v-else>Solo {{ sujeto }} quien tenga alguno de estos roles (o uno superior) o esté entre estos usuarios.</template>
    </Message>

    <label class="flex flex-col gap-1">
      <span class="text-sm font-medium">Roles</span>
      <MultiSelect v-model="roles" :options="opcionesRoles" option-label="label" data-key="id" filter display="chip" placeholder="Cualquier rol" :loading="cargandoRoles" fluid :max-selected-labels="6" />
    </label>

    <label class="flex flex-col gap-1">
      <span class="text-sm font-medium">Usuarios</span>
      <AutoComplete v-model="usuarios" :suggestions="sugerencias" option-label="label" data-key="id" multiple :delay="300" placeholder="Buscar usuario" empty-search-message="Sin coincidencias" fluid @complete="buscarUsuarios($event.query)" />
    </label>
  </div>
</template>

<script setup lang="ts">
import type { Acceso, Persona } from "@/core/administracion/api";
import { idDeOpcion } from "@/core/entities/busquedaRemota";
import { graphql } from "@/core/graphql/client";
import type { AgnosticOption } from "@/core/graphql/types";

withDefaults(defineProps<{ sujeto?: string }>(), { sujeto: "la ve" });
const modelo = defineModel<Acceso>({ required: true });

const aPersona = (o: AgnosticOption): Persona => ({ id: idDeOpcion(o), label: o.label });

const roles = computed({
  get: () => modelo.value.roles,
  set: (roles: Persona[]) => (modelo.value = { ...modelo.value, roles }),
});
const usuarios = computed({
  get: () => modelo.value.usuarios,
  set: (usuarios: Persona[] | null) => (modelo.value = { ...modelo.value, usuarios: (usuarios ?? []).filter((u) => typeof u === "object") }),
});
const libre = computed(() => !modelo.value.roles.length && !modelo.value.usuarios.length);

const opcionesRoles = ref<Persona[]>([]);
const cargandoRoles = ref(true);
onMounted(async () => {
  try {
    opcionesRoles.value = (await graphql.agnosticList("Role")).map(aPersona).sort((a, b) => a.label.localeCompare(b.label));
  } finally {
    cargandoRoles.value = false;
  }
});

const sugerencias = ref<Persona[]>([]);
let ultima = "";
/** Solo cuenta la respuesta de lo último tecleado; no ofrece a los ya elegidos. */
async function buscarUsuarios(texto: string) {
  ultima = texto;
  const encontrados = (await graphql.agnosticSearch("Usuario", { search: texto.trim(), limit: 20 })).map(aPersona);
  if (ultima === texto) sugerencias.value = encontrados.filter((u) => !modelo.value.usuarios.some((x) => x.id === u.id));
}

</script>
