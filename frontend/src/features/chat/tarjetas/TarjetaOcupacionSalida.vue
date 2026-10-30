<!--
  Cuerpo de la tarjeta "Ocupación por vendedor" de una salida (mensaje
  predeterminado "Salida iniciada", ADR-045): la misma tabla del PDF,
  calculada al leer. Los ceros van apagados para que resalte lo que hay.
-->
<template>
  <div class="flex flex-col gap-3 text-sm">
    <div>
      <div class="font-semibold leading-snug">{{ d.titulo }}</div>
      <div class="text-xs text-muted-color first-letter:uppercase">
        <template v-if="d.salida">{{ fecha(d.salida) }}</template
        ><template v-if="d.bus"> · bus {{ d.bus }}</template
        ><template v-if="d.empresa"> · {{ d.empresa }}</template>
      </div>
    </div>

    <div class="cifras">
      <div v-for="c in cifras" :key="c.etiqueta" class="cifra" :class="c.clase">
        <span class="cifra__n">{{ c.n }}</span>
        <span class="cifra__lbl">{{ c.etiqueta }}</span>
      </div>
    </div>

    <p v-if="!d.filas.length" class="m-0 text-muted-color">No se vendió ningún boleto en esta salida.</p>
    <div v-else class="tabla-envoltura">
      <div class="tabla" role="table" :aria-label="`Asientos por vendedor de ${d.titulo}`" :style="{ gridTemplateColumns: `minmax(4.5rem, 1fr) repeat(${claves.length}, auto)` }">
        <div class="fila fila--cabeza" role="row">
          <span class="celda celda--usuario" role="columnheader">USER</span>
          <span v-for="k in claves" :key="k" class="celda" role="columnheader" :title="d.columnas[k]">{{ CORTAS[k] ?? d.columnas[k] }}</span>
        </div>
        <div v-for="f in d.filas" :key="f.usuario" class="fila" role="row">
          <span class="celda celda--usuario" role="cell" :title="f.usuario">{{ f.usuario }}</span>
          <span v-for="k in claves" :key="k" class="celda" :class="{ 'celda--cero': !f.cuenta[k], 'celda--total': k === 'total' }" role="cell">{{ f.cuenta[k] ?? 0 }}</span>
        </div>
        <div class="fila fila--total" role="row">
          <span class="celda celda--usuario" role="cell">Total</span>
          <span v-for="k in claves" :key="k" class="celda" role="cell">{{ d.totales[k] ?? 0 }}</span>
        </div>
      </div>
    </div>
    <p v-if="d.filas.length" class="m-0 text-xs text-muted-color">Asientos ocupados por categoría; Anulados, los boletos que se anularon. Se solapan: el total es A + B.</p>
  </div>
</template>

<script setup lang="ts">
interface Ocupacion {
  titulo: string;
  salida: string | null;
  bus: string | null;
  empresa: string | null;
  asientos: number;
  ocupados: number;
  conIntermedias: number;
  libres: number;
  porCanal: Record<string, number>;
  columnas: Record<string, string>;
  filas: { usuario: string; cuenta: Record<string, number> }[];
  totales: Record<string, number>;
}

const props = defineProps<{ datos: unknown }>();
const d = computed(() => props.datos as Ocupacion);
const claves = computed(() => Object.keys(d.value.columnas));

/** Encabezados cortos: la tabla cabe en la burbuja; el nombre completo va en el `title`. */
const CORTAS: Record<string, string> = { reasignados: "Reas.", anulados: "Anul.", vouchers: "Vouch.", cortesias: "Cort.", intermedias: "Interm.", claseA: "A", claseB: "B", total: "Total" };

const cifras = computed(() => [
  { etiqueta: "Ocupados", n: d.value.ocupados, clase: "cifra--fuerte" },
  { etiqueta: "Libres", n: d.value.libres, clase: "" },
  { etiqueta: "Intermedios", n: d.value.conIntermedias, clase: "" },
  { etiqueta: "Estación", n: d.value.porCanal.estacion ?? 0, clase: "cifra--estacion" },
  { etiqueta: "Agencia", n: d.value.porCanal.agencia ?? 0, clase: "cifra--agencia" },
  { etiqueta: "Web", n: d.value.porCanal.web ?? 0, clase: "cifra--web" },
]);
const fecha = (iso: string) => new Intl.DateTimeFormat("es-GT", { weekday: "short", day: "numeric", month: "short", hour: "numeric", minute: "2-digit" }).format(new Date(iso));
</script>

<style scoped>
.cifras {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.35rem;
}
.cifra {
  display: flex;
  flex-direction: column;
  padding: 0.35rem 0.5rem;
  border-left: 3px solid var(--p-surface-300);
  border-radius: 0.4rem;
  background: var(--p-surface-50);
}
.cifra__n {
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.2;
  font-variant-numeric: tabular-nums;
}
.cifra__lbl {
  font-size: 0.68rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--p-surface-500);
}
.cifra--fuerte {
  border-color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
}
/* Mismos tonos que la tarjeta de la salida y el croquis. */
.cifra--estacion {
  border-color: var(--p-blue-500);
}
.cifra--agencia {
  border-color: var(--p-orange-500);
}
.cifra--web {
  border-color: var(--p-amber-500);
}

.tabla-envoltura {
  overflow-x: auto;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
}
.tabla {
  display: grid;
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
}
/* Cada fila aporta sus celdas a la grilla del padre: columnas alineadas sin <table>. */
.fila {
  display: contents;
}
.celda {
  padding: 0.25rem 0.3rem;
  text-align: right;
  border-bottom: 1px solid var(--p-surface-100);
}
.celda--usuario {
  position: sticky;
  left: 0;
  max-width: 7rem;
  overflow: hidden;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
  background: var(--p-content-background);
}
.celda--cero {
  color: var(--p-surface-400);
}
.celda--total {
  font-weight: 600;
}
.fila--cabeza .celda {
  font-size: 0.6rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--p-surface-500);
  background: var(--p-surface-50);
}
.fila--total .celda {
  font-weight: 700;
  border-bottom: 0;
  background: color-mix(in srgb, var(--p-primary-color) 8%, var(--p-content-background));
}
</style>
