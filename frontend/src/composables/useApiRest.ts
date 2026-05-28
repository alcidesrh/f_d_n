type HttpMethod = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

type RequestConfig = {
  url: string;
  method?: HttpMethod;
  params?: Record<string, any>;
  data?: any;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  timeout?: number;
  responseType?: "json" | "text" | "blob";
  retry?: number;
  retryDelay?: number;
  cacheTTL?: number;
  cacheTags?: string[];
  dedupe?: boolean;
  trace?: boolean;
};

type ApiOptions = {
  baseURL: string;
  getAccessToken?: () => string | null;
  refreshToken?: () => Promise<string | null>;
  onStart?: () => void;
  onEnd?: () => void;
};

export function createApi(options: ApiOptions) {
  const { baseURL, getAccessToken, refreshToken, onStart, onEnd } = options;

  const cache = new Map<string, any>();
  const inflight = new Map<string, Promise<any>>();
  const controllers = new Set<AbortController>();

  let refreshing: Promise<string | null> | null = null;

  const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

  const keyOf = (c: RequestConfig) =>
    `${c.method}-${c.url}-${JSON.stringify(c.params)}-${JSON.stringify(c.data)}`;

  const buildURL = (url: string, params?: any) => {
    const qs = new URLSearchParams(params || {}).toString();
    return baseURL + url + (qs ? `?${qs}` : "");
  };

  function invalidate(tags: string[]) {
    for (const [k, v] of cache.entries()) {
      if (v.tags?.some((t: string) => tags.includes(t))) {
        cache.delete(k);
      }
    }
  }

  async function request<T>(cfg: RequestConfig): Promise<T> {
    const key = keyOf(cfg);

    // cache
    if (cfg.cacheTTL && cache.has(key)) {
      const c = cache.get(key);
      if (c.exp > Date.now()) return c.data;
    }

    // dedupe
    if (cfg.dedupe && inflight.has(key)) return inflight.get(key)!;

    const exec = async (): Promise<T> => {
      onStart?.();

      const controller = new AbortController();
      controllers.add(controller);

      try {
        const headers: any = { ...cfg.headers };
        const token = getAccessToken?.();
        if (token) headers["Authorization"] = `Bearer ${token}`;

        let body: any;
        if (cfg.data instanceof FormData) body = cfg.data;
        else if (cfg.data) {
          headers["Content-Type"] = "application/json";
          body = JSON.stringify(cfg.data);
        }

        const res = await fetch(buildURL(cfg.url, cfg.params), {
          method: cfg.method || "GET",
          headers,
          body,
          signal: cfg.signal || controller.signal,
        });

        if (res.status === 401 && refreshToken) {
          if (!refreshing) refreshing = refreshToken();
          const newToken = await refreshing;
          refreshing = null;
          if (newToken) return exec();
        }

        if (!res.ok) {
          let err: any;
          try {
            err = await res.json();
          } catch {
            err = await res.text();
          }

          if ((cfg.retry ?? 0) > 0) {
            await sleep(cfg.retryDelay ?? 500);
            return request({ ...cfg, retry: (cfg.retry ?? 0) - 1 });
          }

          throw { status: res.status, data: err };
        }

        let data: any = await res.json();

        if (cfg.cacheTTL) {
          cache.set(key, {
            data,
            exp: Date.now() + cfg.cacheTTL,
            tags: cfg.cacheTags || [],
          });
        }

        return data;
      } finally {
        controllers.delete(controller);
        onEnd?.();
      }
    };

    const p = exec();
    if (cfg.dedupe) inflight.set(key, p);

    try {
      return await p;
    } finally {
      inflight.delete(key);
    }
  }

  return {
    request,
    get: (url: string, c?: any) => request({ ...c, url, method: "GET" }),
    post: (url: string, data?: any, c?: any) =>
      request({ ...c, url, method: "POST", data }),
    put: (url: string, data?: any, c?: any) =>
      request({ ...c, url, method: "PUT", data }),
    patch: (url: string, data?: any, c?: any) =>
      request({ ...c, url, method: "PATCH", data }),
    delete: (url: string, c?: any) => request({ ...c, url, method: "DELETE" }),

    invalidate,
    cancelAll: () => controllers.forEach((c) => c.abort()),
  };
}

export let restApi: Ref<ReturnType<typeof createApi>> = ref();

export function useApi() {
  if (!restApi) {
    throw new Error(
      "API no inicializada. Probablemente estás usando useApi() antes del boot.",
    );
  }
  return restApi;
}
export function setApi(api) {
  restApi.value = api;
}
