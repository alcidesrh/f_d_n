<template>
  <aside :class="sidebarClasses" :style="sidebarStyle">
    <div class="sidebar-control">
      <div class="state-switch" role="radiogroup" aria-label="Estado del panel izquierdo">
        <button
          title="Cerrar"
          @click="ui.setLeft('close')"
        >
            <i class="pi pi-times"></i>
        </button>
        <button v-if="ui.leftState != 'mini'"
          :class="{ 'active bg-highlight': ui.leftState === 'mini' }"
          title="Minimizado"
          @click="ui.setLeft('mini')"
        >
            <i class="pi pi-chevron-left"></i>
        </button>
        <button v-if="ui.leftState != 'open'"
          :class="{ 'active bg-highlight': ui.leftState === 'open' }"
          title="Expandido"
          @click="ui.setLeft('open')"
        >
            <i class="pi pi-chevron-right"></i>
        </button>
      </div>
    </div>
    <div class="sidebar-scroll">
      <div class="nav-group-label">Operación</div>
      <nav class="nav">
        <div class="nav-rail" aria-hidden="true"></div>
        <router-link
          v-for="item in NAV_MAIN"
          :key="item.to"
          :to="item.to"
          class="nav-item"
          :data-tip="item.label"
        >
          <span class="stop-dot" aria-hidden="true"></span>
          <span class="nav-ic"><AppIcon :name="item.icon" :size="18" /></span>
          <span class="nav-label">{{ item.label }}</span>
          <span v-if="item.count" class="nav-count">{{ item.count }}</span>
        </router-link>
      </nav>

      <div class="nav-group-label">Gestión</div>
      <nav class="nav">
        <div class="nav-rail" aria-hidden="true"></div>
        <router-link
          v-for="item in NAV_OPS"
          :key="item.to"
          :to="item.to"
          class="nav-item"
          :data-tip="item.label"
        >
          <span class="stop-dot" aria-hidden="true"></span>
          <span class="nav-ic"><AppIcon :name="item.icon" :size="18" /></span>
          <span class="nav-label">{{ item.label }}</span>
          <span v-if="item.count" class="nav-count">{{ item.count }}</span>
        </router-link>
      </nav>
    </div>
  </aside>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useUiStore } from '@/stores/ui'
import { NAV_MAIN, NAV_OPS } from '@/config/nav'
import AppIcon from '@/components/icons/AppIcon.vue'

// const ui = useUiStore();

const sidebarClasses = computed(() => [
  'sidebar',
  'bg-surface-50',
  'left-side',
  { mini: ui.leftState === 'mini' },
  { closed: ui.leftState === 'close' && !ui.isMobile },
  { 'mobile-hidden': ui.isMobile && !ui.mobileLeftOpen },
])

const sidebarStyle = computed(() => (!ui.isMobile ? { width: ui.leftWidth } : {}))
</script>
