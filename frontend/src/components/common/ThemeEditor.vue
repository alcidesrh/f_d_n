<template>
    <div class="pop customizer" style="right: 0">
        <div class="cz-label">Color primario</div>

        <div class="swatch-grid">
          <button
            v-for="p in PRIMARY_OPTIONS"
            :key="p.key"
            class="swatch cursor-pointer"
            :class="{ selected: ui.primary === p.key }"
            :title="p.label"
            @click="ui.setPrimary(p.key)"
          >
            <span
              :style="{
                background: p.swatch,
              }"
            ></span>
          </button>
        </div>
        <Divider />

        <div class="cz-label">Paleta de superficie</div>

        <div class="surface-row">

          <button
            v-for="s in SURFACE_OPTIONS"
            :key="s.key"
            class="surf-swatch cursor-pointer"
            :class="{ selected: ui.surface === s.key }"
            :title="s.label"
            @click="ui.setSurface(s.key)"
          >
            <i :style="{ background: s.accent }"></i>
          </button>
        </div>
        <Divider />
        <div class="cz-label">Modo</div>
        <div class="mode-toggle">
          <button
            class="cursor-pointer"
            :class="{ active: ui.mode === 'light' }"
            @click="ui.setMode('light')"
          >
            <AppIcon name="sun" :size="14" /> Claro
          </button>
          <button
            class="cursor-pointer"
            :class="{ active: ui.mode === 'dark' }"
            @click="ui.setMode('dark')"
          >
            <AppIcon name="moon" :size="14" /> Oscuro
          </button>
        </div>
        <Divider />

        <div class="cz-label">Preset</div>
        <div class="mode-toggle">
          <button
            v-for="v in PRESET_OPTIONS"
            :key="v.key"
            :class="{ active: ui.preset === v.key }"
            class="cursor-pointer"
            @click="ui.setPreset(v.key)"
          >
            <!-- <AppIcon name="sun" :size="14" /> Claro -->
            {{ v.label }}
          </button>
        </div>
      </div>
</template>
<script setup lang="ts">
import { PRESET_OPTIONS, SURFACE_OPTIONS, PRIMARY_OPTIONS } from "@/config/theme";

// const ui = useUiStore();

</script>
