<template>
  <Sidebar side="left">
    <template #menu-content>
      <ul class="sidebar-menu">
        <li class="menu-item">
          <a href="#" class="menu-link" @mouseenter="sidebarStore.handleMouseEnter" @mouseleave="sidebarStore.handleMouseLeave">
            <span>
              <icon name="settings" size="1.5rem" />
            </span>
            <span class="menu-text">Inicio</span>
          </a>
        </li>
        <li class="menu-item">
          <a href="#" class="menu-link" @mouseenter="sidebarStore.handleMouseEnter" @mouseleave="sidebarStore.handleMouseLeave">
            <span>
              <icon name="settings" size="1.5rem" />
            </span>
            <span class="menu-text">Inicio</span>
          </a>
        </li>
        <li class="menu-item">
          <a href="#" class="menu-link" @mouseenter="sidebarStore.handleMouseEnter" @mouseleave="sidebarStore.handleMouseLeave">
            <!-- <span> -->
            <icon name="settings" size="1.5rem" />
            <!-- </span> -->
            <span class="menu-text">Configuración</span>
          </a>
        </li>
      </ul>
    </template>
  </Sidebar>
</template>
<script setup lang="ts">
import Sidebar from "./Sidebar.vue";
import { defineSidebarStore } from "./sidebarStore";

const sidebarStore = defineSidebarStore("left")();
</script>
