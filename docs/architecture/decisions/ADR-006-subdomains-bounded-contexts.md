# ADR-006: Subdominios como bounded contexts

**Estado:** Aceptada

## Contexto

El dominio de FDN Transportes abarca múltiples áreas funcionales: transporte (rutas, trayectos), flota (buses, asientos), venta (boletos, facturación), personal (pilotos, usuarios), configuración (tarifas, empresas), infraestructura (estaciones) y seguridad (IAM). Inicialmente, las entidades se organizaban de forma plana en `src/Entity/`, lo que funcionaba para pocas entidades pero se volvía difícil de navegar y mantener a medida que el sistema crecía.

Se necesita una forma de organizar el código que refleje la estructura del dominio, facilite la navegación y permita que diferentes equipos trabajen en áreas separadas sin conflictos.

## Decisión

Se organiza el backend en **subdominios como bounded contexts** siguiendo los principios de Domain-Driven Design (DDD). Cada subdominio representa un área funcional cohesiva:

| Subdominio | Entidades principales |
|---|---|
| Transporte | Trayecto, Recorrido, RecorridoMatrioska |
| Flota | Bus, BusMarca, Asiento, Piloto |
| Venta | Boleto, Venta, Factura, Cliente, Servicio |
| Personal | Usuario, Piloto |
| Configuración | Empresa, Localidad, Nacion, Tarifa, Parada |
| Infraestructura | Estacion, Enclave, Parada |
| Seguridad | Usuario, Role, Permiso, Action, ApiToken |

Cada subdominio agrupa sus entidades, servicios, repositorios y resolutores GraphQL. Las entidades de configuración dinámica (`EntityConfiguration`, `FieldConfig`) se mantienen como un contexto técnico transversal.

## Consecuencias

**Positivas:**

- Navegación más intuitiva del código fuente
- Las entidades relacionadas están físicamente cerca
- Facilita la asignación de ownership a diferentes desarrolladores
- Los bounded contexts exponen interfaces claras (servicios, repositorios) y ocultan implementaciones
- Preparación para futura separación en microservicios si fuera necesario

**Negativas:**

- Las entidades con relaciones entre contextos (ej: Boleto depende de Recorrido y Cliente) requieren referencias cruzadas
- No hay un límite físico estricto (misma base de datos, mismo código); es una convención organizativa
- Algunas entidades son difíciles de clasificar en un solo subdominio (ej: Usuario aparece en Seguridad y Personal)
- La documentación de subdominios debe mantenerse sincronizada con la estructura real
