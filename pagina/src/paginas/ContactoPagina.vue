<!-- Formulario de contacto (se guarda y llega al buzón de la empresa) y otros medios. -->
<template>
  <div>
    <CabeceraPagina :titulo="t('contacto.titulo')" :entradilla="t('contacto.intro')" />
    <div class="contenedor mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <section class="panel">
        <Message v-if="enviado" severity="success" :closable="false">{{ t('contacto.enviado') }}</Message>
        <FormKit v-else id="contacto" v-model="datos" type="form" :actions="false" form-class="grid grid-cols-1 gap-x-4 md:grid-cols-2" @submit="enviar">
          <FormKit type="InputText" name="nombre" :label="t('contacto.nombre')" validation="required" fluid autocomplete="name" />
          <FormKit type="InputText" name="email" :label="t('contacto.email')" validation="required|email" fluid autocomplete="email" inputmode="email" />
          <FormKit type="InputText" name="telefono" :label="t('contacto.telefono')" fluid autocomplete="tel" inputmode="tel" outer-class="md:col-span-2" />
          <FormKit type="TextArea" name="mensaje" :label="t('contacto.mensaje')" validation="required|length:5,4000" rows="5" auto-resize fluid outer-class="md:col-span-2" />
          <!-- Trampa para robots: oculto para las personas. -->
          <div class="absolute -left-[9999px]" aria-hidden="true"><label>Web <input v-model="web" type="text" tabindex="-1" autocomplete="off" /></label></div>
          <Message v-if="error" severity="error" :closable="false" class="md:col-span-2">{{ error }}</Message>
          <div class="md:col-span-2">
            <Button type="submit" :loading="enviando" class="w-full md:w-auto"><icon name="send" size="1.1rem" /><span>{{ t('contacto.enviar') }}</span></Button>
          </div>
        </FormKit>
      </section>
      <aside class="panel flex flex-col gap-3 text-sm">
        <p class="m-0 text-muted-color">{{ t('contacto.otroMedio') }}</p>
        <a :href="CONTACTO.telefonoEnlace" class="flex items-center gap-2 font-medium no-underline"><icon name="phone" />{{ CONTACTO.telefono }}</a>
        <a :href="CONTACTO.whatsapp" target="_blank" rel="noopener" class="flex items-center gap-2 font-medium no-underline"><icon name="brand-whatsapp" />WhatsApp</a>
        <a :href="CONTACTO.facebook" target="_blank" rel="noopener" class="flex items-center gap-2 font-medium no-underline"><icon name="brand-facebook" />Facebook</a>
        <p class="m-0 flex items-start gap-2"><icon name="map-pin" class="mt-0.5" />{{ CONTACTO.direccion }}</p>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import * as api from '@/api'
import CabeceraPagina from '@/componentes/CabeceraPagina.vue'
import { mensajeDeError } from '@/errores'
import { CONTACTO } from '@/i18n/contenido'

const { t, te, locale } = useI18n()
const datos = ref<Record<string, unknown>>({})
const web = ref('')
const enviando = ref(false)
const enviado = ref(false)
const error = ref('')

async function enviar(v: Record<string, unknown>) {
  enviando.value = true
  error.value = ''
  try {
    await api.contacto({
      nombre: String(v.nombre ?? ''),
      email: String(v.email ?? ''),
      telefono: v.telefono ? String(v.telefono) : undefined,
      mensaje: String(v.mensaje ?? ''),
      idioma: locale.value,
      web: web.value,
    })
    enviado.value = true
  } catch (e) {
    error.value = mensajeDeError(e, t, te).titulo
  } finally {
    enviando.value = false
  }
}
</script>
