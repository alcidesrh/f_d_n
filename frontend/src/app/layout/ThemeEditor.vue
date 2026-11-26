<!--
  Apariencia de la app en este navegador: modo, colores y estilo de los
  componentes (`app/ui.ts`). Es la sección "Apariencia" de Mi cuenta, que lo
  recibe del shell (`EDITOR_DE_APARIENCIA`).
-->
<template>
  <div class="@container flex flex-col gap-5" aria-label="Personalizar apariencia">
    <!-- Una opción por fila: el texto arriba en el teléfono, a la izquierda desde @2xl. -->
    <!-- Vista previa con los componentes reales: refleja cada cambio al instante. -->
    <div class="flex flex-col gap-3 rounded-xl border border-surface-200 bg-surface-0 p-3 @xl:flex-row @xl:items-center @xl:justify-between">
      <div class="flex min-w-0 items-center gap-2.5">
        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary-100 text-primary-700">
          <icon name="palette-outline" size="1.1rem" />
        </span>
        <div class="min-w-0">
          <div class="truncate text-sm font-semibold text-surface-900">Vista previa</div>
          <div class="truncate text-xs text-surface-500">{{ resumen }}</div>
        </div>
      </div>
      <div class="flex gap-2">
        <Button size="small" label="Acción" />
        <Button size="small" label="Secundaria" severity="secondary" outlined />
      </div>
    </div>

    <div class="flex flex-col gap-3 border-t border-surface-200 pt-4 @2xl:flex-row @2xl:items-center @2xl:justify-between @2xl:gap-6">
      <div class="flex min-w-0 flex-col gap-0.5 @2xl:max-w-72">
        <span class="font-medium text-surface-900">Modo</span>
        <span class="text-sm text-surface-500">Al entrar por primera vez sigue el del sistema operativo.</span>
      </div>
      <SelectButton
        :model-value="ui.mode"
        :options="MODOS"
        option-label="label"
        option-value="value"
        :allow-empty="false"
        class="w-full @2xl:w-auto"
        fluid
        @update:model-value="ui.setMode($event)"
      >
        <template #option="{ option }">
          <span class="flex items-center gap-2">
            <icon :name="option.icon" size="1.1rem" />
            {{ option.label }}
          </span>
        </template>
      </SelectButton>
    </div>

    <div class="flex flex-col gap-3 border-t border-surface-200 pt-4 @2xl:flex-row @2xl:items-center @2xl:justify-between @2xl:gap-6">
      <div class="flex min-w-0 flex-col gap-0.5 @2xl:max-w-72">
        <span class="font-medium text-surface-900">Color primario</span>
        <span class="text-sm text-surface-500">{{ primarioLabel }}: botones, enlaces y lo seleccionado.</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="p in PRIMARY_OPTIONS"
          :key="p.key"
          type="button"
          class="tap-target grid size-9 cursor-pointer place-items-center rounded-full transition-transform hover:scale-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
          :class="{ 'ring-2 ring-primary-500 ring-offset-2 ring-offset-surface-0': ui.primary === p.key }"
          :aria-label="p.label"
          :aria-pressed="ui.primary === p.key"
          :title="p.label"
          @click="ui.setPrimary(p.key)"
        >
          <span class="size-7 rounded-full" :style="{ background: p.swatch }"></span>
        </button>
      </div>
    </div>

    <div class="flex flex-col gap-3 border-t border-surface-200 pt-4 @2xl:flex-row @2xl:items-center @2xl:justify-between @2xl:gap-6">
      <div class="flex min-w-0 flex-col gap-0.5 @2xl:max-w-72">
        <span class="font-medium text-surface-900">Superficie</span>
        <span class="text-sm text-surface-500">{{ superficieLabel }}: el tono de fondos, bordes y textos.</span>
      </div>
      <div class="grid grid-cols-3 gap-2 @md:grid-cols-5">
        <button
          v-for="s in SURFACE_OPTIONS"
          :key="s.key"
          type="button"
          class="flex min-w-16 cursor-pointer flex-col items-center gap-1.5 rounded-lg border p-2 transition-colors"
          :class="ui.surface === s.key ? 'border-primary-500 bg-primary-50' : 'border-surface-200 hover:bg-surface-100'"
          :aria-pressed="ui.surface === s.key"
          :title="s.label"
          @click="ui.setSurface(s.key)"
        >
          <span class="relative block h-8 w-full rounded-md" :style="{ background: s.bg }">
            <span class="absolute right-1 bottom-1 size-2.5 rounded-full" :style="{ background: s.accent }"></span>
          </span>
          <span class="text-xs font-medium text-surface-700">{{ s.label }}</span>
        </button>
      </div>
    </div>

    <div class="flex flex-col gap-3 border-t border-surface-200 pt-4 @2xl:flex-row @2xl:items-center @2xl:justify-between @2xl:gap-6">
      <div class="flex min-w-0 flex-col gap-0.5 @2xl:max-w-72">
        <span class="font-medium text-surface-900">Estilo de componentes</span>
        <span class="text-sm text-surface-500">Bordes, esquinas y espaciado de botones y campos.</span>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <button
          v-for="v in PRESET_OPTIONS"
          :key="v.key"
          type="button"
          class="flex min-w-32 cursor-pointer items-center justify-between rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
          :class="ui.preset === v.key ? 'border-primary-500 bg-primary-50 text-primary-800' : 'border-surface-200 text-surface-700 hover:bg-surface-100'"
          :aria-pressed="ui.preset === v.key"
          @click="ui.setPreset(v.key)"
        >
          {{ v.label }}
          <icon v-if="ui.preset === v.key" name="check" size="1rem" />
        </button>
      </div>
    </div>

    <div class="flex justify-end">
      <Button text size="small" severity="secondary" @click="ui.restablecerTema()">
        <template #icon>
          <icon name="restart-alt" size="1rem" />
        </template>
        Restablecer
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { PRESET_OPTIONS, PRIMARY_OPTIONS, SURFACE_OPTIONS } from '@/app/theme'
import { useUiStore } from '@/app/ui'

const ui = useUiStore()

const MODOS = [
  { value: 'light', label: 'Claro', icon: 'light-mode-outline' },
  { value: 'dark', label: 'Oscuro', icon: 'dark-mode-outline' },
]

const primarioLabel = computed(() => PRIMARY_OPTIONS.find((p) => p.key === ui.primary)?.label)
const superficieLabel = computed(() => SURFACE_OPTIONS.find((s) => s.key === ui.surface)?.label)
const resumen = computed(() => {
  const modo = MODOS.find((m) => m.value === ui.mode)?.label
  const preset = PRESET_OPTIONS.find((v) => v.key === ui.preset)?.label
  return `${modo} · ${primarioLabel.value} · ${preset}`
})
</script>

