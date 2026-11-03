<!--
  A qué roles se le muestra una caja o un enlace (ADR-049): curaduría, no
  seguridad. Sin ninguno, a todos; cada uno ve igual solo lo que puede abrir.
-->
<template>
  <div class="flex flex-col gap-3">
    <Message severity="secondary" :closable="false" size="small">
      <template v-if="!roles.length">Sin roles: {{ sujeto }} todos, si pueden abrir lo que enlaza.</template>
      <template v-else>Solo {{ sujeto }} quien tenga alguno de estos roles (y pueda abrir lo que enlaza). El de sistema lo ve siempre.</template>
    </Message>

    <label class="flex flex-col gap-1">
      <span class="text-sm font-medium">Roles</span>
      <MultiSelect v-model="roles" :options="opcionesRoles" option-label="label" data-key="id" filter display="chip" placeholder="Todos los roles" :loading="cargandoRoles" fluid :max-selected-labels="6" />
    </label>
  </div>
</template>

<script setup lang="ts">
import type { Persona } from "@/core/administracion/api";
import { idDeOpcion } from "@/core/entities/busquedaRemota";
import { graphql } from "@/core/graphql/client";
import type { AgnosticOption } from "@/core/graphql/types";

withDefaults(defineProps<{ sujeto?: string }>(), { sujeto: "la ven" });
const roles = defineModel<Persona[]>({ required: true });

const aPersona = (o: AgnosticOption): Persona => ({ id: idDeOpcion(o), label: o.label });

const opcionesRoles = ref<Persona[]>([]);
const cargandoRoles = ref(true);
onMounted(async () => {
  try {
    opcionesRoles.value = (await graphql.agnosticList("Role")).map(aPersona).sort((a, b) => a.label.localeCompare(b.label));
  } finally {
    cargandoRoles.value = false;
  }
});
</script>
