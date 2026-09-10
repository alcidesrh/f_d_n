<template>
  <Sidebar side="right">
    <template #menu-content>
      <ul class="sidebar-menu">
        <li class="menu-item">
          <a
            href="#"
            class="menu-link"
            @mouseenter="handleMouseEnter"
            @mouseleave="handleMouseLeave"
          >
            <span>
              <icon name="settings" size="1.5rem" />
            </span>
            <span class="menu-text">Inicio</span>
          </a>
        </li>
        <li class="menu-item">
          <a
            href="#"
            class="menu-link"
            @mouseenter="handleMouseEnter"
            @mouseleave="handleMouseLeave"
          >
            <span>
              <icon name="settings" size="1.5rem" />
            </span>
            <span class="menu-text">Inicio</span>
          </a>
        </li>
        <li class="menu-item">
          <a
            href="#"
            class="menu-link"
            @mouseenter="handleMouseEnter"
            @mouseleave="handleMouseLeave"
          >
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
import { useMenusStore, type MenuItem } from "@/stores/menus";
import { gsap } from "gsap";
import { CustomBounce } from "gsap/CustomBounce";
import { CustomEase } from "gsap/CustomEase";
const props = defineProps<{ side: "left" | "right" }>();

const menusStore = useMenusStore();
const dynamicMenus = computed(() => menusStore.sidebarLeftItems);

interface MenuGroup {
  label: string;
  items: MenuItem[];
}

const groupedMenus = computed<MenuGroup[]>(() => {
  const items = dynamicMenus.value;
  if (items.length === 0) return [];
  return [{ label: "Navegación", items }];
});

const sidebarStore = defineSidebarStore(props.side)();

watch(
  () => sidebarStore.mode,
  () => sidebarUpdate(),
);
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

// Eventos Hover para el desbordamiento fluido en estado "mini"
const handleMouseEnter = (e) => {
  const rootStyles = window.getComputedStyle(document.documentElement);
  const shadow = rootStyles.getPropertyValue("--p-surface-300");

  if (sidebarStore.mode === "mini") {
    const temp = {
      borderRadius: "0 8px 8px 0",
      duration: 0.25,
      ease: "power1.out",
      zIndex: 999,
      width: "0px",
    };
    if (sidebarStore.side == "left") {
      gsap.fromTo(
        e.target,
        { ...temp },
        { duration: 0.4, width: 200, boxShadow: `1px 0px 3px ${shadow}` },
      );
    } else {
      temp.flexDirection = "row-reverse";
      gsap.fromTo(
        e.target,
        {
          ...temp,
          borderRadius: "8 0px 0px 8",
          display: "flex",
          width: 200,
          flexDirection: "row-reverse",
          x: -130,
          justifyContent: "end",
        },
        {
          duration: 0.4,

          // x: -130,
          boxShadow: `-1px 0px 3px ${shadow}`,
        },
      );
    }

    gsap.fromTo(
      e.currentTarget.querySelector(".menu-text"),
      { opacity: 1, width: "0px", overflow: "hidden" },
      { width: "100%", duration: 0.4 },
    );
  } else if (sidebarStore.mode === "open") {
  }
};

const handleMouseLeave = (e) => {
  if (sidebarStore.mode === "mini") {
    const to = {
      border: "none",
      borderRadius: "none",
      ease: "power1.out",
      zIndex: 999,
      width: "auto",
      duration: 0.4,
      boxShadow: "none",
    };
    if (sidebarStore.side == "left") {
      gsap.to(e.target, to);
    } else {
      to.x = 0;
      gsap.to(e.target, to);
    }
  } else if (sidebarStore.mode === "open") {
    // gsap.to(link, { backgroundColor: bg });
  }
};
onMounted(() => {
  sidebarUpdate();
});
</script>
