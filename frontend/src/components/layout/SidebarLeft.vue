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
const sidebarStore = defineSidebarStore("left")();

// // Eventos Hover para el desbordamiento fluido en estado "mini"
// const handleMouseEnter = (e) => {
//   const rootStyles = window.getComputedStyle(document.documentElement);
//   const shadow = rootStyles.getPropertyValue("--p-surface-300");

//   if (sidebarStore.mode === "mini") {
//     const temp = {
//       borderRadius: "0 8px 8px 0",
//       duration: 0.25,
//       ease: "power1.out",
//       zIndex: 999,
//       width: "0px",
//     };
//     if (sidebarStore.side == "left") {
//       gsap.fromTo(
//         e.target,
//         { ...temp },
//         { duration: 0.4, width: 200, boxShadow: `1px 0px 3px ${shadow}` },
//       );
//     } else {
//       temp.flexDirection = "row-reverse";
//       gsap.fromTo(
//         e.target,
//         {
//           ...temp,
//           borderRadius: "8 0px 0px 8",
//           display: "flex",
//           width: 200,
//           flexDirection: "row-reverse",
//           x: -130,
//           justifyContent: "end",
//         },
//         {
//           duration: 0.4,

//           // x: -130,
//           boxShadow: `-1px 0px 3px ${shadow}`,
//         },
//       );
//     }

//     gsap.fromTo(
//       e.currentTarget.querySelector(".menu-text"),
//       { opacity: 1, width: "0px", overflow: "hidden" },
//       { width: "100%", duration: 0.4 },
//     );
//   } else if (sidebarStore.mode === "open") {
//   }
// };

// const handleMouseLeave = (e) => {
//   if (sidebarStore.mode === "mini") {
//     const to = {
//       border: "none",
//       borderRadius: "none",
//       ease: "power1.out",
//       zIndex: 999,
//       width: "auto",
//       duration: 0.4,
//       boxShadow: "none",
//     };
//     if (sidebarStore.side == "left") {
//       gsap.to(e.target, to);
//     } else {
//       to.x = 0;
//       gsap.to(e.target, to);
//     }
//   } else if (sidebarStore.mode === "open") {
//     // gsap.to(link, { backgroundColor: bg });
//   }
// };
</script>
