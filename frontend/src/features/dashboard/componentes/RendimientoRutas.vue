<!--
  Rendimiento por ruta de las salidas que ya salieron en el periodo:
  ocupación (con su cambio en puntos), ingresos e ingreso por salida.
  Señales: saturada (≥ 85%: sumar frecuencia o bus más grande) y baja
  (< 35%: sobran salidas o hay que promoverla). Ordenable; tabla en
  pantallas anchas y tarjetas en angostas.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <SelectButton v-model="filtro" :options="FILTROS" option-label="etiqueta" option-value="id" :allow-empty="false" size="small" aria-label="Filtrar rutas" />
      <p class="ml-auto text-xs text-muted-color">
        {{ resumen.rutas80 }} de {{ rutas.length }} rutas hacen el 80% de los ingresos<template v-if="resumen.bajas">
          · las de baja ocupación aportan {{ porciento(resumen.ingresoBajas) }}</template
        >
      </p>
    </div>

    <p v-if="!visibles.length" class="py-4 text-center text-sm text-muted-color">
      {{ rutas.length ? 'Ninguna ruta con esa señal en el periodo.' : 'No hay salidas que ya hayan salido en el periodo.' }}
    </p>

    <div v-else class="tabla" role="table" aria-label="Rendimiento por ruta">
      <div class="fila cabecera" role="row">
        <button v-for="c in COLUMNAS" :key="c.id" type="button" role="columnheader" class="celda" :class="c.clase" :aria-sort="orden.campo === c.id ? (orden.desc ? 'descending' : 'ascending') : 'none'" @click="ordenar(c.id)">
          {{ c.etiqueta }}<icon v-if="orden.campo === c.id" :name="orden.desc ? 'arrow-downward' : 'arrow-upward'" sm />
        </button>
      </div>
      <div v-for="r in pagina" :key="r.id" class="fila" role="row">
        <div class="celda nombre" role="cell">
          <span class="truncate" :title="r.nombre">{{ r.nombre }}</span>
          <Tag v-if="r.senal" :severity="r.senal === 'saturada' ? 'success' : 'warn'" :value="r.senal === 'saturada' ? 'Saturada' : 'Baja'" class="!px-1.5 !py-0 !text-[0.65rem]" />
        </div>
        <div class="celda num" role="cell" data-etiqueta="Salidas">{{ numero(r.salidas) }}</div>
        <div class="celda ocup" role="cell" data-etiqueta="Ocupación">
          <BarraOcupacion :valor="r.ocupacion" class="flex-1" />
          <span v-if="variacion(r.ocupacion, r.ocupacionAnterior, { puntos: true })" class="delta" :class="variacion(r.ocupacion, r.ocupacionAnterior, { puntos: true })!.valoracion">
            {{ variacion(r.ocupacion, r.ocupacionAnterior, { puntos: true })!.texto }}
          </span>
        </div>
        <div class="celda num" role="cell" data-etiqueta="Ingresos">{{ dinero(r.ingresos, true) }}</div>
        <div class="celda num" role="cell" data-etiqueta="Por salida">{{ dinero(r.ingresoPorSalida, true) }}</div>
        <div class="celda num" role="cell" data-etiqueta="Boletos">{{ numero(r.boletos) }}</div>
      </div>
    </div>
    <Button v-if="ordenadas.length > LIMITE" :label="todas ? 'Ver menos' : `Ver las ${ordenadas.length} rutas`" text size="small" class="self-start" @click="todas = !todas" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { resumenRutas } from '@/core/tablero/analisis'
import { dinero, numero, porciento, variacion } from '@/core/tablero/formato'
import type { RutaTablero } from '@/core/tablero/types'
import BarraOcupacion from './BarraOcupacion.vue'

const props = defineProps<{ rutas: RutaTablero[] }>()

type Campo = 'nombre' | 'salidas' | 'ocupacion' | 'ingresos' | 'ingresoPorSalida' | 'boletos'
const COLUMNAS: { id: Campo; etiqueta: string; clase: string }[] = [
  { id: 'nombre', etiqueta: 'Ruta', clase: 'nombre' },
  { id: 'salidas', etiqueta: 'Salidas', clase: 'num' },
  { id: 'ocupacion', etiqueta: 'Ocupación', clase: 'ocup' },
  { id: 'ingresos', etiqueta: 'Ingresos', clase: 'num' },
  { id: 'ingresoPorSalida', etiqueta: 'Por salida', clase: 'num' },
  { id: 'boletos', etiqueta: 'Boletos', clase: 'num' },
]
type Filtro = 'todas' | 'saturada' | 'baja'
const FILTROS: { id: Filtro; etiqueta: string }[] = [
  { id: 'todas', etiqueta: 'Todas' },
  { id: 'saturada', etiqueta: 'Saturadas' },
  { id: 'baja', etiqueta: 'Baja ocupación' },
]
const LIMITE = 10

const filtro = ref<Filtro>('todas')
const orden = ref<{ campo: Campo; desc: boolean }>({ campo: 'ingresos', desc: true })
const todas = ref(false)

const resumen = computed(() => resumenRutas(props.rutas))
const visibles = computed(() => (filtro.value === 'todas' ? props.rutas : props.rutas.filter((r) => r.senal === filtro.value)))
const ordenadas = computed(() => {
  const { campo, desc } = orden.value
  return [...visibles.value].sort((a, b) => {
    const x = a[campo] ?? -1
    const y = b[campo] ?? -1
    const c = typeof x === 'string' ? x.localeCompare(String(y), 'es') : (x as number) - (y as number)
    return desc ? -c : c
  })
})
const pagina = computed(() => (todas.value ? ordenadas.value : ordenadas.value.slice(0, LIMITE)))

function ordenar(campo: Campo) {
  orden.value = orden.value.campo === campo ? { campo, desc: !orden.value.desc } : { campo, desc: campo !== 'nombre' }
}
</script>

<style scoped>
.tabla {
  display: flex;
  flex-direction: column;
  font-size: 0.875rem;
}
.fila {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.25rem 0.75rem;
  padding: 0.625rem 0;
  border-top: 1px solid var(--p-content-border-color);
}
.cabecera {
  display: none;
}
.celda {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 0.375rem;
}
.celda.nombre {
  grid-column: 1 / -1;
  font-weight: 500;
}
.celda[data-etiqueta]::before {
  content: attr(data-etiqueta);
  font-size: 0.7rem;
  color: var(--p-text-muted-color);
  margin-right: auto;
}
.celda.ocup {
  grid-column: 1 / -1;
}
.num {
  font-variant-numeric: tabular-nums;
}
.delta {
  font-size: 0.7rem;
  font-weight: 600;
  white-space: nowrap;
  min-width: 3.25rem;
  text-align: end;
}
.delta.buena {
  color: #0a7a0a;
}
.delta.mala {
  color: #b42828;
}
.delta.neutra {
  color: var(--p-text-muted-color);
}
.dark .delta.buena {
  color: #4ccf4c;
}
.dark .delta.mala {
  color: #f08080;
}
@container (min-width: 46rem) {
  .fila {
    grid-template-columns: minmax(0, 2.4fr) 4.5rem minmax(9rem, 1.6fr) 6rem 6rem 5rem;
    padding: 0.5rem 0;
  }
  .cabecera {
    display: grid;
    border-top: 0;
    padding-top: 0;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--p-text-muted-color);
  }
  .cabecera .celda {
    background: none;
    border: 0;
    padding: 0;
    cursor: pointer;
    color: inherit;
    font: inherit;
  }
  .celda.nombre,
  .celda.ocup {
    grid-column: auto;
  }
  .celda[data-etiqueta]::before {
    content: none;
  }
  .num {
    justify-content: flex-end;
  }
}
</style>
