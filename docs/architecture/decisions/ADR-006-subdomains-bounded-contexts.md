# ADR-006: Subdominios como bounded contexts

**Estado:** Aceptada

## Contexto

El dominio de FDN Transportes abarca múltiples áreas funcionales: transporte (rutas, trayectos), flota (buses, asientos), venta (boletos, facturación), personal (pilotos, usuarios), configuración (tarifas, empresas), infraestructura (estaciones) y seguridad (IAM). Inicialmente, las entidades se organizaban de forma plana en `src/Entity/`, lo que funcionaba para pocas entidades pero se volvía difícil de navegar y mantener a medida que el sistema crecía.

Se necesita una forma de organizar el código que refleje la estructura del dominio, facilite la navegación y permita que diferentes equipos trabajen en áreas separadas sin conflictos.

## Decisión

Se organiza el backend en **subdominios como bounded contexts** siguiendo los principios de Domain-Driven Design (DDD). Cada subdominio representa un área funcional cohesiva:

| Subdominio | Entidades principales |
|---|---|
| Transporte | Trayecto, Subtrayecto, Servicio |
| Flota | Bus, BusMarca, Asiento |
| Venta | BoletoAsiento, BoletoVenta, BoletoTarifa, Factura, Cliente |
| Personal | Usuario, Piloto |
| Configuración | Empresa, Localidad, Nacion, Status |
| Infraestructura | Estacion, Enclave |
| Seguridad | Usuario, Role, Permiso, Action, ApiToken |

Cada subdominio agrupa sus entidades, servicios, repositorios y resolutores GraphQL. Las entidades de configuración dinámica (`EntityConfiguration`, `FieldConfig`) se mantienen como un contexto técnico transversal.

> **Nota (2026):** `Agencia`, `Voucher`, `Encomienda`, `Reasignación`, `Anulación` y `Manifiesto` son conceptos vigentes del glosario de negocio (ver `CONTEXT.md` / `AGENTS.md`) que hoy **solo existen en el sistema legado** (`src/EntitySistemaFdn/`). Todavía no tienen entidad ni servicio equivalente en el modelo nuevo (`src/Entity/`) — son el trabajo de dominio pendiente más importante, no un subdominio ya resuelto.

## Consecuencias

**Positivas:**

- Navegación más intuitiva del código fuente
- Las entidades relacionadas están físicamente cerca
- Facilita la asignación de ownership a diferentes desarrolladores
- Los bounded contexts exponen interfaces claras (servicios, repositorios) y ocultan implementaciones
- Preparación para futura separación en microservicios si fuera necesario

**Negativas:**

- Las entidades con relaciones entre contextos (ej: BoletoAsiento depende de Servicio y Cliente) requieren referencias cruzadas
- No hay un límite físico estricto (misma base de datos, mismo código); es una convención organizativa
- Algunas entidades son difíciles de clasificar en un solo subdominio (ej: Usuario aparece en Seguridad y Personal)
- La documentación de subdominios debe mantenerse sincronizada con la estructura real
