import ToastEventBus from "primevue/toasteventbus";

interface ToastOptions {
  severity?: "success" | "info" | "warn" | "error";
  summary?: string;
  detail?: string;
  life?: number;
  [key: string]: any;
}

/**
 * Dispara un Toast global desde CUALQUIER archivo (.vue, .js, .ts)
 */
export const triggerToast = (options: ToastOptions) => {
  // PrimeVue escucha internamente el evento 'add' para pintar el componente
  ToastEventBus.emit("add", options);
};
