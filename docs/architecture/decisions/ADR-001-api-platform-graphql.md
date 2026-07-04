# ADR-001: API Platform + GraphQL como capa primaria de datos

**Estado:** Aceptada

## Contexto

FDN Transportes maneja decenas de entidades de negocio (Boleto, Recorrido, Trayecto, Asiento, Cliente, etc.) con relaciones complejas. El frontend necesita consultar datos de múltiples entidades en una sola pantalla (ej: lista de boletos con datos del cliente, asiento, recorrido y piloto). Usar REST tradicional implicaría múltiples peticiones o endpoints altamente especializados, lo que incrementa la complejidad del mantenimiento y el overhead de red.

Se evaluaron tres opciones: REST puro con API Platform, GraphQL puro con schema manual, y la combinación REST + GraphQL que ofrece API Platform 4.x.

## Decisión

Se adopta **GraphQL como capa primaria de comunicación** entre el frontend y el backend, manteniendo REST como capa secundaria para operaciones específicas (autenticación, uploads, metadatos). API Platform 4.x expone ambos protocolos desde la misma configuración de entidades, eliminando la necesidad de mantener schemas separados.

El frontend usa Apollo Client 4 con una cadena de links personalizada: `mutation → auth → error → loading → removeTypename → http`. La política de fetch por defecto es `no-cache` para garantizar datos frescos en un dominio transaccional como la venta de boletos.

## Consecuencias

**Positivas:**

- Las vistas del frontend consultan exactamente los datos que necesitan, reduciendo el payload de red
- Una sola query puede obtener boleto + cliente + asiento + recorrido, eliminando N+1 HTTP
- API Platform 4.x unifica la definición REST/GraphQL desde atributos de entidad
- Apollo Client 4 proporciona caché normalizada, soporte de subscriptions y tipado fuerte
- El endpoint REST secundario está disponible para operaciones que no encajan bien en GraphQL (login, uploads)

**Negativas:**

- Mayor complejidad en el manejo de errores (errores parciales en GraphQL vs códigos HTTP)
- Curva de aprendizaje para desarrolladores no familiarizados con GraphQL
- Las queries complejas requieren resolvers personalizados para evitar N+1 en la base de datos
- La introspección GraphQL expone el schema completo; requiere medidas de seguridad adicionales en producción
