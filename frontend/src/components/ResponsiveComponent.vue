<script setup>
import { computed, defineAsyncComponent } from "vue";
import { useBreakpoint } from "src/composables/useBreakpoints";

const props = defineProps({
  mobile: String | {},
  tablet: String | {},
  desktop: String | {},
});

function load(name) {
  return defineAsyncComponent(() => import(`./dynamic/${name}.vue`));
}
const conditional = (v) => {
  if (typeof v == "object") {
    const cmp = v.cmp;
    return { cmp: cmp, props: v.props };
  } else if (typeof v == "string") {
    return { cmp: v, props: {} };
  }
  return null;
};
const dynamicProps = ref();
const cmp = computed(() => {
  let temp;
  if (isMobile.value && props.mobile) {
    temp = conditional(props.mobile);
    return { cmp: load(temp.cmp), props: temp.props };
  }

  if (isTablet.value && props.tablet) {
    temp = conditional(props.tablet);
    return { cmp: load(temp.cmp), props: temp.props };
  }

  if (isDesktop.value || !temp) {
    temp = conditional(props.desktop);
    return { cmp: load(temp.cmp), props: temp.props };
  }
});
</script>

<template>
  <component :is="cmp.cmp" v-bind="cmp.props" />
</template>
