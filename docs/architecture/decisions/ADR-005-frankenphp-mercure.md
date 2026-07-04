# ADR-005: FrankenPHP + Caddy + Mercure integrado

**Estado:** Aceptada

## Contexto

El sistema requiere capacidades de tiempo real para notificar cambios en la venta de boletos, disponibilidad de asientos y actualizaciones operativas. La arquitectura tradicional separa el servidor web (Nginx/Apache) del servidor de aplicaciones (PHP-FPM) y del hub de eventos en tiempo real (Mercure), lo que añade complejidad operativa.

La elección del servidor web y el manejo de tiempo real son decisiones críticas que afectan el rendimiento, la simplicidad del despliegue y la integración con Symfony.

## Decisión

Se adopta **FrankenPHP** como servidor de aplicación, que corre PHP 8.4 embebido directamente en Caddy. FrankenPHP proporciona:

1. **Worker mode**: el kernel de Symfony permanece en memoria entre peticiones, eliminando el boot repetido.
2. **Mercure integrado**: el hub de Server-Sent Events corre como un módulo de Caddy dentro del mismo proceso, sin necesidad de un servicio separado.
3. **Configuración unificada**: un solo `Caddyfile` maneja rutas HTTP, HTTPS, HTTP/3, proxy inverso y Mercure.
4. **Entornos diferenciados**: `Caddyfile.dev` para desarrollo con hot-reload y Xdebug; `Caddyfile` estándar para producción.

La imagen Docker multi-etapa (`frankenphp_base → frankenphp_dev / frankenphp_prod`) incluye las extensiones necesarias: `pdo_pgsql`, `pgsql`, `pdo_dblib`, `sqlsrv`, `pdo_sqlsrv`, `apcu`, `intl`, `xdebug` (dev), `opcache` (prod).

## Consecuencias

**Positivas:**

- Eliminación de un servicio separado para Mercure (menor complejidad Docker, menos recursos)
- Worker mode mejora significativamente el rendimiento de Symfony (sin cold boots)
- Configuración unificada en Caddy: TLS automático, compresión, headers de seguridad
- Integración directa con `symfony/mercure-bundle` para publicar eventos desde el backend
- El frontend se suscribe a eventos SSE mediante los composables `mercureItem.ts` y `mercureList.ts`

**Negativas:**

- FrankenPHP es relativamente nuevo comparado con Nginx + PHP-FPM; ecosistema más pequeño
- Worker mode requiere cuidado con fugas de memoria (estado global, objetos no liberados)
- La imagen Docker incluye extensiones adicionales (MS ODBC, sqlsrv) que aumentan su tamaño
- En desarrollo, el worker mode con `watch` puede reiniciarse inesperadamente al editar archivos
