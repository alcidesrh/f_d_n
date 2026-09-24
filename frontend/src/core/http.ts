/**
 * Cliente REST del backend (API Platform + endpoints propios de Symfony).
 *
 * Envía `Accept: application/ld+json` (sin él API Platform responde 406), JSON
 * en el body, el Bearer de la sesión y registra la petición en la barra de
 * carga. Un 401 cierra la sesión y lleva al login (salvo `skipUnauthorized`,
 * usado por el propio login, donde el 401 significa "credenciales inválidas").
 */
import { config } from "./config";
import { useLoadingStore } from "./loading";
import { handleUnauthorized } from "./auth/unauthorized";
import { useSessionStore } from "./auth/session";

export class HttpError extends Error {
  constructor(
    readonly status: number,
    readonly body: unknown,
    message: string,
  ) {
    super(message);
  }
}

export interface RequestOptions {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  headers?: Record<string, string>;
  /** Clave para `useLoadingStore().isLoading(key)`; por defecto el path. */
  loadingKey?: string;
  skipUnauthorized?: boolean;
}

function parse(text: string): unknown {
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

/** Mensaje legible de un error de API Platform / Symfony. */
function errorMessage(body: unknown, status: number): string {
  if (typeof body === "string" && body) return body;
  const record = (body ?? {}) as Record<string, unknown>;
  const message = record.detail ?? record["hydra:description"] ?? record.message ?? record.error;
  return typeof message === "string" && message ? message : `HTTP ${status}`;
}

export async function request<T = unknown>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = "GET", body, loadingKey = path } = options;
  const token = useSessionStore().token;
  const loading = useLoadingStore();
  loading.start(loadingKey);
  try {
    const response = await fetch(`${config.restUrl}${path}`, {
      method,
      headers: {
        Accept: "application/ld+json",
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    const data = parse(await response.text());
    if (response.status === 401 && !options.skipUnauthorized) handleUnauthorized();
    if (!response.ok) throw new HttpError(response.status, data, errorMessage(data, response.status));
    return data as T;
  } finally {
    loading.stop(loadingKey);
  }
}

type Options = Omit<RequestOptions, "method" | "body">;

export const http = {
  get: <T = unknown>(path: string, options?: Options) => request<T>(path, options),
  post: <T = unknown>(path: string, body?: unknown, options?: Options) =>
    request<T>(path, { ...options, method: "POST", body }),
  patch: <T = unknown>(path: string, body?: unknown, options?: Options) =>
    request<T>(path, {
      ...options,
      method: "PATCH",
      body,
      headers: { "Content-Type": "application/merge-patch+json", ...options?.headers },
    }),
  delete: <T = unknown>(path: string, options?: Options) => request<T>(path, { ...options, method: "DELETE" }),
};
