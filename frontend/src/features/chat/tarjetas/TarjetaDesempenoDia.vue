<!--
  Cuerpo de la tarjeta "Desempeño del día" (mensaje predeterminado, ADR-045):
  ingresos del día por canal, ruta completa e intermedia, la operación y
  todas las estaciones que vendieron (la fecha la dice el sello). Mismo diseño que el reporte de referencia (burbuja
  del chat con barras apiladas y chips con punto); calculada al leer.
-->
<template>
  <div class="desempeno flex flex-col gap-5 text-sm">
    <div class="empresa">{{ d.empresa }}</div>

    <p v-if="d.vacio" class="m-0 text-muted-color">Hoy no hubo ventas ni encomiendas.</p>

    <template v-else>
      <section class="seccion">
        <div class="total tabular">
          <b>{{ d.total }}</b>
          <span>ingresos totales</span>
        </div>
        <div class="chips tabular">
          <span v-for="c in d.canales" :key="c.clave" class="chip" :class="{ vacio: !c.boletos }">
            <i class="punto" :class="c.clave" />{{ c.nombre }} {{ c.ingresos }} · {{ c.boletos }}
          </span>
        </div>
        <div class="barra" role="img" :aria-label="resumenCanales">
          <span v-for="c in d.canales" :key="c.clave" :class="c.clave" :style="{ width: `${c.porcentaje}%` }" />
        </div>
      </section>

      <section class="seccion">
        <div class="rotulo">Boletos · {{ d.boletos.cantidad }}</div>
        <div class="barra" role="img" :aria-label="`Ruta completa ${d.ruta.porcentaje} %, intermedia ${d.intermedia.porcentaje} %`">
          <span class="completa" :style="{ width: `${d.ruta.porcentaje}%` }" />
          <span class="intermedia" :style="{ width: `${d.intermedia.porcentaje}%` }" />
        </div>
        <div class="pie tabular">
          <span><b>{{ d.ruta.cantidad }}</b> ruta completa · {{ d.ruta.ingresos }}</span>
          <span><b>{{ d.intermedia.cantidad }}</b> intermedia · {{ d.intermedia.ingresos }}</span>
        </div>
        <div class="chips tabular">
          <span class="chip" :class="{ vacio: !d.encomiendas.cantidad }">Encomiendas {{ d.encomiendas.ingresos }} · {{ d.encomiendas.cantidad }}</span>
        </div>
      </section>

      <section class="seccion">
        <div class="rotulo">Operación</div>
        <table class="operacion tabular">
          <tbody>
            <tr v-for="o in operacion" :key="o.nombre" :class="{ vacio: !o.n, aviso: o.aviso && o.n > 0 }">
              <th scope="row"><i v-if="o.punto" class="punto" :class="o.punto" />{{ o.nombre }}</th>
              <td>{{ o.n }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-if="d.ranking.length" class="seccion">
        <div class="rotulo">Estaciones · {{ d.ranking.length }}</div>
        <ol class="estaciones tabular">
          <li v-for="(r, i) in d.ranking" :key="r.nombre">
            <span class="n">{{ i + 1 }}</span>
            <span class="nombre">{{ r.nombre }}</span>
            <span class="monto">{{ r.ingresos }}</span>
            <div class="mini"><span :style="{ width: `${r.porcentaje}%` }" /></div>
            <span class="detalle">{{ r.boletos }} boletos · {{ r.encomiendas }} encomiendas</span>
          </li>
        </ol>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
interface Canal {
  clave: string;
  nombre: string;
  ingresos: string;
  boletos: number;
  porcentaje: number;
}

interface Desempeno {
  titulo: string;
  empresa: string;
  vacio: boolean;
  total: string;
  boletos: { ingresos: string; cantidad: number };
  encomiendas: { ingresos: string; cantidad: number };
  ruta: { ingresos: string; cantidad: number; porcentaje: number };
  intermedia: { ingresos: string; cantidad: number; porcentaje: number };
  canales: Canal[];
  operacion: { reasignados: number; anulados: number; cancelados: number; cortesias: number; vouchers: number };
  ranking: { nombre: string; ingresos: string; boletos: number; encomiendas: number; porcentaje: number }[];
}

const props = defineProps<{ datos: unknown }>();
const d = computed(() => props.datos as Desempeno);
const operacion = computed(() => {
  const o = d.value.operacion;
  return [
    { nombre: "Cortesías", n: o.cortesias, punto: "cortesia", aviso: false },
    { nombre: "Cancelados", n: o.cancelados, punto: "", aviso: true },
    { nombre: "Reasignados", n: o.reasignados, punto: "", aviso: false },
    { nombre: "Anulados", n: o.anulados, punto: "", aviso: false },
    { nombre: "Vouchers", n: o.vouchers, punto: "", aviso: false },
  ];
});
const resumenCanales = computed(() => d.value.canales.map((c) => `${c.nombre} ${c.porcentaje} %`).join(", "));
</script>

<style scoped>
/* Fondo blanco fijo (también en modo oscuro), como las demás tarjetas del chat. */
.desempeno {
  --p-text-color: #0f172a;
  --p-text-muted-color: #64748b;
  padding: 0.85rem 0.95rem;
  border-radius: 0.75rem;
  color: #0f172a;
  background: #ffffff;
  box-shadow: var(--shadow-sm);
}
.empresa {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
.tabular {
  font-variant-numeric: tabular-nums;
}
.total {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
}
.total b {
  font-weight: 700;
}
.total span {
  font-size: 0.75rem;
  color: #64748b;
}
.seccion {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}
.rotulo {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
.barra {
  display: flex;
  height: 0.5rem;
  overflow: hidden;
  border-radius: 999px;
  background: #e2e8f0;
}
.barra span {
  display: block;
  height: 100%;
}
.pie {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.75rem;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  font-size: 0.75rem;
  background: #f1f5f9;
}
.chip.vacio {
  color: #64748b;
}
.punto {
  display: inline-block;
  width: 0.45rem;
  height: 0.45rem;
  flex-shrink: 0;
  border-radius: 50%;
}
/* Los canales, con los del croquis: estación emerald (ocupado), agencia azul, página ámbar. */
.estacion {
  background: #10b981;
}
.web {
  background: #f59e0b;
}
.agencia {
  background: #3b82f6;
}
.cortesia {
  background: #84cc16;
}
/* Ruta completa e intermedia no son canales: tonos neutros para no confundirlos con la agencia. */
.completa {
  background: #334155;
}
.intermedia {
  background: #cbd5e1;
}
.operacion {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
}
.operacion tr + tr {
  border-top: 1px solid #f1f5f9;
}
.operacion th {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.2rem 0;
  font-weight: 400;
  text-align: left;
}
.operacion td {
  padding: 0.2rem 0;
  font-weight: 600;
  text-align: right;
}
.operacion .vacio {
  color: #64748b;
}
.operacion .vacio td {
  font-weight: 400;
}
.operacion .aviso {
  color: #b45309;
}
/* Todas las que vendieron; la altura de antes (unas cinco) y el resto con scroll. */
.estaciones {
  max-height: 15rem;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding-right: 0.25rem;
}
ol {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin: 0;
  padding: 0;
  list-style: none;
}
li {
  display: grid;
  grid-template-columns: 1rem minmax(0, 1fr) auto;
  align-items: baseline;
  column-gap: 0.4rem;
  row-gap: 0.15rem;
  font-size: 0.8rem;
}
li .n {
  font-size: 0.75rem;
  color: #64748b;
}
li .nombre {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
li .monto {
  font-weight: 600;
}
li .mini {
  grid-column: 2 / 4;
  height: 0.25rem;
  overflow: hidden;
  border-radius: 999px;
  background: #e2e8f0;
}
li .mini span {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: #10b981;
}
li .detalle {
  grid-column: 2 / 4;
  font-size: 0.75rem;
  color: #64748b;
}
</style>
