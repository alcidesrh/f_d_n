<template>
  <q-form class="q-pa-md q-col-gutter-y-md" @submit="emitSubmit">
    <div class="row q-gutter-md">
      <FormRepeater
        :values="item.roles"
        :label="$t('permiso.roles')"
        class="col-12 col-md-8"
        @update="(values: any[]) => (item.roles = values)"
      />
      <q-input
        v-model="item.parents"
        :label="$t('permiso.parents')"
        :error="Boolean(violations?.parents)"
        :error-message="violations?.parents"
        name="parents"
        type="text"
        bottom-slots
        filled
        class="col-12 col-md-4"
      >
        <template #append>
          <q-icon
            name="close"
            class="cursor-pointer"
            @click.prevent.stop="item.parents = undefined"
          />
        </template>
      </q-input>
      <q-input
        v-model="item.children"
        :label="$t('permiso.children')"
        :error="Boolean(violations?.children)"
        :error-message="violations?.children"
        name="children"
        type="text"
        bottom-slots
        filled
        class="col-12 col-md-4"
      >
        <template #append>
          <q-icon
            name="close"
            class="cursor-pointer"
            @click.prevent.stop="item.children = undefined"
          />
        </template>
      </q-input>
      <q-input
        v-model="item.nombre"
        :label="$t('permiso.nombre')"
        :error="Boolean(violations?.nombre)"
        :error-message="violations?.nombre"
        name="nombre"
        type="text"
        bottom-slots
        filled
        class="col-12 col-md-4"
      >
        <template #append>
          <q-icon
            name="close"
            class="cursor-pointer"
            @click.prevent.stop="item.nombre = undefined"
          />
        </template>
      </q-input>
      <q-input
        v-model="item.nota"
        :label="$t('permiso.nota')"
        :error="Boolean(violations?.nota)"
        :error-message="violations?.nota"
        name="nota"
        type="text"
        bottom-slots
        filled
        class="col-12 col-md-4"
      >
        <template #append>
          <q-icon
            name="close"
            class="cursor-pointer"
            @click.prevent.stop="item.nota = undefined"
          />
        </template>
      </q-input>
      <q-input
        v-model="item.label"
        :label="$t('permiso.label')"
        :error="Boolean(violations?.label)"
        :error-message="violations?.label"
        name="label"
        type="text"
        bottom-slots
        filled
        class="col-12 col-md-4"
      >
        <template #append>
          <q-icon
            name="close"
            class="cursor-pointer"
            @click.prevent.stop="item.label = undefined"
          />
        </template>
      </q-input>
      <q-input
        v-model="item.status"
        :label="$t('permiso.status')"
        :error="Boolean(violations?.status)"
        :error-message="violations?.status"
        name="status"
        type="text"
        bottom-slots
        filled
        class="col-12 col-md-4"
      >
        <template #append>
          <q-icon
            name="close"
            class="cursor-pointer"
            @click.prevent.stop="item.status = undefined"
          />
        </template>
      </q-input>
      <q-input
        v-model="item.id"
        :label="$t('permiso.id')"
        :error="Boolean(violations?.id)"
        :error-message="violations?.id"
        name="id"
        type="text"
        bottom-slots
        filled
        class="col-12 col-md-4"
      >
        <template #append>
          <q-icon
            name="close"
            class="cursor-pointer"
            @click.prevent.stop="item.id = undefined"
          />
        </template>
      </q-input>
    </div>

    <div>
      <q-btn label="Submit" type="submit" color="primary" />
      <q-btn
        label="Reset"
        type="reset"
        color="primary"
        flat
        class="q-ml-sm"
        @click="resetForm"
      />
    </div>
  </q-form>
</template>

<script lang="ts" setup>
import { Ref, ref, toRef } from 'vue';
import FormRepeater from 'components/common/CommonFormRepeater.vue';
import type { Item } from 'src/types/item';
import type { Permiso } from 'src/types/permiso';
import type { SubmissionErrors } from 'src/types/error';

let props = defineProps<{
  values?: Permiso;
  errors?: SubmissionErrors;
}>();

const violations = toRef(props, 'errors');

let item: Ref<Permiso> = ref({});

if (props.values) {
  item.value = {
    ...props.values,
  };
}

function resetForm() {
  item.value = { ...props.values };
}

let emit = defineEmits<{
  (e: 'submit', item: Permiso): void;
}>();

function emitSubmit() {
  emit('submit', item.value);
}
</script>
