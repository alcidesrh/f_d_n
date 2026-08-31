<template>
  <div class="page-head">
    <div class="page-title" v-html="computedTitle"></div>
    <!-- <p v-if="subtitle" class="page-sub">{{ subtitle }}</p> -->
    <div class="page-actions">
      <!-- <slot /> -->
    </div>
  </div>
</template>
<script setup lang="ts">
import router from "@/router";

const props = withDefaults(defineProps<{ title: string; subtitle?: string }>(), { subtitle: "" });

const computedTitle = computed(() => {
  const route = router.currentRoute.value;

  return `${route.meta?.title || ""} ${router.currentRoute.value.params?.entity || ""}${props.title || ""}`;

  // let t = "";
  // if (route.meta.title) {
  //   t = route.meta.title;
  // }
  // if (router.currentRoute.value.params?.entity) {
  //   t += " " + router.currentRoute.value.params?.entity;
  // }
  // if(props.title){
  //   t = `${t} ${props.title}`
  // }
  // return t;
});
</script>
