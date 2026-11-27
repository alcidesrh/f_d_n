<!--
  La apariencia del usuario: la sección "Apariencia" de Mi cuenta, que la
  recibe del shell (`EDITOR_DE_APARIENCIA`). Sale entera del catálogo
  (`ajustes.ts`: un grupo por sección, una fila por ajuste); aquí solo se
  decide con qué control se elige cada uno y cómo se dibujan sus opciones.
  Cada cambio se ve al instante y lo guarda la store (`store.ts`).
-->
<template>
  <div class="@container flex flex-col gap-6" aria-label="Personalizar apariencia">
    <!-- Vista previa con los componentes reales: refleja cada cambio al instante. -->
    <div class="flex flex-col gap-3 rounded-xl border border-surface-200 bg-surface-0 p-3 vista-previa @xl:flex-row @xl:items-center @xl:justify-between">
      <div class="flex min-w-0 items-center gap-2.5">
        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary-100 text-primary-700">
          <icon name="palette-outline" size="1.1rem" />
        </span>
        <div class="min-w-0">
          <div class="truncate text-sm font-semibold text-surface-900">Vista previa</div>
          <div class="truncate text-xs text-surface-500">{{ resumen }}</div>
        </div>
      </div>
      <div class="flex min-w-0 flex-wrap gap-2">
        <InputText size="small" placeholder="Un campo" aria-label="Campo de ejemplo" class="min-w-0 flex-1 @xl:w-36 @xl:flex-none" />
        <Button size="small" label="Acción" />
        <Button size="small" label="Secundaria" severity="secondary" outlined />
      </div>
    </div>

    <section v-for="grupo in GRUPOS" :key="grupo.titulo" class="flex flex-col" :aria-labelledby="`apariencia-${grupo.titulo}`">
      <h3 :id="`apariencia-${grupo.titulo}`" class="pb-1 text-xs font-semibold tracking-wide text-surface-500 uppercase">{{ grupo.titulo }}</h3>

      <!-- Una opción por fila: el texto arriba en el teléfono, a la izquierda desde @2xl. -->
      <div
        v-for="clave in grupo.claves"
        :key="clave"
        class="flex flex-col gap-3 border-t border-surface-200 py-4 first-of-type:border-t-0 @2xl:flex-row @2xl:items-center @2xl:justify-between @2xl:gap-6"
      >
        <div class="flex min-w-0 flex-col gap-0.5 @2xl:max-w-72">
          <span :id="`ajuste-${clave}`" class="font-medium text-surface-900">{{ AJUSTES[clave].titulo }}</span>
          <span class="text-sm text-surface-500">
            <template v-if="CON_ELEGIDA.has(clave)">{{ etiquetaDe(clave, apariencia.ajustes[clave]) }}: </template>{{ AJUSTES[clave].descripcion }}
          </span>
        </div>

        <SelectButton
          v-if="CONTROL[clave] === 'segmentos'"
          :model-value="apariencia.ajustes[clave]"
          :options="opciones(clave)"
          option-label="etiqueta"
          option-value="valor"
          :allow-empty="false"
          :aria-labelledby="`ajuste-${clave}`"
          class="w-full @2xl:w-auto"
          fluid
          @update:model-value="elegir(clave, $event)"
        >
          <template #option="{ option }">
            <span class="flex items-center gap-2">
              <icon v-if="option.icono" :name="option.icono" size="1.1rem" />
              {{ option.etiqueta }}
            </span>
          </template>
        </SelectButton>

        <div v-else-if="CONTROL[clave] === 'muestras'" class="flex flex-wrap gap-2" role="group" :aria-labelledby="`ajuste-${clave}`">
          <button
            v-for="o in opciones(clave)"
            :key="String(o.valor)"
            type="button"
            class="tap-target grid size-9 cursor-pointer place-items-center rounded-full transition-transform hover:scale-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
            :class="{ 'ring-2 ring-primary-500 ring-offset-2 ring-offset-surface-0': elegida(clave, o.valor) }"
            :aria-label="o.etiqueta"
            :aria-pressed="elegida(clave, o.valor)"
            :title="o.etiqueta"
            @click="elegir(clave, o.valor)"
          >
            <span class="size-7 rounded-full" :style="{ background: o.muestra }"></span>
          </button>
        </div>

        <div v-else class="grid gap-2" :class="COLUMNAS[clave]" role="group" :aria-labelledby="`ajuste-${clave}`">
          <button
            v-for="o in opciones(clave)"
            :key="String(o.valor)"
            type="button"
            class="mosaico flex min-w-16 cursor-pointer flex-col items-center gap-1.5 rounded-lg border p-2 text-center"
            :class="elegida(clave, o.valor) ? 'border-primary-500 bg-primary-50' : 'border-surface-200 hover:bg-surface-100'"
            :aria-pressed="elegida(clave, o.valor)"
            :title="o.etiqueta"
            @click="elegir(clave, o.valor)"
          >
            <!-- Cada opción se dibuja con su propio valor, no con el elegido. -->
            <span v-if="clave === 'superficie'" class="relative block h-8 w-full rounded-md" :style="{ background: o.muestra }">
              <span class="absolute right-1 bottom-1 size-2.5 rounded-full" :style="{ background: o.acento }"></span>
            </span>
            <span v-else-if="clave === 'fondo'" class="block h-8 w-full rounded-md border border-surface-200 p-1.5" :style="{ background: o.muestra }">
              <span class="block h-full w-2/3 rounded-sm bg-surface-0 shadow-xs"></span>
            </span>
            <span
              v-else-if="clave === 'esquinas'"
              class="block h-8 w-full border-2 border-primary-400 bg-primary-50"
              :style="{ borderRadius: `${8 * Number(o.valor)}px` }"
            ></span>
            <span v-else-if="clave === 'texto'" class="grid h-8 place-items-center leading-none font-medium text-surface-800" :style="{ fontSize: `${o.valor}px` }">Aa</span>
            <span v-else-if="clave === 'fuente'" class="grid h-8 place-items-center text-xl leading-none text-surface-800" :style="{ fontFamily: o.muestra }">Aa</span>
            <span class="flex items-center gap-1 text-xs font-medium" :class="elegida(clave, o.valor) ? 'text-primary-800' : 'text-surface-700'">
              {{ o.etiqueta }}
              <icon v-if="clave === 'estilo' && elegida(clave, o.valor)" name="check" size="0.9rem" />
            </span>
          </button>
        </div>
      </div>
    </section>

    <div class="flex flex-col gap-3 border-t border-surface-200 pt-4 @xl:flex-row @xl:items-center @xl:justify-between">
      <p class="text-sm text-surface-500">
        <template v-if="session.suplantando">Suplantando a otro usuario, los cambios quedan solo en este navegador.</template>
        <template v-else>Se guarda en tu cuenta: la verás igual en otro equipo y en la app móvil.</template>
      </p>
      <!-- Con `label`: el texto en el slot por defecto lo volvería un botón de solo ícono. -->
      <Button text size="small" severity="secondary" label="Restablecer" class="shrink-0 self-end" :disabled="apariencia.esDeFabrica" @click="apariencia.restablecer()">
        <template #icon>
          <icon name="restart-alt" size="1rem" />
        </template>
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useSessionStore } from '@/core/auth/session'
import { AJUSTES, GRUPOS, etiquetaDe, type Ajustes, type ClaveAjuste, type Opcion } from './ajustes'
import { useAparienciaStore } from './store'

type Control = 'segmentos' | 'muestras' | 'mosaicos'

/** Cómo se elige cada ajuste: botones segmentados, círculos de color o mosaicos que dibujan la opción. */
const CONTROL: Record<ClaveAjuste, Control> = {
  modo: 'segmentos',
  primario: 'muestras',
  superficie: 'mosaicos',
  contraste: 'segmentos',
  fondo: 'mosaicos',
  estilo: 'mosaicos',
  esquinas: 'mosaicos',
  densidad: 'segmentos',
  sombras: 'segmentos',
  texto: 'mosaicos',
  fuente: 'mosaicos',
  movimiento: 'segmentos',
}
/** Columnas de los mosaicos: en el teléfono caben tres. */
const COLUMNAS: Partial<Record<ClaveAjuste, string>> = {
  superficie: 'grid-cols-3 @md:grid-cols-5',
  fondo: 'grid-cols-3',
  estilo: 'grid-cols-2 @md:grid-cols-4',
  esquinas: 'grid-cols-3 @md:grid-cols-5',
  texto: 'grid-cols-3 @md:grid-cols-5',
  fuente: 'grid-cols-2',
}
/** Ajustes cuyo nombre de la opción elegida no se lee en el control (los círculos solo tienen `title`). */
const CON_ELEGIDA = new Set<ClaveAjuste>(['primario'])

const apariencia = useAparienciaStore()
const session = useSessionStore()

// Mutable para SelectButton, que no recibe `readonly` (no las modifica).
const opciones = (clave: ClaveAjuste) => AJUSTES[clave].opciones as Opcion<Ajustes[ClaveAjuste]>[]
const elegida = (clave: ClaveAjuste, valor: unknown) => apariencia.ajustes[clave] === valor
const elegir = (clave: ClaveAjuste, valor: unknown) => apariencia.cambiar(clave, valor as Ajustes[ClaveAjuste])

const resumen = computed(() =>
  (['modo', 'primario', 'estilo'] as const).map((c) => etiquetaDe(c, apariencia.ajustes[c])).join(' · '),
)
</script>

<style scoped>
.vista-previa {
  box-shadow: var(--shadow-sm);
}
.mosaico {
  transition:
    background-color var(--transition-fast),
    border-color var(--transition-fast);
}
</style>
