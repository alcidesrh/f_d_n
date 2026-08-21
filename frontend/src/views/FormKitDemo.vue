<template>
  <div>
    <!-- <PageHead
      title="FormKit + PrimeVue"
      subtitle="Custom inputs de PrimeVue v4 registrados como tipos de FormKit"
    /> -->
    <Tabs value="0">
      <TabList>
        <Tab value="0">Demo I</Tab>
        <Tab value="1">Demo II</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="0">
          <EntityFormDemo />
        </TabPanel>
        <TabPanel value="1">
          <div class="card">
            <FormKit type="form" v-model="formData" submit-label="Guardar" @submit="onSubmit">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                <Fluid>
                  <FormKit
                    type="InputText"
                    name="nombre"
                    label="Nombre"
                    placeholder="Nombre completo"
                    validation="required"
                  />
                  <FormKit
                    type="InputMask"
                    name="telefono"
                    label="Teléfono"
                    mask="999-999-9999"
                    placeholder="999-999-9999"
                  />
                  <FormKit
                    type="InputNumber"
                    name="edad"
                    label="Edad"
                    :min="0"
                    :max="120"
                    suffix=" años"
                  />
                  <FormKit
                    type="Password"
                    name="password"
                    label="Contraseña"
                    toggle-mask
                    feedback
                    validation="required|min:6"
                  />
                  <FormKit
                    type="DatePicker"
                    name="fechaNacimiento"
                    label="Fecha de nacimiento"
                    date-format="dd/mm/yy"
                    show-icon
                  />
                  <FormKit
                    type="TextArea"
                    name="bio"
                    label="Biografía"
                    placeholder="Cuéntanos de ti"
                    :rows="3"
                    auto-resize
                  />
                </Fluid>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                <Fluid>
                  <FormKit
                    type="Select"
                    name="pais"
                    label="País"
                    :options="paises"
                    option-label="name"
                    option-value="code"
                    placeholder="Elige un país"
                    validation="required"
                    show-clear
                  />
                  <FormKit
                    type="MultiSelect"
                    name="intereses"
                    label="Intereses"
                    :options="intereses"
                    option-label="label"
                    option-value="value"
                    placeholder="Elige varios"
                    display="chip"
                  />
                  <FormKit
                    type="CascadeSelect"
                    name="ubicacion"
                    label="Ubicación"
                    :options="ubicaciones"
                    option-label="label"
                    option-group-label="label"
                    option-group-children="children"
                    placeholder="Selecciona una ubicación"
                  />
                  <FormKit
                    type="TreeSelect"
                    name="archivos"
                    label="Archivos"
                    :options="archivos"
                    selection-mode="checkbox"
                    placeholder="Selecciona archivos"
                  />
                  <FormKit
                    type="AutoComplete"
                    name="marca"
                    label="Marca"
                    :suggestions="sugerencias"
                    placeholder="Busca una marca"
                    @complete="buscarMarcas"
                  />
                </Fluid>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                <FormKit
                  type="Checkbox"
                  name="newsletter"
                  label="Suscripción"
                  help="Recibir novedades por correo"
                />
                <FormKit type="ToggleSwitch" name="activo" label="Activo" help="Cuenta activa" />
                <FormKit
                  type="RadioButton"
                  name="genero"
                  label="Género"
                  :options="generos"
                  option-label="label"
                  option-value="value"
                  validation="required"
                />
                <FormKit
                  type="SelectButton"
                  name="prioridad"
                  label="Prioridad"
                  :options="prioridades"
                  option-label="label"
                  option-value="value"
                />
                <FormKit
                  type="SelectButton"
                  name="dias"
                  label="Días disponibles"
                  :options="dias"
                  option-label="label"
                  option-value="value"
                  multiple
                />
              </div>
            </FormKit>

            <div class="mt-6">
              <h3 class="font-semibold mb-2">Datos del formulario</h3>
              <pre class="p-4 rounded-lg bg-surface-100 text-sm overflow-auto">{{
                JSON.stringify(formData, null, 2)
              }}</pre>
            </div>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { TreeNode } from 'primevue/treenode'
import EntityFormDemo from './EntityFormDemo.vue'
const formData = ref<Record<string, unknown>>({ activo: true })
const submitted = ref<Record<string, unknown> | null>(null)

const onSubmit = (data: Record<string, unknown>) => {
  submitted.value = data
}

const paises = [
  { name: 'Guatemala', code: 'GT' },
  { name: 'España', code: 'ES' },
  { name: 'México', code: 'MX' },
  { name: 'Argentina', code: 'AR' },
]

const intereses = [
  { label: 'Tecnología', value: 'tech' },
  { label: 'Deportes', value: 'sports' },
  { label: 'Música', value: 'music' },
  { label: 'Cine', value: 'movies' },
]

const prioridades = [
  { label: 'Baja', value: 'baja' },
  { label: 'Media', value: 'media' },
  { label: 'Alta', value: 'alta' },
]

const dias = [
  { label: 'Lun', value: 'mon' },
  { label: 'Mar', value: 'tue' },
  { label: 'Mié', value: 'wed' },
  { label: 'Jue', value: 'thu' },
  { label: 'Vie', value: 'fri' },
]

const ubicaciones = [
  {
    label: 'América',
    children: [
      { label: 'Guatemala', children: [{ label: 'Ciudad de Guatemala' }, { label: 'Antigua' }] },
      { label: 'México', children: [{ label: 'CDMX' }] },
    ],
  },
  {
    label: 'Europa',
    children: [{ label: 'España', children: [{ label: 'Madrid' }, { label: 'Barcelona' }] }],
  },
]

const generos = [
  { label: 'Masculino', value: 'M' },
  { label: 'Femenino', value: 'F' },
  { label: 'Otro', value: 'O' },
]

const archivos: TreeNode[] = [
  {
    key: '0',
    label: 'Documentos',
    data: 'Documentos',
    children: [
      { key: '0-0', label: 'Facturas', data: 'Facturas' },
      { key: '0-1', label: 'Contratos', data: 'Contratos' },
    ],
  },
  {
    key: '1',
    label: 'Imágenes',
    data: 'Imágenes',
    children: [
      { key: '1-0', label: 'Logos', data: 'Logos' },
      { key: '1-1', label: 'Capturas', data: 'Capturas' },
    ],
  },
]

const marcas = ['Mercedes', 'BMW', 'Audi', 'Tesla', 'Toyota', 'Honda', 'Ford']
const sugerencias = ref<string[]>([])

const buscarMarcas = (e: { query: string }) => {
  const query = e.query.trim().toLowerCase()
  sugerencias.value = query ? marcas.filter((m) => m.toLowerCase().includes(query)) : [...marcas]
}
</script>
