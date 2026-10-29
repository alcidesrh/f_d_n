// Sirve el build (dist/) en el puerto 9000. Las rutas que no son un archivo
// devuelven index.html para que el router de la SPA resuelva la URL (salvo los que parecen archivo).
const dist = `${import.meta.dir}/dist`;

// Lo de assets/ lleva el hash del contenido en el nombre: no cambia nunca. Fuentes e
// imágenes se guardan un día. index.html, el service worker, el manifiesto y el schema
// se piden de nuevo siempre: son los que dicen qué versión está publicada.
function cacheControl(pathname: string): string {
  if (pathname.startsWith("/assets/")) return "public, max-age=31536000, immutable";
  if (/^\/(fonts|images|pwa)\//.test(pathname) || pathname === "/favicon.ico") return "public, max-age=86400";
  return "no-cache";
}

Bun.serve({
  port: Number(process.env.PORT ?? 9000),
  hostname: process.env.HOSTNAME ?? "0.0.0.0",
  async fetch(req) {
    const { pathname } = new URL(req.url);
    const path = `${dist}${pathname}`;

    if (!path.startsWith(`${dist}/`)) return new Response("Not found", { status: 404 });

    const archivo = Bun.file(path);
    if (pathname !== "/" && (await archivo.exists())) {
      return new Response(archivo, { headers: { "Cache-Control": cacheControl(pathname) } });
    }

    // Un archivo que no existe (un chunk de una versión anterior) no es una ruta de la SPA:
    // devolver index.html ahí rompe el módulo en el navegador.
    if (/\.[a-z0-9]+$/i.test(pathname)) return new Response("Not found", { status: 404 });

    return new Response(Bun.file(`${dist}/index.html`), {
      headers: { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-cache" },
    });
  },
});
