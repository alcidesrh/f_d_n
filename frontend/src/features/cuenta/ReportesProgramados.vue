<!--
  "Reportes y avisos" de Mi cuenta (ADR-044): los reportes que el programador
  de tareas manda cada mañana (o al ocurrir, las alertas) y por dónde los
  quiere el usuario. Cada uno llega recortado a lo que su rol le deja ver.
-->
<template>
  <section v-if="datos && datos.reportes.some((r) => r.puede || r.suscrito)" class="card">
    <h2 class="mb-1 text-base font-semibold">Reportes y avisos</h2>
    <p class="mb-3 text-sm text-muted-color">Llegan a «Avisos del sistema» en el chat o a su correo, con lo que su rol le deja ver.</p>
    <ul class="m-0 flex list-none flex-col gap-3 p-0">
      <li v-for="r in datos.reportes" :key="r.codigo" class="flex flex-col gap-2 @xl:flex-row @xl:items-start @xl:justify-between">
        <div class="min-w-0">
          <span class="block font-medium">{{ r.nombre }}</span>
          <span class="block text-sm text-muted-color">{{ r.puede ? r.descripcion : r.motivo }}</span>
        </div>
        <div class="flex shrink-0 gap-4">
          <label class="flex items-center gap-2 text-sm">
            <Checkbox binary :model-value="r.porChat" :disabled="(!r.puede && !r.porChat) || cambiando === r.codigo" @update:model-value="(v: boolean) => cambiar(r, v, r.porCorreo)" />
            Chat
          </label>
          <label class="flex items-center gap-2 text-sm" :title="datos.correo ? undefined : 'Agregue su correo en Datos personales'">
            <Checkbox binary :model-value="r.porCorreo" :disabled="((!r.puede || !datos.correo) && !r.porCorreo) || cambiando === r.codigo" @update:model-value="(v: boolean) => cambiar(r, r.porChat, v)" />
            Correo
          </label>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { cambiarReporte, fetchMisReportes, type MisReportes, type ReporteSuscribible } from "@/core/cuenta/reportes";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";

const datos = ref<MisReportes | null>(null);
const cambiando = ref<string | null>(null);

onMounted(async () => {
  try {
    datos.value = await fetchMisReportes();
  } catch {
    // Sin la sección: el resto de la cuenta sigue.
  }
});

async function cambiar(r: ReporteSuscribible, porChat: boolean, porCorreo: boolean) {
  cambiando.value = r.codigo;
  try {
    datos.value = await cambiarReporte(r.codigo, porChat, porCorreo);
  } catch (e) {
    notify.error(e instanceof HttpError ? e.message : "No se pudo cambiar el reporte.");
  } finally {
    cambiando.value = null;
  }
}
</script>
