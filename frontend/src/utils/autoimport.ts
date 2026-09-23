import { useToasts } from "@/composables/useToasts";

interface ToastOptions {
  severity?: "success" | "info" | "warn" | "error";
  summary?: string;
  detail?: string;
  life?: number;
  [key: string]: any;
}

/**
 * Dispara un Toast global desde CUALQUIER archivo (.vue, .js, .ts).
 *
 * Usa el sistema de toasts custom del proyecto (`useToasts`), que
 * funciona tanto dentro como fuera del contexto de setup de componentes.
 *
 * El push se difiere a un macrotask: si `msg` se invoca durante un
 * render que termina en error (ej. un setup que lanza una excepción), Vue
 * aborta ese ciclo de flush y el re-render pendiente de `<Toasts />` queda
 * descartado. Al diferir, el toast se renderiza en un flush limpio y aislado.
 */
export const msg = (options: ToastOptions) => {
  setTimeout(() => {
    const toasts = useToasts();
    const text = [options.summary, options.detail].filter(Boolean).join(": ");
    const duration = options.life && options.life > 0 ? options.life : undefined;

    switch (options.severity) {
      case "error":
        toasts.error(text);
        break;
      case "warn":
        toasts.warning(text, { duration });
        break;
      case "success":
        toasts.success(text, { duration });
        break;
      default:
        toasts.info(text, { duration });
    }
  }, 0);
};
