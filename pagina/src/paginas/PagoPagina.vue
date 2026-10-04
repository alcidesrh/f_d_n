<!--
  Resumen de la compra y pago (ADR-023). Los asientos ya están apartados
  (en las taquillas se ven ocupados). "Volver a los asientos" o salir sin
  pagar los suelta. El pago puede tener pasos de 3-D Secure (recolección de
  datos del dispositivo, desafío del banco en un iframe): tras cada uno la
  página vuelve a enviar el pago con `continuar`. Los datos de la tarjeta
  solo viven en esta página y viajan directo al backend, que los pasa a la
  pasarela sin guardarlos.
-->
<template>
  <div
    class="contenedor py-4 md:py-8"
    :class="{ 'pb-28 lg:pb-8': !carrito.vacio }"
  >
    <!-- Como en el inicio: con la compra a la vista, el resto se atenúa como detrás de un modal. -->
    <Transition name="velo">
      <div
        v-if="!cargando && !carrito.vacio"
        class="fixed inset-0 z-[25] bg-slate-900/60"
        aria-hidden="true"
      />
    </Transition>

    <div class="relative z-[30] mb-3 flex items-center justify-between gap-3">
      <button
        type="button"
        class="inline-flex items-center gap-1 border-0 bg-transparent p-0 text-sm font-medium text-primary"
        :class="{ '!text-white': !cargando && !carrito.vacio }"
        :disabled="pagando"
        @click="volver"
      >
        <icon name="arrow-left" size="1rem" />{{ t("pago.volver") }}
      </button>
      <button
        type="button"
        class="inline-flex cursor-pointer items-center gap-1 border-0 bg-transparent p-0 text-sm font-medium text-primary"
        :class="{ '!text-white': !cargando && !carrito.vacio }"
        :disabled="pagando"
        @click="cancelarCompra"
      >
        <icon name="x" size="1rem" />{{ t("barra.cancelar") }}
      </button>
    </div>

    <div v-if="cargando" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <Skeleton height="28rem" border-radius="1rem" /><Skeleton
        height="16rem"
        border-radius="1rem"
      />
    </div>

    <div
      v-else-if="carrito.vacio"
      class="panel mx-auto flex max-w-xl flex-col items-center gap-3 py-8 text-center"
    >
      <icon name="clock" size="2.5rem" class="text-acento-500" />
      <p class="m-0 font-medium">
        {{ vencido ? t("pago.vencido") : t("pago.sinCarrito") }}
      </p>
      <Button
        :label="vencido ? t('pago.elegirDeNuevo') : t('pago.buscar')"
        @click="volver"
      />
    </div>

    <div
      v-else
      class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_23rem] lg:gap-6"
    >
      <h1 class="relative z-[30] m-0 text-2xl font-bold text-white lg:col-span-2">
        {{ t("pago.titulo") }}
      </h1>

      <!-- Resumen: arriba en móvil, al lado en escritorio -->
      <aside
        class="panel relative z-[30] lg:sticky lg:top-20 lg:col-start-2 lg:row-start-2"
        :aria-label="t('pago.resumen')"
      >
        <h2 class="m-0 mb-3 text-base font-semibold">
          {{ t("pago.resumen") }}
        </h2>
        <ResumenCarrito @vencio="vencio" />
        <div class="mt-4 hidden lg:block">
          <Button
            class="boton-compra"
            size="large"
            fluid
            :loading="pagando"
            @click="enviar"
          >
            <icon name="lock" size="1.1rem" /><span>{{
              pagando
                ? t("pago.pagando")
                : t("pago.pagar", {
                    total: carrito.carrito?.total?.texto ?? "",
                  })
            }}</span>
          </Button>
        </div>
        <p class="m-0 mt-3 flex items-center gap-1.5 text-xs text-muted-color">
          <icon name="shield-check" size="1rem" />{{ t("pago.seguro") }}
        </p>
      </aside>

      <FormKit
        id="pago"
        v-model="datos"
        type="form"
        :actions="false"
        :incomplete-message="t('pago.revise')"
        form-class="relative z-[30] flex flex-col gap-4"
        class="relative z-[30] lg:col-start-1 lg:row-start-2"
        @submit="pagar"
      >
        <section class="panel">
          <h2 class="m-0 mb-4 flex items-center gap-2 text-lg font-semibold">
            <icon name="users" class="text-marca-700" />{{ t("pago.datos") }}
          </h2>
          <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <FormKit
              type="InputText"
              name="nombre"
              :label="t('pago.nombre')"
              validation="required"
              fluid
              autocomplete="given-name"
            />
            <FormKit
              type="InputText"
              name="apellido"
              :label="t('pago.apellido')"
              fluid
              autocomplete="family-name"
            />
            <FormKit
              type="InputText"
              name="email"
              :label="t('pago.email')"
              :help="t('pago.emailAyuda')"
              validation="required|email"
              fluid
              autocomplete="email"
              inputmode="email"
            />
            <FormKit
              type="InputText"
              name="telefono"
              :label="t('pago.telefono')"
              fluid
              autocomplete="tel"
              inputmode="tel"
            />
            <FormKit
              type="InputText"
              name="nit"
              :label="t('pago.nit')"
              :help="t('pago.nitAyuda')"
              validation="required"
              fluid
            />
            <FormKit
              type="Select"
              name="nacionalidad"
              :label="t('pago.nacionalidad')"
              :options="naciones"
              show-clear
              filter
              fluid
            />
            <FormKit
              type="Select"
              name="tipoDocumento"
              :label="t('pago.documento')"
              :options="documentos"
              show-clear
              fluid
            />
            <FormKit
              type="InputText"
              name="numeroDocumento"
              :label="t('pago.numeroDocumento')"
              fluid
            />
          </div>
        </section>

        <section class="panel">
          <h2 class="m-0 mb-1 flex items-center gap-2 text-lg font-semibold">
            <icon name="credit-card" class="text-marca-700" />{{
              t("pago.tarjeta")
            }}
          </h2>
          <p class="m-0 mb-4 text-sm text-muted-color">
            {{ t("pago.tarjetaAyuda") }}
          </p>
          <div class="grid grid-cols-2 gap-x-4">
            <FormKit
              type="InputText"
              name="titular"
              :label="t('pago.titular')"
              validation="required"
              fluid
              autocomplete="cc-name"
              outer-class="col-span-2 md:col-span-1"
            />
            <FormKit
              type="InputText"
              name="numero"
              :label="t('pago.numero')"
              validation="required|tarjeta"
              :validation-rules="{ tarjeta: tarjetaValida }"
              :validation-messages="{ tarjeta: t('pago.tarjetaInvalida') }"
              fluid
              autocomplete="cc-number"
              inputmode="numeric"
              outer-class="col-span-2 md:col-span-1"
            />
            <FormKit
              type="InputMask"
              name="expira"
              :label="t('pago.vence')"
              mask="99/99"
              placeholder="MM/AA"
              validation="required|vigente"
              :validation-rules="{ vigente }"
              :validation-messages="{ vigente: t('pago.venceInvalida') }"
              fluid
              autocomplete="cc-exp"
              inputmode="numeric"
            />
            <FormKit
              type="InputText"
              name="cvv"
              :label="t('pago.cvv')"
              validation="required|cvv"
              :validation-rules="{ cvv: cvvValido }"
              :validation-messages="{ cvv: t('pago.cvvInvalido') }"
              fluid
              autocomplete="cc-csc"
              inputmode="numeric"
              maxlength="4"
            />
          </div>
        </section>

        <section class="panel">
          <h2 class="m-0 mb-1 flex items-center gap-2 text-lg font-semibold">
            <icon name="map-pin" class="text-marca-700" />{{
              t("pago.direccion")
            }}
          </h2>
          <p class="m-0 mb-4 text-sm text-muted-color">
            {{ t("pago.direccionAyuda") }}
          </p>
          <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <FormKit
              type="Select"
              name="pais"
              :label="t('pago.pais')"
              :options="listaPaises"
              filter
              validation="required"
              fluid
              autocomplete="country"
            />
            <FormKit
              type="InputText"
              name="region"
              :label="
                usaCodigoPostal ? t('pago.regionCodigo') : t('pago.region')
              "
              :validation="
                usaCodigoPostal ? 'required|matches:/^[A-Za-z]{2}$/' : ''
              "
              :validation-messages="{ matches: t('pago.regionInvalida') }"
              fluid
              autocomplete="address-level1"
            />
            <FormKit
              type="InputText"
              name="ciudad"
              :label="t('pago.ciudad')"
              validation="required"
              fluid
              autocomplete="address-level2"
              maxlength="50"
            />
            <FormKit
              type="InputText"
              name="direccion"
              :label="t('pago.calle')"
              validation="required"
              fluid
              autocomplete="address-line1"
              maxlength="60"
            />
            <FormKit
              v-if="usaCodigoPostal"
              type="InputText"
              name="codigoPostal"
              :label="t('pago.codigoPostal')"
              validation="required"
              fluid
              autocomplete="postal-code"
              maxlength="10"
            />
          </div>
        </section>

        <Message v-if="error" severity="error" :closable="false">
          <div class="font-semibold">{{ t("pago.errorTitulo") }}</div>
          <div class="mt-1">{{ error.titulo }}</div>
          <div v-if="error.detalle" class="mt-1 text-sm opacity-90">
            {{ error.detalle }}
          </div>
        </Message>

        <p class="m-0 text-center text-xs text-muted-color">
          <i18n-t keypath="pago.aceptaTerminos" tag="span" scope="global">
            <template #terminos>
              <RouterLink
                :to="{ name: 'politicas', params: { idioma: locale } }"
                target="_blank"
                >{{ t("pago.terminos") }}</RouterLink
              >
            </template>
          </i18n-t>
        </p>

        <!-- Móvil: botón fijo abajo -->
        <div
          class="fixed inset-x-0 bottom-0 z-20 border-t border-surface-200 bg-white/95 px-4 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3 backdrop-blur lg:hidden"
        >
          <Button
            type="submit"
            class="boton-compra"
            size="large"
            fluid
            :loading="pagando"
          >
            <icon name="lock" size="1.1rem" /><span>{{
              pagando
                ? t("pago.pagando")
                : t("pago.pagar", {
                    total: carrito.carrito?.total?.texto ?? "",
                  })
            }}</span>
          </Button>
        </div>
      </FormKit>
    </div>

    <Dialog
      :visible="pagando && !desafio"
      modal
      :closable="false"
      :draggable="false"
      :show-header="false"
      class="w-[min(22rem,calc(100vw-2rem))]"
    >
      <div class="flex flex-col items-center gap-3 pt-6 text-center">
        <ProgressSpinner style="width: 3rem; height: 3rem" stroke-width="4" />
        <p class="m-0 font-semibold">{{ t("pago.pagando") }}</p>
        <p class="m-0 text-sm text-muted-color">{{ t("pago.noCierre") }}</p>
      </div>
    </Dialog>

    <DesafioBanco
      v-if="desafio"
      :url="desafio.url"
      :campos="desafio.campos"
      :ancho="desafio.ancho"
      :alto="desafio.alto"
      @completado="desafio.fin($event)"
      @cancelado="desafio.fin(null)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { onBeforeRouteLeave, useRouter } from "vue-router";
import { getNode } from "@formkit/core";
import * as api from "@/api";
import { useCarrito } from "@/carrito";
import DesafioBanco from "@/componentes/DesafioBanco.vue";
import ResumenCarrito from "@/componentes/ResumenCarrito.vue";
import { mensajeDeError, type MensajeError } from "@/errores";
import { exigeCodigoPostal, expiraValida, luhn, marca, paises } from "@/modelo";
import { cargarHuella, datosNavegador, recolectarDispositivo } from "@/tresDs";
import type {
  Comprador,
  DatosFacturacion,
  DatosTarjeta,
  ResultadoPago,
  SolicitudPago,
} from "@/tipos";
import { useViaje } from "@/viaje";

const { t, te, locale } = useI18n();
const router = useRouter();
const carrito = useCarrito();
const viaje = useViaje();
const datos = ref<Record<string, unknown>>({ nit: "CF", pais: "GT" });
const pagando = ref(false);
const cargando = ref(true);
const vencido = ref(false);
const error = ref<MensajeError | null>(null);
let completado = false;

const opciones = (l: Array<{ id: number; nombre: string }> | undefined) =>
  (l ?? []).map((o) => ({ label: o.nombre, value: o.id }));
const naciones = computed(() => opciones(viaje.catalogos?.naciones));
const documentos = computed(() => opciones(viaje.catalogos?.tiposDocumento));
const listaPaises = computed(() => paises(locale.value));
const usaCodigoPostal = computed(() => exigeCodigoPostal(datos.value.pais));

/** Desafío del banco en curso: `fin` recibe lo que envió el banco (o null si se canceló). */
const desafio = ref<{
  url: string;
  campos: Record<string, string>;
  ancho: string;
  alto: string;
  fin: (d: Record<string, string> | null) => void;
} | null>(null);

const tarjetaValida = (n: { value: unknown }) =>
  luhn(String(n.value ?? "")) && marca(String(n.value ?? "")) !== null;
const vigente = (n: { value: unknown }) => expiraValida(String(n.value ?? ""));
const cvvValido = (n: { value: unknown }) =>
  /^\d{3,4}$/.test(String(n.value ?? "").trim());

function avisarSalida(e: BeforeUnloadEvent) {
  if (pagando.value) e.preventDefault();
}

onMounted(async () => {
  window.addEventListener("beforeunload", avisarSalida);
  await Promise.all([carrito.refrescar(), viaje.cargarCatalogos()]);
  cargando.value = false;
  if (carrito.token && !carrito.vacio) {
    const h = await api.huella(carrito.token).catch(() => null);
    if (h?.script) cargarHuella(h.script);
  }
});
onBeforeUnmount(() => window.removeEventListener("beforeunload", avisarSalida));

// Salir sin pagar suelta los asientos: otros clientes y las taquillas los ven libres de nuevo.
onBeforeRouteLeave(async (to) => {
  if (pagando.value) return false;
  if (!completado && to.name !== "compra") await carrito.vaciar();
  return true;
});

/** Cancela la compra completa: suelta los asientos (al salir) y vuelve al inicio sin nada elegido. */
function cancelarCompra() {
  viaje.cancelar();
  void router.push({ name: "inicio", params: { idioma: locale.value } });
}

function volver() {
  const b = viaje.busqueda;
  const query =
    b.origen && b.destino
      ? {
          o: String(b.origen),
          d: String(b.destino),
          f: b.fecha ?? undefined,
          ...(b.idaVuelta ? { r: b.regreso ?? "" } : {}),
        }
      : {};
  void router.push({
    name: "inicio",
    params: { idioma: locale.value },
    query,
    hash: "#salidas",
  });
}

/** Botón del resumen (fuera del formulario en escritorio). */
function enviar() {
  getNode("pago")?.submit();
}

async function vencio() {
  await carrito.refrescar();
  if (carrito.vacio) vencido.value = true;
}

async function pagar(v: Record<string, unknown>) {
  if (!carrito.token) return;
  pagando.value = true;
  error.value = null;
  const comprador: Comprador = {
    nombre: String(v.nombre ?? ""),
    apellido: v.apellido ? String(v.apellido) : undefined,
    email: String(v.email ?? ""),
    telefono: v.telefono ? String(v.telefono) : undefined,
    nit: String(v.nit ?? "CF"),
    tipoDocumento: (v.tipoDocumento as number | null) ?? null,
    numeroDocumento: v.numeroDocumento ? String(v.numeroDocumento) : undefined,
    nacionalidad: (v.nacionalidad as number | null) ?? null,
  };
  const tarjeta: DatosTarjeta = {
    numero: String(v.numero ?? ""),
    expira: String(v.expira ?? ""),
    cvv: String(v.cvv ?? ""),
    titular: String(v.titular ?? ""),
  };
  const facturacion: DatosFacturacion = {
    pais: String(v.pais ?? "GT"),
    region: v.region ? String(v.region) : undefined,
    ciudad: String(v.ciudad ?? ""),
    direccion: String(v.direccion ?? ""),
    codigoPostal:
      exigeCodigoPostal(v.pais) && v.codigoPostal
        ? String(v.codigoPostal)
        : undefined,
  };
  const token = carrito.token;
  const solicitud: SolicitudPago = {
    comprador,
    tarjeta,
    facturacion,
    navegador: datosNavegador(),
  };
  try {
    let r: ResultadoPago = await api.pagar(token, solicitud);
    // Pasos de 3-D Secure hasta que el banco apruebe (los rechazos llegan como error).
    for (let paso = 0; r.estado !== "completado"; paso++) {
      if (paso > 4) throw new Error(t("pago.sinFin"));
      let continuar: Record<string, string> = {};
      if (r.estado === "dispositivo") {
        await recolectarDispositivo(r.url, r.campos, r.origenes);
      } else {
        const respuesta = await desafiar(r.url, r.campos, r.ancho, r.alto);
        if (respuesta === null) throw new Error(t("pago.cancelado"));
        continuar = respuesta;
      }
      r = await api.pagar(token, { ...solicitud, continuar });
    }
    completado = true;
    carrito.olvidar();
    viaje.limpiar();
    pagando.value = false;
    await router.push({
      name: "compra",
      params: { idioma: locale.value, token },
    });
  } catch (e) {
    error.value =
      e instanceof api.ErrorPublico
        ? mensajeDeError(e, t, te)
        : { titulo: e instanceof Error ? e.message : String(e), detalle: null };
    if (e instanceof api.ErrorPublico && e.codigo === "carrito_vencido") {
      await carrito.refrescar();
      vencido.value = carrito.vacio;
    }
  } finally {
    desafio.value = null;
    pagando.value = false;
  }
}

/** Muestra el desafío del banco y espera su resultado (null si el cliente lo cierra). */
function desafiar(
  url: string,
  campos: Record<string, string>,
  ancho: string,
  alto: string,
) {
  return new Promise<Record<string, string> | null>((resolve) => {
    desafio.value = {
      url,
      campos,
      ancho,
      alto,
      fin: (d) => {
        desafio.value = null;
        resolve(d);
      },
    };
  });
}
</script>

<style scoped>
.velo-enter-active,
.velo-leave-active {
  transition: opacity 0.25s ease;
}
.velo-enter-from,
.velo-leave-to {
  opacity: 0;
}
</style>
