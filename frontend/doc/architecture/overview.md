# Arquitectura del Frontend

El frontend está construido sobre **Vue 3** y **Quasar Framework**, operando como una Single Page Application (SPA).

## Filosofía Principal

El código base se estructura priorizando la escalabilidad y la fácil localización del código. En lugar de un enfoque técnico estricto (ej. todo en `components/`, todo en `views/`), la estructura tiende a dividirse por **subdominios lógicos** (ej. Ventas, Flota, Configuración).

## Boot Order (Secuencia de Inicio)

La aplicación inicializa sus capacidades en una secuencia estricta controlada por Quasar Boot files. Esta secuencia es crucial para garantizar que cada capa dependa de la anterior correctamente:

1. **`unocss`**: Inicialización del motor atómico para estilos e inyección en el DOM.
2. **`api-rest`**: Configuración básica del cliente HTTP / Axios si se requiere REST.
3. **`apollo`**: Instanciación del cliente GraphQL, enlazado con los URIs correctos (dev/prod).
4. **`introspection`**: Capa crítica. Antes de arrancar la UI principal, se realiza la consulta de introspección al backend para descargar los metadatos de las entidades.
5. **`middleware`**: Interceptores de enrutamiento (Auth guard, guards por roles).
6. **`i18n`**: Configuración de idiomas y carga de traducciones.
7. **`gsap`**: Inicialización de animaciones globales.

## Introspección y CRUD Dinámico

El Frontend está diseñado para evitar escribir pantallas repetitivas ("boilerplate") para mantenimientos. Utilizando la introspección GraphQL proporcionada por el servidor, los componentes genéricos (`DynamicTable`, `DynamicForm`) pueden inferir:

- Tipos de campo (Texto, Fecha, Selectores de relaciones).
- Validaciones requeridas.
- Nombres y etiquetas.

De este modo, añadir una nueva entidad al sistema con operaciones CRUD a menudo solo requiere exponerla en el Backend, sin tener que programar la vista completa en el Frontend.

[Ver estructura de directorios](./directories.md)
