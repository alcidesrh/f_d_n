<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar class="rounded-none border-none! bg-transparent px-2">
      <template #start>
        <PageHead title="" />
      </template>
    </Toolbar>

    <div class="ayuda px-2">
      <aside class="ayuda-indice" aria-label="Temas">
        <IconField>
          <InputIcon><icon name="search" /></InputIcon>
          <InputText
            v-model="busqueda"
            placeholder="Buscar: contraseña, rol, estación…"
            aria-label="Buscar en la ayuda"
            fluid
          />
        </IconField>
        <nav class="temas">
          <button
            v-for="g in grupos"
            :key="g.tema.id"
            type="button"
            class="tema tap-target"
            :class="{ 'tema--activo': tema === g.tema.id }"
            @click="tema = tema === g.tema.id ? null : g.tema.id"
          >
            <icon :name="g.tema.icono" />
            <span class="min-w-0 flex-1 truncate text-left">{{ g.tema.titulo }}</span>
            <span class="text-xs text-muted-color">{{ g.guias.length }}</span>
          </button>
        </nav>
      </aside>

      <main class="flex min-w-0 flex-col gap-5">
        <Message v-if="pantalla" severity="info" :closable="false">
          <div class="flex flex-wrap items-center gap-2">
            <span>{{
              deEstaPantalla.length
                ? 'Guías de la pantalla donde estaba.'
                : 'Todavía no hay guías para esa pantalla.'
            }}</span>
            <Button label="Ver todas" size="small" text @click="verTodas" />
          </div>
        </Message>

        <p v-if="!secciones.length" class="py-10 text-center text-muted-color">
          Nada coincide con «{{ busqueda }}».
        </p>

        <section v-for="s in secciones" :key="s.tema.id" class="flex flex-col gap-2">
          <h2
            class="flex items-center gap-2 text-sm font-semibold tracking-wide text-muted-color uppercase"
          >
            <icon :name="s.tema.icono" />{{ s.tema.titulo }}
          </h2>

          <article
            v-for="g in s.guias"
            :id="`guia-${g.id}`"
            :key="g.id"
            class="guia"
            :class="{ 'guia--abierta': abierta === g.id }"
          >
            <button
              type="button"
              class="guia-cabecera"
              :aria-expanded="abierta === g.id"
              @click="alternar(g.id)"
            >
              <span class="min-w-0 flex-1">
                <span class="block font-medium">{{ g.titulo }}</span>
                <span class="block text-sm text-muted-color">{{ g.resumen }}</span>
                <span class="mt-1.5 flex flex-wrap gap-1">
                  <Tag v-if="!g.para.length" value="Para todos" severity="secondary" />
                  <Tag v-for="a in g.para" :key="a.etiqueta" :value="a.etiqueta" severity="info" />
                </span>
              </span>
              <icon
                :name="abierta === g.id ? 'expand-less' : 'expand-more'"
                class="text-muted-color"
              />
            </button>

            <div v-if="abierta === g.id" class="guia-cuerpo">
              <p v-if="g.antes" class="antes">
                <icon name="history" class="mt-0.5 shrink-0" />
                <span
                  ><strong>Antes:</strong> <AyudaTexto :texto="g.antes" :visibles="idsVisibles"
                /></span>
              </p>

              <div v-if="g.tabla" class="equivalencias" role="table" aria-label="Equivalencias">
                <div class="equivalencia equivalencia--cabecera" role="row">
                  <span role="columnheader">Sistema anterior</span>
                  <span role="columnheader">Ahora</span>
                </div>
                <div v-for="f in g.tabla" :key="f.antes" class="equivalencia" role="row">
                  <span role="cell" class="text-muted-color">{{ f.antes }}</span>
                  <span role="cell"><AyudaTexto :texto="f.ahora" :visibles="idsVisibles" /></span>
                </div>
              </div>

              <ol v-if="g.pasos" class="pasos">
                <li v-for="p in g.pasos" :key="p">
                  <AyudaTexto :texto="p" :visibles="idsVisibles" />
                </li>
              </ol>

              <div v-if="g.ejemplo" class="ejemplo">
                <p class="text-sm font-semibold">
                  <icon name="lightbulb-outline" class="mr-1" />Ejemplo:
                  <AyudaTexto :texto="g.ejemplo.caso" :visibles="idsVisibles" />
                </p>
                <ol class="pasos">
                  <li v-for="p in g.ejemplo.pasos" :key="p">
                    <AyudaTexto :texto="p" :visibles="idsVisibles" />
                  </li>
                </ol>
              </div>

              <ul v-if="g.ojo" class="ojo">
                <li v-for="o in g.ojo" :key="o">
                  <icon name="info-outline" class="mt-0.5 shrink-0 text-amber-600" /><AyudaTexto
                    :texto="o"
                    :visibles="idsVisibles"
                  />
                </li>
              </ul>

              <div v-if="g.abrir" class="flex justify-end">
                <RouterLink :to="g.abrir.destino" class="p-button p-button-sm p-button-outlined">
                  {{ g.abrir.etiqueta }}<icon name="arrow-forward" class="ml-1" />
                </RouterLink>
              </div>
            </div>
          </article>
        </section>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAccesoStore } from '@/core/acceso/store'
import { agruparPorTema, coincide, deLaPantalla, veGuia } from '@/core/ayuda/reglas'
import AyudaTexto from './AyudaTexto.vue'
import { GUIAS, TEMAS } from './contenido'

defineOptions({ name: 'AyudaPage' })

const route = useRoute()
const router = useRouter()
const acceso = useAccesoStore()

const busqueda = ref('')
const tema = ref<string | null>(null)

const abierta = computed(() => (typeof route.params.guia === 'string' && route.params.guia) || null)
const pantalla = computed(() =>
  typeof route.query.pantalla === 'string' ? route.query.pantalla : null,
)

const visibles = computed(() => GUIAS.filter((g) => veGuia(g, acceso.acceso)))
const deEstaPantalla = computed(() =>
  pantalla.value ? visibles.value.filter((g) => deLaPantalla(g, [pantalla.value!])) : [],
)
const grupos = computed(() => agruparPorTema(TEMAS, visibles.value))
/** Las guías que ve este usuario: los enlaces entre guías solo llevan a estas. */
const idsVisibles = computed(() => new Set(visibles.value.map((g) => g.id)))

const secciones = computed(() => {
  const base = deEstaPantalla.value.length ? deEstaPantalla.value : visibles.value
  return agruparPorTema(
    TEMAS.filter((t) => !tema.value || t.id === tema.value),
    base.filter((g) => coincide(g, busqueda.value)),
  )
})

function alternar(id: string) {
  void router.replace({
    params: { guia: abierta.value === id ? undefined : id },
    query: route.query,
  })
}

function verTodas() {
  void router.replace({ params: { guia: abierta.value ?? undefined }, query: {} })
}

// Un enlace directo a una guía (`/ayuda/otorgar-rol`) la abre y la trae a la vista.
watch(
  abierta,
  async (id) => {
    if (!id) return
    await nextTick()
    document.getElementById(`guia-${id}`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
  },
  { immediate: true },
)
</script>

<style scoped>
.ayuda {
  display: grid;
  gap: 1rem;
  align-items: start;
  max-width: 64rem;
}
@container (min-width: 48rem) {
  .ayuda {
    grid-template-columns: 15rem minmax(0, 1fr);
  }
  .ayuda-indice {
    position: sticky;
    top: 0.5rem;
  }
}
.ayuda-indice {
  display: grid;
  gap: 0.5rem;
}
.temas {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}
@container (min-width: 48rem) {
  .temas {
    flex-direction: column;
  }
}
.tema {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 0.6rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
}
.tema:hover {
  background: var(--p-surface-100);
}
.tema--activo {
  background: var(--p-primary-50);
  color: var(--p-primary-700);
}
.guia {
  border: 1px solid var(--p-surface-200);
  border-radius: 0.6rem;
  background: var(--p-surface-0);
}
.guia--abierta {
  border-color: var(--p-primary-300);
}
.guia-cabecera {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.75rem;
  padding: 0.7rem 0.9rem;
  text-align: left;
}
.guia-cuerpo {
  display: grid;
  gap: 0.9rem;
  padding: 0 0.9rem 0.9rem;
  font-size: 0.9rem;
}
.antes {
  display: flex;
  gap: 0.5rem;
  padding: 0.5rem 0.7rem;
  border-left: 3px solid var(--p-surface-400);
  border-radius: 0.3rem;
  background: var(--p-surface-50);
}
.pasos {
  display: grid;
  gap: 0.35rem;
  margin: 0;
  padding-left: 1.4rem;
  list-style: decimal;
}
.ejemplo {
  display: grid;
  gap: 0.4rem;
  padding: 0.6rem 0.8rem;
  border-radius: 0.5rem;
  background: var(--c-info-soft);
}
.ojo {
  display: grid;
  gap: 0.35rem;
  margin: 0;
  padding: 0;
  list-style: none;
  li {
    display: flex;
    gap: 0.5rem;
  }
}
.equivalencias {
  display: grid;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
  overflow: hidden;
}
.equivalencia {
  display: grid;
  gap: 0.25rem 1rem;
  padding: 0.5rem 0.7rem;
  border-top: 1px solid var(--p-surface-100);
}
@container (min-width: 40rem) {
  .equivalencia {
    grid-template-columns: 1fr 1fr;
  }
}
.equivalencia--cabecera {
  display: none;
  border-top: none;
  background: var(--p-surface-50);
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}
@container (min-width: 40rem) {
  .equivalencia--cabecera {
    display: grid;
  }
}
</style>
