<template>
  <q-breadcrumbs gutter="none">
    <template v-slot:separator>
      <q-icon size="1.5em" name="sym_o_chevron_right" color="primary" />
    </template>
    <q-breadcrumbs-el
      v-for="(item, index) in breadcrumbs"
      :key="index"
      :label="item.meta.breadcrumb.label"
      :to="item.meta?.breadcrumb?.to || ''"
      :icon="
        item.meta.breadcrumb.icon ? `sym_o_${item.meta.breadcrumb.icon}` : ''
      "
    >
      <template #default></template>
    </q-breadcrumbs-el>
  </q-breadcrumbs>
</template>
<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();
const breadcrumbs = computed(() => {
  const matched = route.matched;
  return matched
    .map((r, index) => {
      if (!r.meta?.breadcrumb) {
        return false;
      }
      const label =
        typeof r.meta?.breadcrumb.label === "function"
          ? r.meta?.breadcrumb.label(route)
          : r.meta?.breadcrumb.label || r.name;
      r.meta.breadcrumb.label = label;
      r.meta.breadcrumb.to = index < matched.length - 1 ? r.path : undefined;

      return r;
    })
    .filter((i) => i);
});
</script>
<style lang="scss" scoped>
.q-breadcrumbs {
  @apply text-surface-8 font-300;
  & a {
    // color: $accent;
  }
  & :deep(i) {
    font-size: 20px !important;
  }
}
</style>
