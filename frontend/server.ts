// Sirve el build (dist/) en el puerto 9000. Las rutas que no son un archivo
// devuelven index.html para que el router de la SPA resuelva la URL.
const dist = `${import.meta.dir}/dist`;

Bun.serve({
  port: Number(process.env.PORT ?? 9000),
  hostname: process.env.HOSTNAME ?? "0.0.0.0",
  async fetch(req) {
    const { pathname } = new URL(req.url);
    const path = `${dist}${pathname}`;

    if (!path.startsWith(`${dist}/`)) return new Response("Not found", { status: 404 });

    const archivo = Bun.file(path);
    if (pathname !== "/" && (await archivo.exists())) return new Response(archivo);

    return new Response(Bun.file(`${dist}/index.html`), {
      headers: { "Content-Type": "text/html; charset=utf-8" },
    });
  },
});
