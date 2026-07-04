# FormKit con Metadatos

El sistema de formularios usa **FormKit 2** con un theme personalizado y generación dinámica de schemas desde metadatos de entidad.

## Arquitectura

```
src/form/
├── formkit-theme-fdn/   # Proyecto theme independiente (Tailwind)
├── formkit.theme.ts     # Tema compilado (4384 líneas)
├── input-schemas/       # Schemas de inputs predefinidos
│   ├── index.json       # 37 tipos de input catalogados
│   └── schemas/         # Schemas individuales
├── inputs/              # Inputs personalizados
│   ├── index.ts         # Registro de inputs
│   ├── button/
│   ├── checkbox/
│   ├── datepicker/
│   ├── icon_picker/
│   ├── number/
│   ├── password/
│   ├── select/
│   └── text/            # text, text_icon, text_search
└── plugins/             # Plugins FormKit
    ├── addAsterisk.ts   # Añade asterisco a campos requeridos
    ├── animate.ts       # Animaciones en formularios
    ├── filterProps.ts   # Filtra propiedades no válidas
    └── scrollToErrors.ts # Scroll automático a errores
```

## Inputs personalizados

Registrados en `src/form/inputs/index.ts`:

| Input | Archivo | Propósito |
|-------|---------|-----------|
| `button` | `button/` | Botón personalizado |
| `checkbox` | `checkbox/` | Checkbox con theme FDN |
| `datetime` | `datepicker/` | Selector de fecha/hora |
| `icon_picker` | `icon_picker/` | Selector de iconos Material Symbols |
| `number` | `number/` | Input numérico |
| `password` | `password/` | Campo de contraseña |
| `select` | `select/` | Select desplegable |
| `text` | `text/text` | Texto plano |
| `text_icon` | `text/text` | Texto con icono |
| `text_search` | `text/text` | Búsqueda de texto |

## Generación dinámica de schemas

El schema del formulario se construye en `storeFactory.ts`, método `getFormSchema()`:

1. Toma `config.formFields` (configuración visual desde backend)
2. Para cada campo visible, obtiene su definición desde `entity.fields`
3. Mapea el tipo GraphQL al tipo FormKit:

| Tipo GraphQL | Input FormKit |
|-------------|--------------|
| `String` | `text` |
| `Int`, `Float`, `ID` | `number` |
| `Boolean` | `checkbox` |
| `Date` | `datetime` |
| Relación (`OBJECT`, `LIST`, `ENUM`) | `select` |

4. Para campos relacionados, carga opciones dinámicamente vía `getStore(relatedTo).getOptions()`
5. Construye schema con grid responsivo:

```typescript
this.formSchema = [
  { $el: 'div', children: '$slots.crudBtn' },
  {
    $el: 'div',
    attrs: { class: 'grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2' },
    children: fields.map(v => v.input),
  },
]
```

## Theme FDN

El theme está en `src/form/formkit-theme-fdn/`, un proyecto independiente generado con el CLI de FormKit. Usa Tailwind CSS para las clases.

El archivo compilado `src/form/formkit.theme.ts` (4384 líneas) contiene la función `rootClasses` que asigna clases CSS a cada sección de cada tipo de input. Las familias soportadas son:

- `text`, `box` (checkbox/radio), `button`, `dropdown`
- Tipos individuales: `checkbox`, `color`, `date`, `datetime-local`, `email`, `file`, `form`, `month`, `number`, `password`, `radio`, `range`, `search`, `select`, `submit`, `tel`, `textarea`, `time`, `url`, `week`, `autocomplete`, `colorpicker`

## Configuración

El archivo raíz `formkit.config.ts` importa el theme y los inputs personalizados:

```typescript
import { plugin, defaultConfig } from '@formkit/vue'
import config from '../../formkit.config'
// En boot:
app.use(plugin, defaultConfig(config))
```

## Plugins

- **addAsterisk.ts**: Añade un asterisco rojo a labels de campos requeridos
- **animate.ts**: Animaciones de entrada/salida en formularios
- **filterProps.ts**: Filtra props no estándar antes de pasarlas al input
- **scrollToErrors.ts**: Scroll automático al primer campo con error al hacer submit
