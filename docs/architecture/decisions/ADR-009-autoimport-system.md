# ADR-009: Sistema de auto-import mediante unplugin

**Estado:** Aceptada

## Contexto

El frontend de FDN Transportes contiene numerosos composables, stores, utilidades y servicios que se usan de forma transversal en toda la aplicación. En un proyecto convencional, cada archivo debe importar manualmente las dependencias que necesita, lo que genera:

- Importaciones repetitivas (ej: `useQuasar` aparece en decenas de componentes)
- Ruido visual en la parte superior de cada archivo
- Riesgo de importaciones inconsistentes (mismo símbolo importado de diferentes rutas)
- Dificultad para refactorizar rutas de módulos

Se busca una solución que elimine las importaciones manuales para símbolos de uso común sin perder la trazabilidad ni el tipado de TypeScript.

## Decisión

Se implementa **auto-import mediante unplugin** usando `unplugin-auto-import` para símbolos y `unplugin-vue-components` para componentes Vue. La configuración en `quasar.config.ts` define:

**Auto-import de símbolos:**
- APIs de Vue (`ref`, `computed`, `watch`, `onMounted`, etc.)
- APIs de Vue Router (`useRouter`, `useRoute`)
- APIs de Pinia (`defineStore`, `storeToRefs`)
- Quasar (`useQuasar`)
- Directorios del proyecto: `src/composables/`, `src/stores/autoimport/**/*`, `src/utils/autoimport/**/*`, `src/config/`, `src/graphql/`, `src/services/`

**Auto-import de componentes Vue:**
- Todos los componentes en `src/components/` (recursivo, con profundidad)
- Generación de declaraciones TypeScript en `src/components.d.ts`

**Auto-import de stores globales:**
- `stores/autoimport/` contiene stores que se cargan globalmente (loading, menu, sidebar)

Los tipos generados se escriben en `src/auto-imports.d.ts` y `src/components.d.ts` respectivamente.

## Consecuencias

**Positivas:**

- Eliminación total de importaciones para símbolos de uso común
- El código es más limpio y legible
- Refactorización de rutas más sencilla (se actualiza la configuración de auto-import)
- TypeScript completamente soportado con generación automática de tipos
- Los componentes se auto-importan sin necesidad de registro manual en cada archivo
- La configuración es explícita y controlable (directorios específicos, no mágica)

**Negativas:**

- Las importaciones implícitas dificultan entender de dónde viene un símbolo sin un IDE
- La generación de tipos requiere regenerar `auto-imports.d.ts` al agregar nuevos composables
- Pueden ocurrir colisiones de nombres si dos módulos exportan el mismo símbolo
- La dependencia de unplugin añade complejidad al pipeline de build de Vite
- Desarrolladores nuevos pueden confundirse al no ver importaciones explícitas
