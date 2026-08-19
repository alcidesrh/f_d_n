Actúa como un Desarrollador Frontend Senior experto en Vue 3, FormKit, Pinia y TypeScript.

Contexto del proyecto:
- Stack: Vue 3 (Script Setup), Vite, PrimeVue (UI), Pinia (Estado) y FormKit.
- Flujo actual: La aplicación parsea un archivo de introspección GraphQL y extrae los metadatos de las entidades de la API. Esta información estructurada (campos, tipos, nulabilidad) ya se procesa y se almacena en una Store de Pinia con un formato personalizado propio del cliente.

Objetivo:
Necesito implementar una funcionalidad puramente en el Frontend (dentro de la Store de Pinia o como un composable/utilidad de TypeScript) que serialice A DEMANDA estos objetos de metadatos almacenados en Pinia y los transforme en un JSON completamente válido y compatible con la especificación de FormKit Schema (https://formkit.com/essentials/schema).

Reglas de mapeo que debes implementar:
1. Formato FormKit: Cada campo debe convertirse en un nodo de esquema de FormKit usando la sintaxis de componente: { "$cmp": "FormKit", "props": { "name": "...", "type": "...", "label": "..." } }.
2. Tipos de datos:
   - String -> type: 'text' (si el nombre contiene 'email', usar type: 'email'; si contiene 'password', usar type: 'password').
   - Int / Float / Float! -> type: 'number'.
   - Boolean / Boolean! -> type: 'checkbox' o 'toggle' (usando componentes de PrimeVue mapeados en FormKit si es posible, o nativos).
   - Enum -> type: 'select' (debe mapear las opciones del enum al array 'options' de FormKit).
3. Validaciones: Si el metadato indica que el campo en GraphQL era NON_NULL (ej. String!), añade automáticamente en las props: "validation": "required".

Requerimientos de la salida:
1. Define las Interfaces de TypeScript tanto para mi "Objeto de metadatos personalizado de Pinia" como para la salida esperada de "FormKit Schema".
2. Escribe una función pura de TypeScript (`serializeMetadataToFormKitSchema`) o extiende la Store de Pinia con una acción/getter que realice esta transformación de manera eficiente.
3. Proporciona un ejemplo breve de un componente de Vue 3 que consuma este esquema generado dinámicamente usando el componente `<FormKitSchema :schema="miEsquemaGenerado" />`.

Entrega código limpio, modular, sin explicaciones redundantes y listo para producción.
