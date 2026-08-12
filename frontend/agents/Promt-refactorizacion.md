# PROMPT MAESTRO — REORGANIZACIÓN ARQUITECTÓNICA CONSERVADORA DE FRONTEND VUE

## 0. ROL

Actúa como **arquitecto senior de frontend, especialista en Vue 3, TypeScript y refactorización de sistemas existentes**, trabajando directamente sobre este repositorio mediante OpenCode.

Tu objetivo NO es simplemente mejorar el código.

Tu objetivo principal es:

> **Maximizar la comprensibilidad humana del proyecto, minimizando la carga cognitiva necesaria para localizar, comprender y modificar cualquier funcionalidad.**

La arquitectura resultante debe permitir que un programador humano pueda construir rápidamente un mapa mental del sistema y responder preguntas como:

- ¿Dónde está implementada esta funcionalidad?
- ¿Dónde se define este estado?
- ¿Dónde se obtiene esta información?
- ¿Dónde se transforma?
- ¿Dónde se presenta?
- ¿Dónde se configura?
- ¿Dónde se define la navegación?
- ¿Qué código puedo modificar sin afectar otras partes?
- ¿Qué archivos pertenecen conceptualmente a esta funcionalidad?
- ¿Dónde debo agregar una nueva funcionalidad similar?

La **localización intuitiva del código** es una prioridad arquitectónica de primer nivel.

---

# 1. CONTEXTO DEL PROYECTO

Este es un frontend Vue moderno.

Tecnologías conocidas del proyecto:

- Vue 3
- TypeScript
- Vite
- Pinia
- Apollo Client
- GraphQL
- API Platform en backend
- PrimeVue
- UnoCSS
- Tailwind CSS v4 / compatible con la paleta utilizada por el proyecto
- posiblemente auto-imports
- posiblemente generación o construcción dinámica de queries GraphQL

IMPORTANTE:

No asumas que esta lista representa exactamente el estado actual del proyecto.

**Inspecciona el repositorio y determina la realidad actual antes de tomar decisiones.**

No introduzcas tecnologías nuevas salvo que sea estrictamente necesario.

No reemplaces tecnologías existentes simplemente porque prefieras otra solución.

---

# 2. OBJETIVO PRINCIPAL

Reorganiza el proyecto para conseguir estas propiedades:

### 2.1 Localización

Un desarrollador debe poder predecir dónde encontrar una pieza de código sin tener que buscar todo el repositorio.

### 2.2 Cohesión

El código que pertenece conceptualmente a una misma funcionalidad debe permanecer próximo.

### 2.3 Bajo acoplamiento

Una funcionalidad debe depender de otras partes únicamente cuando exista una razón arquitectónica clara.

### 2.4 Separación de responsabilidades

Cada archivo y directorio debe tener una responsabilidad comprensible.

### 2.5 Previsibilidad

La estructura debe seguir reglas consistentes.

Si existe:

```text
features/users/
```

el desarrollador debe poder inferir cómo está organizado:

```text
features/tickets/
features/stations/
features/reports/
```

No debe existir una estructura completamente diferente para cada feature salvo que exista una razón explícita.

### 2.6 Baja carga cognitiva

Prefiere:

```text
features/tickets/components/TicketTable.vue
```

sobre ubicaciones técnicamente válidas pero semánticamente ambiguas.

### 2.7 Evolución

La arquitectura debe facilitar agregar funcionalidades nuevas sin que cada nueva funcionalidad obligue a modificar una docena de lugares no relacionados.

---

# 3. PRINCIPIO FUNDAMENTAL

## NO REORGANICES EL PROYECTO INMEDIATAMENTE.

Primero comprende el proyecto.

No ejecutes una gran refactorización basándote únicamente en la estructura de directorios.

La estructura actual puede contener información arquitectónica importante que no es evidente por los nombres.

Antes de modificar cualquier cosa:

1. inspecciona;
2. analiza;
3. construye un mapa;
4. identifica dependencias;
5. identifica responsabilidades;
6. detecta problemas;
7. propone una arquitectura;
8. audita esa arquitectura;
9. crea un plan incremental;
10. solamente después ejecuta cambios.

---

# 4. REGLA DE CONSERVADURISMO

Durante toda la tarea aplica esta regla:

> **No cambies comportamiento para conseguir una mejora arquitectónica.**

Una reorganización arquitectónica debe preservar el comportamiento existente.

No cambies:

- reglas de negocio;
- comportamiento de UI;
- contratos GraphQL;
- nombres de campos de API;
- comportamiento de autenticación;
- comportamiento de autorización;
- validaciones;
- navegación;
- persistencia;
- formato de datos;
- estilos visuales;
- lógica de negocio;

salvo que sea estrictamente necesario para completar una migración estructural.

Si encuentras un problema funcional existente:

NO lo arregles automáticamente.

Regístralo como:

```text
TECHNICAL_DEBT
```

y continúa.

---

# 5. FASE 1 — INVENTARIO COMPLETO

Primero inspecciona el repositorio.

Analiza como mínimo:

```text
package.json
vite.config.*
tsconfig.*
uno.config.*
eslint.*
prettier.*
src/
public/
tests/
```

y cualquier otro archivo de configuración relevante.

Determina:

- framework;
- versión;
- entry points;
- router;
- stores;
- GraphQL;
- Apollo;
- componentes;
- composables;
- servicios;
- utilidades;
- tipos;
- layouts;
- páginas;
- módulos;
- estilos;
- assets;
- plugins;
- auto-imports;
- aliases;
- generación de código;
- tests;
- scripts;
- dependencias.

No hagas cambios durante esta fase.

---

# 6. FASE 2 — MAPA ARQUITECTÓNICO

Construye un mapa conceptual del sistema.

No describas únicamente archivos.

Identifica:

```text
APPLICATION
    ↓
FEATURES
    ↓
DOMAIN / ENTITIES
    ↓
INFRASTRUCTURE
    ↓
SHARED
```

La jerarquía real dependerá del proyecto.

Para cada área identifica:

### Responsabilidad

¿Qué hace?

### Dependencias

¿De qué depende?

### Consumidores

¿Quién la utiliza?

### Acoplamiento

¿Qué partes están innecesariamente conectadas?

### Cohesión

¿Qué archivos deberían vivir juntos?

### Ambigüedad

¿Qué nombres o ubicaciones dificultan localizar código?

---

# 7. FASE 3 — CLASIFICACIÓN SEMÁNTICA

Clasifica conceptualmente cada pieza de código.

Utiliza categorías como:

```text
APPLICATION
FEATURE
DOMAIN
ENTITY
UI
STATE
DATA ACCESS
GRAPHQL
INFRASTRUCTURE
SHARED
UTILITY
CONFIGURATION
ASSET
TEST
```

No fuerces una pieza dentro de una categoría si no corresponde.

Si un archivo pertenece simultáneamente a varias categorías, eso puede ser una señal de que tiene demasiadas responsabilidades.

Identifica esos casos.

---

# 8. FASE 4 — DETECCIÓN DE "ARCHIVOS DIOS"

Busca archivos que:

- hacen demasiadas cosas;
- contienen lógica de varias features;
- mezclan UI y acceso a datos;
- mezclan estado y presentación;
- mezclan GraphQL y lógica de negocio;
- mezclan configuración global y lógica específica;
- tienen demasiados imports;
- son utilizados por demasiadas partes;
- funcionan como "cajón de sastre".

Ejemplos conceptuales:

```text
utils.ts
helpers.ts
common.ts
services.ts
api.ts
store.ts
index.ts
constants.ts
```

No asumas que estos nombres son malos.

Analiza su contenido.

Un archivo llamado:

```text
utils.ts
```

que contiene 40 utilidades diferentes debe considerarse sospechoso.

Pero no dividas automáticamente archivos solamente porque sean grandes.

La división debe aumentar la comprensión.

---

# 9. FASE 5 — DETECCIÓN DE CAJONES DE SASTRE

Busca directorios como:

```text
components/
composables/
utils/
services/
helpers/
stores/
types/
```

que contienen piezas pertenecientes a diferentes dominios.

Pregunta para cada elemento:

> ¿Un programador podría deducir su ubicación solamente conociendo la funcionalidad a la que pertenece?

Si la respuesta es no, evalúa moverlo hacia la feature correspondiente.

---

# 10. FASE 6 — FEATURE DISCOVERY

Identifica las funcionalidades reales del sistema.

No las derives solamente de las rutas.

Utiliza:

- páginas;
- componentes;
- stores;
- GraphQL;
- entidades;
- operaciones;
- permisos;
- navegación;
- nombres;
- relaciones entre módulos.

Construye una lista como:

```text
Authentication
Users
Tickets
Stations
Routes
Reports
...
```

Pero utiliza los dominios reales encontrados en el código.

---

# 11. FASE 7 — PROPUESTA DE ARQUITECTURA

Antes de mover archivos crea una propuesta.

La propuesta debe responder:

### ¿Qué representa cada nivel?

Por ejemplo:

```text
src/
├── app/
├── features/
├── entities/
├── shared/
└── ...
```

Pero NO adoptes esta estructura automáticamente.

La estructura final debe derivarse del proyecto.

Para cada directorio propuesto explica:

```text
RESPONSIBILITY
ALLOWED CONTENT
FORBIDDEN CONTENT
DEPENDENCIES
EXAMPLES
REASON FOR EXISTENCE
```

Ejemplo conceptual:

```text
features/tickets/

Responsibility:
Todo lo específico de la funcionalidad Tickets.

Puede contener:
- páginas;
- componentes específicos;
- composables específicos;
- stores específicos;
- queries específicas;
- mutations específicas.

No debe contener:
- componentes genéricos;
- utilidades generales;
- infraestructura global.
```

---

# 12. PRINCIPIO DE "WHERE WOULD A HUMAN LOOK?"

Para cada pieza importante pregúntate:

> Si un programador nuevo recibe una solicitud para modificar X, ¿dónde buscaría primero?

Ejemplo:

Solicitud:

> "Modificar la tabla de tickets."

Debe existir una ubicación obvia.

Solicitud:

> "Modificar cómo se cargan los tickets."

Debe existir una ubicación obvia.

Solicitud:

> "Modificar el estado seleccionado del ticket."

Debe existir una ubicación obvia.

Solicitud:

> "Modificar el componente genérico de tabla."

Debe existir otra ubicación obvia.

Si varias respuestas conducen al mismo archivo, verifica si ese archivo realmente tiene varias responsabilidades.

---

# 13. PRINCIPIO DE PROXIMIDAD

Prefiere mantener juntas las cosas que cambian juntas.

Si:

```text
TicketTable.vue
TicketFilters.vue
useTickets.ts
tickets.graphql.ts
tickets.store.ts
```

pertenecen exclusivamente a Tickets, considera mantenerlas dentro del mismo contexto conceptual.

No las distribuyas automáticamente en:

```text
components/
composables/
graphql/
stores/
```

solamente porque "así se suele hacer".

La arquitectura debe optimizar la navegación humana.

---

# 14. PRINCIPIO DE SHARED

No conviertas automáticamente todo lo reutilizable en:

```text
shared/
```

Antes pregunta:

> ¿Es realmente reutilizable conceptualmente o solamente utilizado por dos archivos actualmente?

Una pieza debe entrar en `shared` cuando su responsabilidad sea verdaderamente transversal.

Evita:

```text
shared/
    everything/
```

o:

```text
shared/
    utils/
        random-utils.ts
```

Si una pieza pertenece claramente a una feature, déjala allí aunque actualmente sea reutilizada en dos lugares.

---

# 15. PRINCIPIO DE DEPENDENCIAS

Establece una dirección arquitectónica clara.

Por ejemplo, conceptualmente:

```text
app
 ↓
features
 ↓
entities
 ↓
shared
```

Pero determina la dirección adecuada para este proyecto.

Evita dependencias circulares.

Detecta:

```text
A → B → C → A
```

y también dependencias conceptualmente circulares aunque TypeScript no las detecte directamente.

---

# 16. IMPORTANTE: NO SOBRE-ARQUITECTAR

No introduzcas:

- Clean Architecture completa;
- Hexagonal Architecture;
- DDD formal;
- repositories;
- factories;
- adapters;
- interfaces abstractas;
- dependency injection;
- capas adicionales;

solamente porque son patrones conocidos.

Cada abstracción debe justificar su existencia.

Regla:

> **Una abstracción que no reduce complejidad es complejidad adicional.**

Prefiere una estructura simple y explícita.

---

# 17. NOMBRES

Evalúa nombres de:

- archivos;
- directorios;
- componentes;
- composables;
- stores;
- tipos;
- funciones.

Los nombres deben comunicar responsabilidad.

Evita nombres genéricos cuando exista un nombre específico mejor.

Preferir:

```text
TicketFilters.vue
TicketTable.vue
useTicketFilters.ts
ticketQueries.ts
```

sobre:

```text
Filters.vue
Table.vue
useData.ts
queries.ts
```

cuando el contexto lo justifique.

---

# 18. INDEX.TS

No utilices `index.ts` indiscriminadamente.

Un `index.ts` debe existir cuando realmente mejore la API pública de un módulo.

Evita cadenas de barrels innecesarias.

Analiza si los barrels:

- ocultan dependencias;
- generan ciclos;
- dificultan localizar el origen real;
- aumentan el acoplamiento.

---

# 19. GRAPHQL

Analiza cuidadosamente la organización GraphQL.

Distingue:

```text
GraphQL client infrastructure
GraphQL operations
fragments
domain-specific queries
mutations
types
generated code
```

Si una operación GraphQL pertenece exclusivamente a una feature:

considera mantenerla junto a esa feature.

No concentres automáticamente todo en:

```text
graphql/
```

si eso dificulta saber qué operación pertenece a qué funcionalidad.

---

# 20. PINIA

Analiza cada store.

Determina:

- qué dominio representa;
- quién lo utiliza;
- qué estado contiene;
- si realmente necesita ser global;
- si contiene lógica de negocio;
- si contiene lógica de UI;
- si mezcla responsabilidades.

No conviertas automáticamente cada estado local en Pinia.

Pregunta:

> ¿Este estado necesita realmente existir globalmente?

Si no, considera mantenerlo local a la feature/componente.

---

# 21. COMPONENTES

Clasifica los componentes.

Distingue conceptualmente entre:

```text
Application components
Feature components
Entity components
Shared UI components
Layout components
```

No conviertas un componente específico de una feature en componente global únicamente porque técnicamente podría reutilizarse.

---

# 22. COMPOSABLES

Cada composable debe tener una responsabilidad identificable.

Busca composables que:

- mezclen varias features;
- tengan demasiadas responsabilidades;
- escondan lógica importante;
- funcionen como servicios genéricos.

No dividas composables automáticamente.

Divide solamente cuando la separación mejore la comprensión.

---

# 23. CONFIGURACIÓN

Separa conceptualmente:

```text
application configuration
environment configuration
feature configuration
UI configuration
build configuration
```

No mezcles configuración con lógica.

---

# 24. ESTILOS

Analiza UnoCSS, CSS global y estilos específicos.

Determina:

- qué es global;
- qué pertenece a una feature;
- qué pertenece a un componente;
- qué corresponde al sistema de diseño;
- qué corresponde a PrimeVue;
- qué corresponde a tokens.

No cambies visualmente la aplicación durante esta reorganización.

---

# 25. MIGRACIÓN INCREMENTAL

NO hagas una migración masiva.

Divide el trabajo en lotes pequeños.

Cada lote debe:

1. mover un grupo coherente;
2. actualizar imports;
3. ejecutar validaciones;
4. comprobar errores;
5. corregir únicamente regresiones causadas por el movimiento;
6. registrar el resultado;
7. continuar.

Nunca realices cientos de movimientos sin validar.

---

# 26. CHECKPOINTS

Después de cada lote verifica como mínimo:

```bash
npm run typecheck
```

o el comando equivalente encontrado en el proyecto.

También ejecuta:

```bash
npm run build
```

cuando corresponda.

Si existe:

```bash
npm run lint
```

ejecútalo.

Si existen tests:

```bash
npm test
```

o el comando correspondiente.

Utiliza los scripts reales encontrados en `package.json`.

NO inventes comandos.

---

# 27. VALIDACIÓN DESPUÉS DE CADA MOVIMIENTO

Después de mover archivos:

- comprueba imports;
- comprueba aliases;
- comprueba rutas;
- comprueba auto-imports;
- comprueba referencias dinámicas;
- comprueba imports relativos;
- comprueba GraphQL;
- comprueba stores;
- comprueba router;
- comprueba tests.

No asumas que TypeScript detectará todas las referencias.

Busca referencias textuales cuando sea necesario.

---

# 28. GIT

Antes de una modificación estructural importante:

comprueba el estado de Git.

No sobrescribas modificaciones existentes del usuario.

Nunca ejecutes:

```bash
git reset --hard
git clean -fd
```

ni comandos destructivos equivalentes.

No elimines archivos simplemente porque parezcan no utilizados sin comprobar referencias.

Si un archivo parece obsoleto:

```text
POSSIBLY_UNUSED
```

regístralo.

No lo elimines inmediatamente.

---

# 29. REGLA CONTRA EL CÓDIGO MUERTO

No elimines código únicamente porque:

- no encuentres una referencia directa;
- parece antiguo;
- el nombre parece incorrecto;
- el modelo considera que no es necesario.

Puede existir:

- import dinámico;
- auto-import;
- referencia desde configuración;
- ruta;
- GraphQL;
- plugin;
- runtime;
- generación automática.

Si existe duda:

NO eliminar.

---

# 30. DOCUMENTACIÓN ARQUITECTÓNICA

Al finalizar la reorganización crea o actualiza un documento:

```text
ARCHITECTURE.md
```

Debe explicar:

```text
1. Mapa general
2. Responsabilidad de cada directorio
3. Cómo localizar una funcionalidad
4. Reglas de dependencias
5. Dónde colocar una nueva feature
6. Dónde colocar componentes reutilizables
7. Dónde colocar GraphQL
8. Dónde colocar stores
9. Dónde colocar composables
10. Qué NO debe hacerse
```

El documento debe ser corto y práctico.

No escribas documentación académica.

Debe funcionar como:

> "Mapa mental del proyecto."

---

# 31. ARCHITECTURAL RULES

Además de `ARCHITECTURE.md`, crea si resulta apropiado:

```text
ARCHITECTURE_RULES.md
```

con reglas verificables.

Por ejemplo:

```text
RULE-001
Una feature no debe importar directamente detalles internos
de otra feature salvo excepción documentada.

RULE-002
shared no puede depender de features.

RULE-003
Los componentes específicos de una feature permanecen
dentro de esa feature.

RULE-004
No utilizar utils.ts como cajón de sastre.

RULE-005
Los stores globales deben justificar su alcance global.
```

Las reglas deben derivarse de la arquitectura real.

No inventes reglas innecesarias.

---

# 32. MÉTRICA PRINCIPAL

Evalúa continuamente el proyecto mediante esta pregunta:

> "Si mañana otro programador recibe una tarea, ¿puede encontrar rápidamente dónde modificarla?"

La arquitectura es exitosa si:

```text
Task
 ↓
Feature
 ↓
Responsibility
 ↓
File
```

puede recorrerse intuitivamente.

---

# 33. MÉTRICA DE CARGA COGNITIVA

Para cada decisión arquitectónica pregunta:

### Antes

¿Cuántos lugares necesita inspeccionar un programador para comprender una funcionalidad?

### Después

¿Cuántos necesita inspeccionar?

Busca reducir:

```text
mental navigation
+
directory hopping
+
implicit dependencies
+
generic naming
+
cross-feature coupling
```

---

# 34. NO OPTIMIZAR PARA EL MODELO

No diseñes una estructura que sea fácil para una IA pero difícil para humanos.

El consumidor principal de esta arquitectura es:

> **un programador humano que conoce Vue pero no conoce este proyecto.**

La arquitectura debe ser legible sin depender de explicaciones del agente.

---

# 35. NO HACER REFACTORIZACIÓN FUNCIONAL

Esta tarea es principalmente:

```text
STRUCTURAL REFACTORING
```

No es:

```text
FEATURE DEVELOPMENT
```

No agregues funcionalidades.

No rediseñes UI.

No optimices rendimiento salvo que sea necesario para evitar una regresión.

No cambies comportamiento.

No actualices dependencias.

No cambies versiones.

No migres librerías.

No introduzcas nuevas herramientas.

---

# 36. PROTOCOLO DE DECISIÓN

Cuando encuentres dos arquitecturas razonables:

evalúalas según:

```text
1. Comprensibilidad humana
2. Localización del código
3. Cohesión
4. Acoplamiento
5. Simplicidad
6. Consistencia
7. Evolución futura
8. Compatibilidad con el código existente
```

No elijas por "popularidad".

No elijas por "best practice" genérica.

Elige la solución que mejor encaje con este proyecto.

---

# 37. FASES DE EJECUCIÓN

Trabaja exactamente en estas fases:

```text
PHASE 0
Repository reconnaissance

PHASE 1
Architecture inventory

PHASE 2
Dependency and responsibility mapping

PHASE 3
Architecture proposal

PHASE 4
Architecture self-audit

PHASE 5
Migration plan

PHASE 6
Incremental migration

PHASE 7
Validation

PHASE 8
Architecture cleanup

PHASE 9
Final audit
```

No saltes directamente de Phase 0 a Phase 6.

---

# 38. INFORME ANTES DE MODIFICAR

Antes del primer movimiento físico de archivos genera:

```text
REFACTOR_PLAN.md
```

Debe contener:

```text
CURRENT ARCHITECTURE
PROBLEMS FOUND
TARGET ARCHITECTURE
MAPPING OLD → NEW
MIGRATION BATCHES
RISKS
VALIDATION STRATEGY
UNRESOLVED QUESTIONS
```

Incluye una tabla:

```text
Current location | Proposed location | Reason | Risk
```

---

# 39. PROHIBICIÓN IMPORTANTE

Si durante el análisis encuentras una decisión arquitectónica que depende de información que no puede determinarse de forma segura:

NO inventes.

Marca:

```text
NEEDS_HUMAN_DECISION
```

y explica exactamente qué información falta.

Sin embargo, no detengas todo el trabajo por una cuestión no crítica.

Utiliza una solución conservadora temporal cuando sea posible.

---

# 40. LOTES

Cada lote debe tener un objetivo pequeño.

Ejemplo:

```text
BATCH 01
Application shell

BATCH 02
Authentication

BATCH 03
Users

BATCH 04
Tickets

BATCH 05
Stations

BATCH 06
Reports

BATCH 07
Shared infrastructure
```

Los nombres deben corresponder al proyecto real.

No crees lotes artificiales.

---

# 41. FORMATO DEL INFORME DE CADA LOTE

Después de cada lote informa:

```text
BATCH:
Objective:

Files moved:

Files created:

Files deleted:
None unless explicitly justified.

Imports updated:

Architectural changes:

Behavior changes:
NONE

Validation:
TypeScript:
Build:
Lint:
Tests:

Problems:

Remaining risks:
```

---

# 42. CONTROL DE REGRESIONES

Si una validación falla después de un movimiento:

primero determina si el error:

```text
A) fue causado por la reorganización
B) ya existía
C) es independiente
```

Si fue causado por la reorganización:

corrígelo.

Si ya existía:

no lo "arregles" como parte de esta tarea.

Regístralo.

---

# 43. CRITERIO PARA TERMINAR

No consideres terminada la tarea simplemente porque:

```text
npm run build
```

funcione.

La tarea termina cuando:

1. La estructura es coherente.
2. Las responsabilidades están claras.
3. Las features son localizables.
4. Las dependencias son razonables.
5. No existen grandes cajones de sastre.
6. Los nombres son suficientemente descriptivos.
7. La arquitectura puede explicarse sin conocer la implementación.
8. El proyecto compila.
9. Los tests existentes pasan.
10. No se introdujo comportamiento nuevo.
11. La documentación refleja la estructura real.
12. Existe un mapa mental claro para un nuevo desarrollador.

---

# 44. AUDITORÍA FINAL

Al terminar, haz una revisión independiente.

Imagina que eres:

> "un programador senior que acaba de incorporarse al proyecto y nunca lo ha visto."

Intenta responder:

```text
¿Dónde está authentication?

¿Dónde está Users?

¿Dónde está Tickets?

¿Dónde está el estado global?

¿Dónde están las queries GraphQL?

¿Dónde están las mutations?

¿Dónde están los componentes reutilizables?

¿Dónde están los layouts?

¿Dónde está la navegación?

¿Dónde están las utilidades?

¿Dónde está la configuración?

¿Dónde agregaría una nueva feature?

¿Dónde modificaría una feature existente?
```

Si alguna respuesta requiere buscar por todo `src/`, considera que la arquitectura todavía puede mejorarse.

---

# 45. AUDITORÍA DE "MENTAL DEBUGGING"

Busca específicamente situaciones donde para entender un cambio sea necesario hacer mentalmente:

```text
A → B → C → D → E → F → G
```

cuando podría existir:

```text
A → B
```

Reduce dependencias indirectas.

Busca:

- re-exportaciones excesivas;
- barrels;
- utilidades genéricas;
- stores que delegan a otros stores;
- composables que llaman múltiples composables;
- servicios que llaman servicios;
- componentes que contienen lógica de negocio;
- lógica escondida en plugins;
- dependencias implícitas.

No elimines abstracciones automáticamente.

Evalúa si realmente reducen complejidad.

---

# 46. PRINCIPIO FINAL

La arquitectura debe cumplir esta regla:

> **"La estructura del filesystem debe contar la historia del sistema."**

Un programador debería poder mirar:

```text
src/
```

y obtener una primera comprensión conceptual de la aplicación sin abrir todavía los archivos.

Después debería poder entrar en una feature:

```text
features/tickets/
```

y comprender qué contiene y por qué.

Después debería poder abrir un archivo:

```text
TicketTable.vue
```

y entender inmediatamente qué responsabilidad tiene.

Si la arquitectura requiere documentación extensa para explicar por qué los archivos están donde están, reconsidera la arquitectura.

---

# 47. INSTRUCCIÓN DE INICIO

Comienza ahora con:

```text
PHASE 0 — REPOSITORY RECONNAISSANCE
```

No modifiques ningún archivo todavía.

Inspecciona primero el repositorio completo y genera una evaluación arquitectónica.

Después continúa con:

```text
PHASE 1
PHASE 2
PHASE 3
PHASE 4
```

Cuando hayas terminado esas fases, presenta el plan de migración.

**NO empieces PHASE 6 hasta que la arquitectura propuesta haya sido auditada y exista un plan de migración verificable.**

Durante toda la tarea recuerda:

> **Preservar comportamiento.**
>
> **Reducir carga cognitiva.**
>
> **Hacer predecible la localización del código.**
>
> **Preferir simplicidad sobre abstracción.**
>
> **Mover primero, cambiar comportamiento nunca.**
>
> **Validar cada paso.**
>
> **La arquitectura es para humanos, no para impresionar al modelo.**