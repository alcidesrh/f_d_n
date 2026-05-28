<template>
  <CrudForm
    :data="data"
    :store="store"
    :arg="$route.params.id ? { id: $route.params.id } : null"
  >
    <template #children="{ schema }">
      <FormKitSchema :schema="schema" :data="data" />
    </template>
    <template #parents="{ schema }">
      <FormKitSchema :schema="schema" :data="data" />
    </template>
  </CrudForm>
  <!-- {{ schema }} -->
  <!-- <FormKitSchema :schema="a" :data="data" /> -->
</template>
<script setup lang="ts">
const store = useRoleStore();
store.getItems();
const permisoStore = usePermisoStore();
permisoStore.getItems(true);

const { items, entity, schema } = storeToRefs(store);
const { items: permisos } = storeToRefs(permisoStore);

const parents = computed(() =>
  items.value.filter(
    (v) =>
      entity.value.item.id != v.id &&
      (entity.value.item?.children
        ? !entity.value.item?.children ||
          !entity.value.item?.children.includes(v.value)
        : true),
  ),
);

const children = computed(() =>
  permisos.value.filter(
    (v) =>
      entity.value.item.id != v.value &&
      (entity.value.item?.parents
        ? !entity.value.item?.parents ||
          !entity.value.item?.parents.includes(v.value)
        : true),
  ),
);
const a = [
  {
    $el: "div",
    children: [
      {
        name: "nombre",
        $formkit: "text",
        id: "nombre",
        label: "nombre",
        "sections-schema": {
          outer: {
            attrs: {
              class: "form-outer",
            },
          },
          wrapper: {
            attrs: {
              class: "form-wrapper",
            },
          },
          inner: {
            attrs: {
              class: "form-inner",
            },
          },
          label: {
            $el: "div",
            attrs: {
              class: "form-label",
            },
          },
        },
        validation: "required",
      },
      {
        name: "parents",
        $formkit: "select",
        id: "parents",
        optionLabel: "label",
        optionValue: "id",
        placeholder: "Seleccionar",
        multiple: true,
        label: "parents",
        "sections-schema": {
          outer: {
            attrs: {
              class: "form-outer",
            },
          },
          wrapper: {
            attrs: {
              class: "form-wrapper",
            },
          },
          inner: {
            attrs: {
              class: "form-inner",
            },
          },
          label: {
            $el: "div",
            attrs: {
              class: "form-label",
            },
          },
        },
        options: "$parents",
      },
    ],
  },
];
const data = ref({
  children,
  parents,
  permisos,
  item: computed(() => entity.value.item),
});
</script>
