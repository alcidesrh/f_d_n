<template>
  <aside class="sidebar" :class="[sidebarStore.side, sidebarStore.mode]">
    <nav>
      <div :class="[sidebarStore.side]" class="sidebar-control">
        <div @click="sidebarStore.setMode('close')">
          <icon name="x" />
        </div>
        <div @click="sidebarStore.setMode(sidebarStore.mode == 'mini' ? 'open' : 'mini')">
          <icon :name="sidebarStore.mode != 'mini' ? 'chevrons-left' : 'chevrons-right'" />
        </div>
      </div>
      <slot name="menu-content"> </slot>
    </nav>
  </aside>
</template>
<script setup lang="ts">
import { gsap } from "gsap";
import { CustomBounce } from "gsap/CustomBounce";
import { CustomEase } from "gsap/CustomEase";
import { Flip } from "gsap/Flip";
gsap.registerPlugin(Flip);
const props = defineProps<{ side: "left" | "right" }>();

const sidebarStore = defineSidebarStore(props.side)();

// watch(
//   () => sidebarStore.mode,
//   () => sidebarUpdate(),
// );
gsap.registerPlugin(CustomBounce, CustomEase);

function sidebarUpdate() {
  const targets = {
    sidebar: `.sidebar.${sidebarStore.side}`,
    main: `.main`,
    menu: `.sidebar.${sidebarStore.side} .menu-text`,
  };
  const duration = 0.3;
  // const ease = "power2.inOut";
  const ease = "expoScale(0.5,7, none)";

  if (sidebarStore.mode === "open") {
    gsap.to(targets.sidebar, { width: sidebarStore.width, duration, ease });
    if (sidebarStore.side == "left") {
      gsap.to(targets.main, { marginLeft: sidebarStore.width, duration, ease: ease });
    } else {
      gsap.to(targets.main, { marginRight: sidebarStore.width, duration, ease });
    }
    gsap.to(targets.menu, { opacity: 1, duration: duration * 0.8, ease });
  } else if (sidebarStore.mode === "mini") {
    gsap.to(targets.sidebar, { width: sidebarStore.width, overflow: "visible", duration, ease });

    if (sidebarStore.side == "left") {
      gsap.to(targets.main, { marginLeft: sidebarStore.width, duration, ease });
    } else {
      gsap.to(targets.main, { marginRight: sidebarStore.width, duration, ease });
    }
    gsap.to(targets.menu, { opacity: 0, duration: duration * 0.5, ease });
  } else if (sidebarStore.mode === "close") {
    gsap.to(targets.sidebar, {
      width: sidebarStore.width,
      opacity: 1,
      overflow: "hidden",
      duration,
      ease,
    });
    if (sidebarStore.side == "left") {
      gsap.to(targets.main, { marginLeft: 0, duration, ease: ease });
    } else {
      gsap.to(targets.main, { marginRight: 0, duration, ease });
    }
    gsap.to(targets.menu, { opacity: 0, duration: duration * 0.5, ease });
  }
}
onMounted(() => {
  // sidebarUpdate();
});
</script>
