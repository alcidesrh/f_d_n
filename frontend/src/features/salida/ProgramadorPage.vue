<!--
  Programador de salidas (ADR-024): ruta, horas del día (cada una con su
  distribución de asientos y un bus que la tiene, ADR-031) y días (uno solo,
  N días o hasta una fecha, cada X días). Un usuario sin empresa puede
  elegirla para ver solo sus distribuciones y buses. La vista previa se pide
  sola y marca las que ya existen, chocan con otro viaje del bus o ya
  pasaron; solo se crean las nuevas, y tras crear se ve lo creado.
  Esquemas: la configuración se puede guardar con un nombre al crear, y un
  esquema guardado se carga, cambia (buses, horas, quitar horas) o borra aquí mismo.

  Estado y acciones en `programador/store.ts`; cada sección es un componente.
  Contenido angosto: una columna; ancho: formulario y vista previa al lado.
-->
<template>
  <div class="@container flex flex-col gap-4">
    <nav class="flex flex-wrap justify-end gap-1" aria-label="Otras vistas de salidas">
      <RouterLink :to="{ name: 'salidas-calendario' }" class="no-underline">
        <Button label="Calendario" severity="secondary" text size="small">
          <template #icon><icon name="calendar-view-week" class="mr-1" /></template>
        </Button>
      </RouterLink>
      <RouterLink :to="{ name: 'salidas' }" class="no-underline">
        <Button label="Ver salidas" severity="secondary" text size="small">
          <template #icon><icon name="list" class="mr-1" /></template>
        </Button>
      </RouterLink>
    </nav>

    <Message v-if="store.sinPermiso" severity="warn" :closable="false">No tiene permiso para programar salidas (salida.crear).</Message>

    <div v-else class="programador">
      <div class="programador__form">
        <EsquemaPanel />
        <RutaPanel />
        <HorasPanel />
        <DiasPanel />
      </div>
      <aside class="programador__previa">
        <VistaPreviaPanel />
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import DiasPanel from './programador/DiasPanel.vue'
import EsquemaPanel from './programador/EsquemaPanel.vue'
import HorasPanel from './programador/HorasPanel.vue'
import RutaPanel from './programador/RutaPanel.vue'
import VistaPreviaPanel from './programador/VistaPreviaPanel.vue'
import { useProgramadorStore } from './programador/store'

const route = useRoute()
const store = useProgramadorStore()

onMounted(() => void store.iniciar(Number(route.query.esquema) || null))
</script>

<style scoped>
.programador {
  display: grid;
  gap: 1.5rem;
}
.programador__form {
  display: flex;
  min-width: 0;
  flex-direction: column;
}
/* Cada sección separada por una línea, sin tarjetas. */
.programador__form > :deep(section) {
  padding-block: 1.25rem;
  border-bottom: 1px solid var(--p-content-border-color);
}
.programador__form > :deep(section:first-child) {
  padding-top: 0;
}
.programador__previa {
  min-width: 0;
  padding: 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.75rem);
  background: var(--p-content-background);
}
@container (min-width: 64rem) {
  .programador {
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    align-items: start;
  }
  .programador__previa {
    position: sticky;
    top: 1rem;
  }
}
:deep(.pg-titulo) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}
:deep(.pg-paso) {
  display: inline-grid;
  place-items: center;
  width: 1.4rem;
  height: 1.4rem;
  border-radius: 999px;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
}
</style>
