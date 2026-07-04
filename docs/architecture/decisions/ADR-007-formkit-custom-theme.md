# ADR-007: FormKit con tema personalizado + JSON schemas

**Estado:** Aceptada

## Contexto

El sistema FDN Transportes requiere formularios dinámicos para la creación y edición de entidades. El sistema de CRUD dinámico (ADR-004) necesita una biblioteca de formularios que:

- Genere formularios a partir de metadatos/configuración
- Soporte validación, internacionalización y personalización visual
- Se integre con Vue 3 y el sistema de componentes Quasar
- Permita inputs personalizados para tipos de datos específicos (selectores de entidades relacionados, Money, etc.)

Se evaluaron VeeValidate + custom components, Quasar Forms nativos y FormKit. FormKit destacó por su capacidad de definir formularios mediante esquemas (JSON), su tema extensible y su soporte de validación declarativa.

## Decisión

Se adopta **FormKit 2.x** como biblioteca de formularios con un tema personalizado llamado **formkit-theme-fdn**. La configuración incluye:

1. **Tema personalizado**: definido en `src/form/formkit.theme.ts` con estilos consistentes con la identidad visual de FDN.
2. **Inputs personalizados**: en `src/form/inputs/` para tipos de datos específicos del dominio (selectores de entidad, campos de precio, selectores de fecha/hora).
3. **Generación dinámica**: los formularios del CRUD dinámico construyen esquemas FormKit desde los metadatos de `EntityConfiguration`, permitiendo crear/editar cualquier entidad sin código específico.
4. **Validación**: reglas de validación configuradas por campo en los metadatos del backend.

El tema personalizado usa un sistema de tokens de diseño (colores, espaciados, tipografía) que se integra con UnoCSS y las variables CSS del frontend.

## Consecuencias

**Positivas:**

- Generación automática de formularios desde metadatos: cero código para entidades nuevas
- El tema personalizado asegura consistencia visual en todos los formularios
- Los inputs personalizados encapsulan lógica de negocio compleja (búsqueda de entidades relacionadas)
- FormKit maneja validación, errores y estados de carga de forma declarativa
- Los esquemas JSON permiten serializar la definición del formulario para personalización por rol/usuario

**Negativas:**

- Dependencia externa de FormKit 2.x, cuyo ecosistema es menos maduro que alternativas nativas de Vue
- La personalización del tema requiere conocimiento interno de la API de ellos de FormKit
- FormKit añade ~30KB al bundle del frontend
- Algunas características de Quasar (notificaciones, diálogos) deben integrarse manualmente con FormKit
- Los formularios generados dinámicamente son más difíciles de depurar que formularios escritos a mano
