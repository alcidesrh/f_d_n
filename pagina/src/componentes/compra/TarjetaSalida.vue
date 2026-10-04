<!--
  Una salida de la lista: hora, llegada, empresa, bus, precios por clase,
  ocupación y precio "desde". Es el encabezado del acordeón: al tocarla se
  abre el croquis debajo (slot por defecto).
-->
<template>
  <article
    class="overflow-hidden rounded-2xl border bg-white shadow-tarjeta transition-colors"
    :class="abierta ? 'border-marca-700 ring-1 ring-marca-700' : elegida ? 'border-acento-500' : 'border-surface-200'"
  >
    <h3 class="m-0">
      <button
        :id="`${idBase}-cab`"
        type="button"
        class="grid w-full cursor-pointer grid-cols-[1fr_auto] gap-x-3 gap-y-2 border-0 bg-transparent p-4 text-left text-color disabled:cursor-not-allowed disabled:opacity-60 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center md:gap-x-6"
        :aria-expanded="abierta"
        :aria-controls="`${idBase}-panel`"
        :disabled="agotada"
        @click="emit('alternar')"
      >
        <!-- Horario -->
        <span class="col-start-1 row-start-1 flex min-w-0 flex-col gap-1">
          <span class="flex flex-wrap items-baseline gap-x-2">
            <span class="text-2xl font-bold tabular-nums tracking-tight">{{ hora(s.salida ?? s.salidaInicio, region) }}</span>
            <span v-if="s.llegada" class="text-sm text-muted-color">
              <icon name="arrow-right" size="0.85rem" class="align-[-1px]" /> {{ t('salidas.llega') }} {{ hora(s.llegada, region) }}
            </span>
          </span>
          <span class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-color">
            <span v-if="s.empresa" class="font-medium text-color">{{ s.empresa }}</span>
            <span v-if="tiempo" class="inline-flex items-center gap-1"><icon name="clock" size="0.95rem" />{{ tiempo }}</span>
            <span class="inline-flex items-center gap-1"><icon name="route" size="0.95rem" />{{ t('salidas.paradas', s.paradas) }}</span>
          </span>
        </span>

        <!-- Precio desde (móvil: arriba a la derecha) -->
        <span class="col-start-2 row-start-1 flex flex-col items-end md:col-start-3">
          <span class="text-xs text-muted-color">{{ t('salidas.desde') }}</span>
          <span class="whitespace-nowrap text-xl font-bold text-marca-900">{{ s.desde.texto }}</span>
          <span class="mt-1 hidden md:inline-flex"><span :class="claseBoton"><icon name="armchair" size="1rem" />{{ abierta ? t('salidas.ocultar') : t('salidas.elegir') }}<icon :name="abierta ? 'chevron-up' : 'chevron-down'" size="1rem" /></span></span>
        </span>

        <!-- Clases: precio, libres y ocupados -->
        <span class="col-span-2 flex flex-col gap-2 md:col-span-1 md:col-start-2 md:row-start-1 md:w-80">
          <span v-for="c in s.clases" :key="c.clase" class="flex items-center justify-between gap-2 rounded-lg px-2 py-1 text-xs" :class="c.clase === 'B' ? 'bg-amber-50 text-amber-900' : 'bg-marca-50 text-marca-900'">
            <span class="inline-flex items-center gap-1 whitespace-nowrap font-semibold">
              <icon v-if="c.clase === 'B'" name="crown" size="0.8rem" />{{ t('salidas.clase', { clase: c.clase }) }} · {{ c.precio.texto }}
            </span>
            <span class="whitespace-nowrap tabular-nums">{{ t('salidas.libresOcupados', { libres: Math.max(0, c.asientos - c.ocupados), ocupados: c.ocupados }) }}</span>
          </span>
          <span v-if="agotada || pocos" class="text-xs font-semibold" :class="agotada ? 'text-red-700' : 'text-acento-700'">
            {{ agotada ? t('salidas.agotado') : t('salidas.ultimos') }}
          </span>
        </span>

        <!-- Pie móvil -->
        <span class="col-span-2 flex items-center justify-between gap-2 text-sm md:hidden">
          <span v-if="elegida" class="inline-flex items-center gap-1 font-medium text-acento-700">
            <icon name="armchair" size="1rem" />{{ t('salidas.elegida') }} · {{ elegida }}
          </span>
          <span v-else class="text-xs text-muted-color">{{ t('salidas.cierre', { hora: hora(s.cierre, region) }) }}</span>
          <span :class="claseBoton"><icon name="armchair" size="1rem" />{{ abierta ? t('salidas.ocultar') : t('salidas.elegir') }}<icon :name="abierta ? 'chevron-up' : 'chevron-down'" size="1rem" /></span>
        </span>
      </button>
    </h3>

    <Transition :css="false" @enter="alEntrar" @leave="alSalir">
      <div v-if="abierta" class="overflow-hidden">
        <div :id="`${idBase}-panel`" role="region" :aria-labelledby="`${idBase}-cab`" class="border-t border-t-surface-200 bg-surface-50/60">
          <slot />
        </div>
      </div>
    </Transition>
  </article>
</template>

<script setup lang="ts">
import { gsap } from 'gsap'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { REGION, type Idioma } from '@/i18n'
import { duracion, hora, porcentajeOcupado } from '@/modelo'
import type { Salida } from '@/tipos'

const props = defineProps<{ s: Salida; abierta: boolean; /** Números de asiento elegidos aquí, p. ej. "3, 4". */ elegida?: string | null }>()
const emit = defineEmits<{ alternar: [] }>()
const { t, locale } = useI18n()

const region = computed(() => REGION[locale.value as Idioma])
const idBase = computed(() => `salida-${props.s.id}-${props.s.trayecto}`)
const ocupado = computed(() => porcentajeOcupado(props.s))
const agotada = computed(() => props.s.disponibles <= 0)
const claseBoton = 'inline-flex items-center gap-1.5 rounded-lg border border-marca-700 bg-marca-700 px-3 py-1.5 text-sm font-medium text-white'

// Acordeón suave: el panel crece desde 0 (altura y opacidad) y se pliega al cerrar.
function alEntrar(el: Element, hecho: () => void) {
  gsap.fromTo(el, { height: 0, opacity: 0 }, { height: 'auto', opacity: 1, duration: 0.5, ease: 'power2.out', onComplete: () => { gsap.set(el, { clearProps: 'height,opacity' }); hecho() } })
}
function alSalir(el: Element, hecho: () => void) {
  gsap.to(el, { height: 0, opacity: 0, duration: 0.35, ease: 'power2.inOut', onComplete: hecho })
}

const pocos = computed(() => props.s.disponibles > 0 && props.s.disponibles <= 5)
const tiempo = computed(() => duracion(props.s.salida ?? props.s.salidaInicio, props.s.llegada))
</script>
