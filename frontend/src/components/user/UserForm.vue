<template>
  <q-form class="q-pa-md q-col-gutter-y-md" @submit="emitSubmit">
    <div class="row q-gutter-md">
      <q-input v-model="item.username" :label="$t('user.username')" :error="Boolean(violations?.username)"
        :error-message="violations?.username" name="username" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.username = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.password" :label="$t('user.password')" :error="Boolean(violations?.password)"
        :error-message="violations?.password" name="password" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.password = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.plainPassword" :label="$t('user.plainPassword')"
        :error="Boolean(violations?.plainPassword)" :error-message="violations?.plainPassword" name="plainPassword"
        type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.plainPassword = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.apiTokens" :label="$t('user.apiTokens')" :error="Boolean(violations?.apiTokens)"
        :error-message="violations?.apiTokens" name="apiTokens" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.apiTokens = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.userRoles" :label="$t('user.userRoles')" :error="Boolean(violations?.userRoles)"
        :error-message="violations?.userRoles" name="userRoles" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.userRoles = undefined" />
        </template>
      </q-input>
      <FormRepeater :values="item.permisos" :label="$t('user.permisos')" class="col-12 col-md-8"
        @update="(values: any[]) => (item.permisos = values)" />
      <q-input v-model="item.label" :label="$t('user.label')" :error="Boolean(violations?.label)"
        :error-message="violations?.label" name="label" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.label = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.createdAt" :label="$t('user.createdAt')" :error="Boolean(violations?.createdAt)"
        :error-message="violations?.createdAt" name="createdAt" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.createdAt = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.updatedAt" :label="$t('user.updatedAt')" :error="Boolean(violations?.updatedAt)"
        :error-message="violations?.updatedAt" name="updatedAt" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.updatedAt = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.status" :label="$t('user.status')" :error="Boolean(violations?.status)"
        :error-message="violations?.status" name="status" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.status = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.legacyId" :label="$t('user.legacyId')" :error="Boolean(violations?.legacyId)"
        :error-message="violations?.legacyId" name="legacyId" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.legacyId = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.apellido" :label="$t('user.apellido')" :error="Boolean(violations?.apellido)"
        :error-message="violations?.apellido" name="apellido" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.apellido = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.nombre" :label="$t('user.nombre')" :error="Boolean(violations?.nombre)"
        :error-message="violations?.nombre" name="nombre" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.nombre = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.email" :label="$t('user.email')" :error="Boolean(violations?.email)"
        :error-message="violations?.email" name="email" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.email = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.nit" :label="$t('user.nit')" :error="Boolean(violations?.nit)"
        :error-message="violations?.nit" name="nit" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.nit = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.telefono" :label="$t('user.telefono')" :error="Boolean(violations?.telefono)"
        :error-message="violations?.telefono" name="telefono" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.telefono = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.direccion" :label="$t('user.direccion')" :error="Boolean(violations?.direccion)"
        :error-message="violations?.direccion" name="direccion" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.direccion = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.localidad" :label="$t('user.localidad')" :error="Boolean(violations?.localidad)"
        :error-message="violations?.localidad" name="localidad" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.localidad = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.fullName" :label="$t('user.fullName')" :error="Boolean(violations?.fullName)"
        :error-message="violations?.fullName" name="fullName" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.fullName = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.id" :label="$t('user.id')" :error="Boolean(violations?.id)" :error-message="violations?.id"
        name="id" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.id = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.userIdentifier" :label="$t('user.userIdentifier')"
        :error="Boolean(violations?.userIdentifier)" :error-message="violations?.userIdentifier" name="userIdentifier"
        type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.userIdentifier = undefined" />
        </template>
      </q-input>
      <FormRepeater :values="item.roles" :label="$t('user.roles')" class="col-12 col-md-8"
        @update="(values: any[]) => (item.roles = values)" />
      <q-input v-model="item.token" :label="$t('user.token')" :error="Boolean(violations?.token)"
        :error-message="violations?.token" name="token" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.token = undefined" />
        </template>
      </q-input>
      <q-input v-model="item.validTokenStrings" :label="$t('user.validTokenStrings')"
        :error="Boolean(violations?.validTokenStrings)" :error-message="violations?.validTokenStrings"
        name="validTokenStrings" type="text" bottom-slots filled class="col-12 col-md-4">
        <template #append>
          <q-icon name="close" class="cursor-pointer" @click.prevent.stop="item.validTokenStrings = undefined" />
        </template>
      </q-input>
    </div>

    <div>
      <q-btn label="Submit" type="submit" color="primary" />
      <q-btn label="Reset" type="reset" color="primary" flat class="q-ml-sm" @click="resetForm" />
    </div>
  </q-form>
</template>

<script lang="ts" setup>
import { Ref, ref, toRef } from 'vue';
import { formatDateInput } from 'src/utils/autoimport/date';
import FormRepeater from 'components/common/CommonFormRepeater.vue';
import type { Item } from 'src/types/item';
import type { User } from 'src/types/user';
import type { SubmissionErrors } from 'src/types/error';

let props = defineProps<{
  values?: User;
  errors?: SubmissionErrors;
}>();

const violations = toRef(props, 'errors');

let item: Ref<User> = ref({});

if (props.values) {
  item.value = {
    ...props.values,
  };
}

function resetForm() {
  item.value = { ...props.values };
}

let emit = defineEmits<{
  (e: 'submit', item: User): void;
}>();

function emitSubmit() {
  emit('submit', item.value);
}
</script>
