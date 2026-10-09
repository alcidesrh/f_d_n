<!--
  Bandeja del chat: conversaciones recientes y una sola búsqueda que encuentra
  conversaciones y personas (elegir una persona abre o crea el directo).
-->
<template>
  <aside class="bandeja">
    <header class="bandeja__cabeza">
      <label class="buscar">
        <icon name="search" size="1.1rem" color="text-current" />
        <input v-model="busqueda" type="search" placeholder="Buscar persona, estación o grupo" aria-label="Buscar" @focus="void chat.cargarContactos()" />
        <button v-if="busqueda" type="button" class="tap-target" aria-label="Limpiar búsqueda" @click="busqueda = ''"><icon name="close" size="1rem" color="text-current" /></button>
      </label>
      <div class="flex items-center">
        <button v-if="permisoAvisos === 'default'" type="button" class="icono tap-target" aria-label="Avisos del navegador" v-tooltip.bottom="'Avisarme aunque esté en otra pestaña'" @click="pedirAvisos">
          <icon name="notifications-active-outline" color="text-current" />
        </button>
        <button type="button" class="icono tap-target" aria-label="Nuevo grupo" v-tooltip.bottom="'Nuevo grupo'" @click="grupo = true">
          <icon name="group-add-outline" color="text-current" />
        </button>
      </div>
    </header>

    <div class="bandeja__lista">
      <template v-if="!chat.listo">
        <div v-for="n in 6" :key="n" class="fila">
          <Skeleton shape="circle" size="2.5rem" />
          <div class="flex-1"><Skeleton width="60%" class="mb-2" /><Skeleton width="85%" /></div>
        </div>
      </template>

      <template v-else>
        <p v-if="busqueda && conversaciones.length" class="seccion">Conversaciones</p>
        <button v-for="c in conversaciones" :key="c.id" type="button" class="fila" :class="{ 'fila--activa': c.id === activo, 'fila--nueva': c.noLeidos > 0 }" @click="emit('abrir', c.id)">
          <ChatAvatar :id="c.id" :nombre="c.nombre" :grupo="c.tipo === 'grupo'" :sistema="c.tipo === 'sistema'" :ambito="c.contacto?.ambito" :foto="c.contacto?.foto" />
          <span class="fila__texto">
            <span class="fila__linea">
              <span class="fila__nombre">{{ c.nombre }}</span>
              <span v-if="c.ultimo" class="fila__hora">{{ fechaBandeja(c.ultimo.fecha) }}</span>
            </span>
            <span class="fila__linea">
              <span class="fila__extracto">
                <template v-if="c.ultimo"><template v-if="c.ultimo.autor === chat.yo?.id">Tú: </template>{{ c.ultimo.extracto }}</template>
                <template v-else>{{ c.tipo === "grupo" ? `${c.miembros.length} miembros` : (c.contacto?.lugar ?? "Nueva conversación") }}</template>
              </span>
              <span v-if="c.noLeidos" class="fila__badge">{{ c.noLeidos > 99 ? "99+" : c.noLeidos }}</span>
            </span>
          </span>
        </button>

        <template v-if="busqueda">
          <p v-if="personas.length" class="seccion">Personas</p>
          <button v-for="p in personas" :key="`p${p.id}`" type="button" class="fila" @click="abrirCon(p.id)">
            <ChatAvatar :id="p.id" :nombre="p.nombre" :ambito="p.ambito" :foto="p.foto" />
            <span class="fila__texto">
              <span class="fila__nombre">{{ p.nombre }}</span>
              <span class="fila__extracto">{{ p.lugar ?? AMBITOS[p.ambito] }}</span>
            </span>
          </button>
          <p v-if="!conversaciones.length && !personas.length" class="vacio">{{ chat.contactos ? "Sin resultados." : "Buscando…" }}</p>
        </template>

        <div v-else-if="!chat.canales.length" class="vacio">
          <icon name="forum-outline" size="2.25rem" color="text-current" />
          <p>Aún no hay conversaciones.<br />Busque a alguien arriba para empezar.</p>
        </div>
      </template>
    </div>

    <NuevoGrupoDialog v-model:visible="grupo" @creado="(id) => emit('abrir', id)" />
  </aside>
</template>

<script setup lang="ts">
import { useChatStore } from "@/core/chat/store";
import { coincide, fechaBandeja } from "@/core/chat/modelo";
import { notify } from "@/core/notify";
import ChatAvatar from "@/shared/chat/ChatAvatar.vue";
import NuevoGrupoDialog from "./NuevoGrupoDialog.vue";

defineProps<{ activo: number | null }>();
const emit = defineEmits<{ abrir: [canal: number] }>();

const AMBITOS = { administracion: "Administración", estacion: "Estación", agencia: "Agencia" } as const;
const MAX_PERSONAS = 30;

const chat = useChatStore();
const busqueda = ref("");
const grupo = ref(false);

const conversaciones = computed(() => (busqueda.value ? chat.canales.filter((c) => coincide([c.nombre, c.contacto?.lugar ?? "", ...c.miembros.map((m) => m.nombre)].join(" "), busqueda.value)) : chat.canales));
/** Personas que coinciden y con las que todavía no hay directo en la lista. */
const personas = computed(() => {
  const conDirecto = new Set(conversaciones.value.filter((c) => c.tipo === "directo").map((c) => c.contacto?.id));
  return (chat.contactos ?? []).filter((p) => !conDirecto.has(p.id) && coincide(`${p.nombre} ${p.lugar ?? ""} ${AMBITOS[p.ambito]}`, busqueda.value)).slice(0, MAX_PERSONAS);
});

async function abrirCon(usuario: number) {
  try {
    const id = await chat.directo(usuario);
    busqueda.value = "";
    emit("abrir", id);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  }
}

const permisoAvisos = ref(typeof Notification === "undefined" ? "denied" : Notification.permission);
async function pedirAvisos() {
  permisoAvisos.value = await Notification.requestPermission();
}
</script>

<style scoped>
.bandeja {
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: var(--p-content-background);
}
.bandeja__cabeza {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.75rem 0.5rem 0.5rem 1rem;
}
.icono {
  display: grid;
  place-items: center;
  border-radius: 0.6rem;
  color: var(--p-surface-600);
  &:hover {
    color: var(--p-primary-color);
    background: var(--p-surface-100);
  }
}
.buscar {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0 0.75rem;
  height: 2.5rem;
  border-radius: 0.75rem;
  color: var(--p-surface-500);
  background: var(--p-surface-100);
  transition: box-shadow var(--transition);
  &:focus-within {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--p-primary-color) 45%, transparent);
  }
  input {
    flex: 1;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--p-surface-800);
    font-size: 0.9rem;
  }
  input::-webkit-search-cancel-button {
    display: none;
  }
}
.bandeja__lista {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 0.25rem 0.5rem 1rem;
}
.seccion {
  margin: 0.75rem 0.75rem 0.25rem;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--p-surface-500);
}
.fila {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.6rem 0.75rem;
  border-radius: 0.75rem;
  text-align: left;
  cursor: pointer;
  transition: background var(--transition);
  &:hover {
    background: var(--p-surface-100);
  }
}
.fila--activa,
.fila--activa:hover {
  background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
}
.fila__texto {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  flex: 1;
  min-width: 0;
}
.fila__linea {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
  min-width: 0;
}
.fila__nombre,
.fila__extracto {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fila__nombre {
  font-weight: 500;
  color: var(--p-surface-800);
}
.fila__extracto {
  font-size: 0.82rem;
  color: var(--p-surface-500);
}
.fila__hora {
  flex: none;
  font-size: 0.72rem;
  color: var(--p-surface-500);
}
.fila--nueva .fila__nombre {
  font-weight: 650;
}
.fila--nueva .fila__extracto {
  color: var(--p-surface-700);
}
.fila--nueva .fila__hora {
  color: var(--p-primary-color);
  font-weight: 600;
}
.fila__badge {
  flex: none;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.35rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.25rem;
  text-align: center;
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
}
.vacio {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 2.5rem 1rem;
  font-size: 0.875rem;
  line-height: 1.5;
  text-align: center;
  color: var(--p-surface-500);
  p {
    margin: 0;
  }
}
</style>
