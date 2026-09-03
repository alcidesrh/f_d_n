<template>
  <TopLoadingBar />

  <transition name="fade" mode="out-in">
    <AppLayout :key="layoutKey">
      <router-view v-slot="{ Component, route: r }">
        <transition name="fade" mode="out-in">
          <component :is="Component" :key="r.fullPath" />
        </transition>
      </router-view>
    </AppLayout>
  </transition>
  <ConfirmDialog></ConfirmDialog>
  <DynamicDialog />
  <Toast />
  <div class="backdrop" :class="{ show: showBackdrop }" @click="ui.closeMobileOverlays()"></div>

  <div>
    <!-- forked from the amazing Blake bowen for posterity 💚 -->
    <!-- https://codepen.io/osublake/pen/GYzqjL -->
    <div class="wrapper">
      <ul class="toolbar">
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/2D-star.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/2D-wobble.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/2D-windmill.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/2D-sparkle.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/2D-lightning.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/2D-flower.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/2D-diamond.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/2D-keyframe.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/3D-combo.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/2D-label.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/3D-cone.png" alt="" />
          </a>
        </li>
        <li class="toolbarItem">
          <a class="toolbarLink" href="#!">
            <img class="toolbarImg" src="https://assets.codepen.io/16327/3D-hoop.png" alt="" />
          </a>
        </li>
      </ul>
    </div>
    saddsfds
  </div>
</template>
<script setup lang="ts">
import AppLayout from "@/components/layout/AppLayout.vue";

const route = useRoute();

const layoutKey = computed(() => (route.meta.layout as string) || "default");

const showBackdrop = computed(() => ui.isMobile && (ui.mobileLeftOpen || ui.mobileRightOpen));
</script>
