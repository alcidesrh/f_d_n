<template>
  <header class="app-header">
    <div class="flex btn-siderbar-header" :class="[sidebarStore.mode]">
      <button class="icon-btn" @click="sidebarStore.setMode()">
        <icon name="menu-2" />
      </button>
      <Divider layout="vertical" class="mx-[5px]!" />
    </div>
    <div class="brand">
      <!-- <div style="min-width: 0; position: relative"> -->
      <div class="header-clock" v-html="currentTime"></div>
      <div class="brand-name">FDN</div>
      <!-- </div> -->
    </div>

    <div class="header-crumbs">
      <nav class="crumbs" aria-label="Breadcrumb">
        <template
          v-for="(crumb, i) in navigationHistory.entries"
          :key="crumb.path + i"
        >
          <icon v-if="i > 0" name="chevron-right" size=".8rem"></icon>
          <router-link
            :to="
              crumb.name
                ? { name: crumb.name, params: crumb.params }
                : crumb.path
            "
            class="crumb-link"
            :class="{
              'crumb-current': i === navigationHistory.entries.length - 1,
            }"
          >
            {{ crumb.label }}
          </router-link>
        </template>
      </nav>
    </div>

    <div class="header-actions">
      <template v-if="topbarMenuItems.length > 0">
        <router-link
          v-for="item in topbarMenuItems"
          :key="item.id"
          :to="item.ruta ?? '#'"
          class="icon-btn"
          :title="item.label"
        >
          <icon :name="item.icon ?? 'circle'" />
        </router-link>
      </template>
      <slot name="menu-content"></slot>

      <button
        class="icon-btn cursor-pointer"
        title="Personalizar apariencia"
        @click.stop="showThemeEditor()"
      >
        <icon name="palette" />
      </button>

      <button
        class="icon-btn"
        title="Pantalla completa"
        @click="toggleFullscreen"
        :class="{ 'active-state': openPopover === 'fullscreen' }"
      >
        <icon name="arrows-maximize" />
      </button>
      <div style="position: relative">
        <button
          class="icon-btn"
          title="Notificaciones"
          @click.stop="toggle('notif')"
        >
          <icon name="bell" />
        </button>
      </div>

      <div style="position: relative">
        <Chip :label="useUserSessionStore().user" removable>
          <template #removeicon>
            <!-- <button class="icon-btn" @click.stop="logout"> -->
            <icon name="logout" @click.stop="logout" />
            <!-- </button -->
          </template>
        </Chip>
      </div>
    </div>
    <div class="flex btn-siderbar-header" :class="[sidebarStoreR.mode]">
      <Divider layout="vertical" class="mx-[5px]!" />
      <button
        class="icon-btn right"
        title="Mostrar/ocultar menú"
        @click="sidebarStoreR.setMode()"
      >
        <icon name="menu-2" />
      </button>
    </div>
  </header>
</template>
<script setup lang="ts">
import { computed } from "vue";
import { useMenusStore } from "@/stores/menus";
import { useNavigationHistoryStore } from "@/stores/navigationHistory";
import { useDialog } from "primevue/usedialog";
import ThemeEditor from "@/components/common/ThemeEditor.vue";
import type { SidebarStoreState } from "@/stores/entities/types";

const props = defineProps<{
  sidebarStoreR?: SidebarStoreState;
}>();

const dialog = useDialog();

const showThemeEditor = () =>
  dialog.open(ThemeEditor, { props: { header: "Edit Profile" } });

const sidebarStore = defineSidebarStore("left")();
const sidebarStoreR = props.sidebarStoreR
  ? props.sidebarStoreR
  : defineSidebarStore("right")();
type PopoverName = "notif" | "customizer" | "user" | "fullscreen" | null;
const openPopover = ref<PopoverName>(null);

const menusStore = useMenusStore();
const topbarMenuItems = computed(() => menusStore.topbarRightItems);
const navigationHistory = useNavigationHistoryStore();

function formatClock(date: Date) {
  const pad = (n: number) => String(n).padStart(2, "0");
  const hours12 = date.getHours() % 12 || 12;
  const seconds10 = Math.floor(date.getSeconds() / 10) * 10;
  const ampm = date.getHours() >= 12 ? "pm" : "am";
  return `${pad(hours12)}:${pad(date.getMinutes())} <span class="text-[.8rem] font-bold">${pad(seconds10)}</span> ${ampm}`;
}
const currentTime = ref(formatClock(new Date()));
let clockTimer: ReturnType<typeof setInterval>;
onMounted(() => {
  clockTimer = setInterval(() => {
    currentTime.value = formatClock(new Date());
  }, 1000);
});
onUnmounted(() => clearInterval(clockTimer));

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen?.();
  } else {
    document.exitFullscreen?.();
  }
  toggle("fullscreen");
}
function toggle(name: Exclude<PopoverName, null>) {
  openPopover.value = openPopover.value === name ? null : name;
}

async function logout() {
  apiRest.post("/logout").then(async (resp) => {
    useUserSessionStore().clear();
    // const router = useRouter();
    router.push({ path: "/login" });
  });
}
</script>
