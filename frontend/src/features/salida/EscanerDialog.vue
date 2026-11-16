<!--
  Chequeo por escaneo (ADR-063): la cámara del teléfono (o un lector USB, que
  escribe en el campo y termina en Enter) lee el QR de un boleto o el código
  de barras de uno impreso y lo chequea como presente. Cada lectura responde
  sola, en verde o en rojo, y la cámara sigue leyendo.
  La cámara usa `BarcodeDetector` (Chrome y el WebView de Android); donde no
  existe queda el campo de texto.
-->
<template>
  <Dialog :visible="true" modal header="Escanear boletos" class="w-[min(28rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div class="flex flex-col gap-3">
      <div v-if="camara !== 'sin-soporte'" class="relative aspect-square overflow-hidden rounded-lg bg-black">
        <video ref="video" class="size-full object-cover" playsinline muted />
        <p v-if="camara !== 'activa'" class="absolute inset-0 m-0 flex items-center justify-center p-4 text-center text-sm text-white">
          {{ camara === "pidiendo" ? "Abriendo la cámara…" : "No se pudo usar la cámara. Revise el permiso o escriba el código." }}
        </p>
      </div>
      <Message v-else severity="info" :closable="false">Este navegador no lee códigos con la cámara. Use un lector o escriba el código.</Message>

      <form class="flex items-end gap-2" @submit.prevent="enviarEscrito">
        <label class="flex min-w-0 flex-1 flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Código del boleto</span>
          <InputText ref="campo" v-model="escrito" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Lector o teclado" fluid />
        </label>
        <Button type="submit" label="Chequear" :disabled="!escrito.trim()" :loading="enviando" />
      </form>

      <div v-if="resultado" class="rounded-lg border p-3" :class="resultado.ok ? 'border-green-300 bg-green-50 text-green-900' : 'border-red-300 bg-red-50 text-red-900'" role="status" aria-live="assertive">
        <div class="flex items-center gap-2 font-semibold">
          <icon :name="resultado.ok ? 'check-circle-outline' : 'error-outline'" />
          {{ resultado.ok ? "Presente" : "No se chequeó" }}
        </div>
        <div class="mt-1 whitespace-pre-line text-sm">{{ resultado.texto }}</div>
      </div>

      <p class="m-0 text-center text-sm text-muted-color">
        <strong class="tabular-nums">{{ chequeados }}</strong> chequeado(s) en esta sesión
      </p>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { escanearBoleto } from "@/core/abordaje/api";
import { debeEnviar, FORMATOS, limpiar, resumen, type Lectura } from "@/core/abordaje/escaneo";
import { HttpError } from "@/core/http";
import { nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef } from "vue";

/** Lo que se usa del detector del navegador (no está en los tipos de TypeScript). */
interface Detector {
  detect(fuente: HTMLVideoElement): Promise<{ rawValue: string }[]>;
}
type DetectorCtor = new (opciones: { formats: readonly string[] }) => Detector;

const props = defineProps<{ salidaId: number }>();
const emit = defineEmits<{ cerrar: []; chequeado: [] }>();

const video = useTemplateRef<HTMLVideoElement>("video");
const campo = useTemplateRef<{ $el: HTMLInputElement }>("campo");
const camara = ref<"pidiendo" | "activa" | "error" | "sin-soporte">("pidiendo");
const escrito = ref("");
const enviando = ref(false);
const resultado = ref<{ ok: boolean; texto: string } | null>(null);
const chequeados = ref(0);

let flujo: MediaStream | null = null;
let temporizador: ReturnType<typeof setInterval> | null = null;
let ultima: Lectura | null = null;

async function enviar(texto: string) {
  const codigo = limpiar(texto);
  const ahora = Date.now();
  if (enviando.value || !debeEnviar(codigo, ultima, ahora)) return;
  ultima = { codigo, momento: ahora };
  enviando.value = true;
  try {
    const r = await escanearBoleto(props.salidaId, codigo);
    chequeados.value += r.chequeados;
    resultado.value = { ok: true, texto: resumen(r.boletos) };
    navigator.vibrate?.(80);
    emit("chequeado");
  } catch (e) {
    const sinPermiso = e instanceof HttpError && e.status === 403 && !(e.body as { error?: string } | null)?.error;
    resultado.value = { ok: false, texto: sinPermiso ? "No tiene permiso para chequear boletos." : e instanceof Error ? e.message : String(e) };
    navigator.vibrate?.([120, 80, 120]);
  } finally {
    enviando.value = false;
  }
}

async function enviarEscrito() {
  const texto = escrito.value;
  escrito.value = "";
  // Lo escrito a mano se envía aunque repita la lectura anterior.
  ultima = null;
  await enviar(texto);
  campo.value?.$el.focus();
}

async function abrirCamara() {
  const Ctor = (window as unknown as { BarcodeDetector?: DetectorCtor }).BarcodeDetector;
  if (!Ctor || !navigator.mediaDevices?.getUserMedia) {
    camara.value = "sin-soporte";
    return;
  }
  try {
    flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
    await nextTick();
    const v = video.value;
    if (!v) return;
    v.srcObject = flujo;
    await v.play();
    camara.value = "activa";
    const detector = new Ctor({ formats: FORMATOS });
    temporizador = setInterval(async () => {
      if (enviando.value || v.readyState < 2) return;
      try {
        const [codigo] = await detector.detect(v);
        if (codigo?.rawValue) void enviar(codigo.rawValue);
      } catch {
        // Un cuadro que no se pudo leer: se intenta con el siguiente.
      }
    }, 300);
  } catch {
    camara.value = "error";
  }
}

onMounted(() => {
  void abrirCamara();
  void nextTick(() => campo.value?.$el.focus());
});

onBeforeUnmount(() => {
  if (temporizador) clearInterval(temporizador);
  flujo?.getTracks().forEach((t) => t.stop());
});
</script>
