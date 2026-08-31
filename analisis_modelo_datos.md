# Análisis del Modelo de Datos — Sistema FDN (Modelo Nuevo)
> **Fuente:** `backend/src/Entity/` (modelo nuevo, en construcción)  
> **Rol:** Arquitecto Senior — Base de Datos, Patrones de Diseño, APIs REST/GraphQL

---

## 1. Mapa Completo del Modelo

### 1.1 Jerarquía de clases base

```
Base (MappedSuperclass)
  ├── id: int (autoincrement)
  ├── label: ?string (calculado vía reflexión → getNombre() o getName())
  │
  ├── PersonaBase extends Base
  │     traits: TimestampableEntityTrait, StatusTrait
  │     campos: nombre, apellido, email, nit, telefono, direccion, localidad
  │     ├── Cliente   (vacía, solo hereda PersonaBase)
  │     ├── Piloto    (+ fechaNacimiento, licencia, dpi, seguroSocial, codigo, empresa)
  │     └── Usuario   (+ username, password, roles, permisos, actions, ventas)
  │
  ├── TimeLegacyStatusBase extends Base
  │     traits: TimestampableEntityTrait, StatusTrait
  │     ├── Venta     (usuario, boletos[], factura, enclave, empresa)
  │     ├── Boleto    (recorrido, cliente, venta, asiento, servicio)
  │     └── Servicio  (fecha, empresa, recorrido, bus, piloto, boletos[])
  │
  └── Base directa:
        ├── Empresa   (nombre, nit, direccion, telefono, email, buses[], ventas[])
        ├── Bus       (matricula, gama, empresa, asientos[], piloto, pilotoAux, marca, codigo...)
        ├── Asiento   (numero, clase:TipoAsiento, fila, columna, bus)
        ├── BusMarca  
        ├── Recorrido (nombre, precioClaseA:Precio, precioClaseB:Precio, empresa, bus, trayecto, subrecorridos[])
        ├── Trayecto  (origen:Enclave, destino:Enclave, distanciaKm, duracionEstimadaMinutos, activo, trayectosHijos[], recorridos[])
        ├── Localidad (nombre, nacion)
        ├── Nacion    (nombre) [tabla: pais]
        ├── Status    (nombre) — estado genérico compartido
        └── Factura   (dte, uuid, serie, fecha, emisorNit, emisorNombre, receptorNit, receptorNombre...)

Enclave (SINGLE_TABLE inheritance, discriminator: tipo)
  ├── Enclave   ('enclave') — ubicación geográfica base
  ├── Estacion  ('estacion') — subtipo vacío, hereda todo de Enclave
  └── Parada    ('parada')  — subtipo vacío, hereda todo de Enclave
  campos comunes: nombre, direccion, latitud, longitud, ventas[]
  
RecorridoMatrioska (recorrido padre → subrecorridos[]: Recorrido, posicion)

Embeddable:
  Precio (monto: int, moneda: string[3]) — usa la librería MoneyPHP
```

### 1.2 Flujo de negocio principal

```
Empresa
  └── Bus (empresa) ──► Asiento[] (bus, clase: A/B)
  └── Piloto (empresa)
  └── Recorrido (empresa, trayecto, bus)
        └── Servicio (empresa, recorrido, bus, piloto, fecha, status)
              └── Boleto[] (servicio, recorrido, cliente, venta, asiento, status)
  └── Venta (empresa, usuario, enclave, factura, boletos[])
        └── Factura (dte, uuid, serie, emisor/receptor)

Trayecto (origen:Enclave, destino:Enclave)
  ├── trayectosHijos[] (self-referential ManyToMany)
  └── trayectosPadres[] (inverso)

RecorridoMatrioska (recorrido_padre → subrecorridos[]:Recorrido, posicion)

Cliente ──► PersonaBase (nombre, apellido, nit, email, localidad)
```

---

## 2. Fortalezas del Modelo Actual

Antes de los problemas, es justo reconocer lo que está bien resuelto:

| Aspecto | Evaluación |
|---|---|
| **Nomenclatura del dominio** | ✅ Excelente. `Enclave`, `Trayecto`, `Recorrido`, `Parada`, `Estacion`, `Boleto` — coincide perfectamente con el glosario |
| **Jerarquía `Enclave`** | ✅ El SINGLE_TABLE inheritance para `Enclave/Estacion/Parada` es una decisión correcta y eficiente para este caso |
| **`trayectosPadres/Hijos`** | ✅ La recursividad ManyToMany modela correctamente el concepto de trayectos contenidos dentro de otros |
| **`PersonaBase` compartido** | ✅ `Cliente`, `Piloto` y `Usuario` reutilizan la misma base — elimina duplicación del modelo legacy |
| **`Precio` embeddable con MoneyPHP** | ✅ Uso de Money pattern (montos como enteros + ISO currency) es la práctica correcta para manejo de dinero |
| **`TimestampableEntityTrait` + `StatusTrait`** | ✅ Auditoría y estados centralizados como traits — patrón correcto |
| **`legacyId` en varias entidades** | ✅ Buena práctica para mantener trazabilidad durante la migración desde el sistema legacy |
| **`Factura` como documento fiscal inmutable** | ✅ Almacena snapshot de datos del emisor/receptor — correcto para documentos tributarios |

---

## 3. Problemas Identificados

### 🔴 Críticos

#### P1 — `Recorrido` no modela el "recorrido" del dominio: es una plantilla, no una instancia

Según el glosario: **recorrido** = trayecto + timestamp que establece cuando un bus comienza a recorrer el trayecto.

La entidad `Recorrido` actual tiene: `nombre`, `precioClaseA`, `precioClaseB`, `empresa`, `bus`, `trayecto`.  
**No tiene `fecha` ni timestamp alguno.** La fecha está en `Servicio`.

Por otro lado, `Servicio` tiene: `fecha`, `empresa`, `recorrido`, `bus`, `piloto`, `boletos[]`.

Lo que el código llama `Recorrido` es en realidad una **plantilla de servicio** (configuración reutilizable: precio + bus asignado + trayecto), y lo que llama `Servicio` es el **recorrido real** del dominio (la instancia con fecha concreta).

Esto genera confusión semántica grave: un desarrollador que lea el glosario y luego el código buscará la fecha en `Recorrido` y no la encontrará.

```
DECISIÓN NECESARIA:
  Opción A: Renombrar Recorrido → PlantillaServicio / ConfiguracionRecorrido
            y Servicio → Recorrido (el concepto del dominio con fecha)
  Opción B: Mover la fecha de Servicio a Recorrido y fusionar ambas entidades
            si Servicio es siempre 1:1 con Recorrido.
```

#### P2 — `Boleto` referencia tanto `Recorrido` como `Servicio` (FK doble hacia el mismo concepto)

```php
// Boleto.php
private ?Recorrido $recorrido = null;  // línea 19
private ?Servicio $servicio = null;    // línea 39
```

`Boleto` tiene FK a `Servicio` (que ya contiene FK a `Recorrido`). Esto implica que el recorrido es accesible como `$boleto->getServicio()->getRecorrido()`. Tener además `$boleto->getRecorrido()` introduce:
- **Riesgo de inconsistencia**: `$boleto->getRecorrido()` podría diferir de `$boleto->getServicio()->getRecorrido()`.
- **Responsabilidad duplicada**: ¿cuál FK es la "fuente de verdad"?

```
MEJORA: Si Boleto siempre pertenece a un Servicio, eliminar la FK directa
        a Recorrido de Boleto. El acceso es: $boleto->getServicio()->getRecorrido().
```

#### P3 — `Recorrido.bus` duplica la asignación que ya existe en `Servicio.bus`

```php
// Recorrido: bus asignado a nivel de plantilla
private ?Bus $bus = null;

// Servicio: bus asignado a nivel de ejecución concreta
private ?Bus $bus = null;
```

Si `Recorrido` es una plantilla, el bus en ella sería el bus "por defecto" o "habitual". Si `Servicio` es la instancia concreta, su bus es el que realmente opera ese día. Esto es ambiguo: ¿cuál prevalece? No hay documentación ni constraint que lo aclare.

```
MEJORA: Clarificar la semántica:
  - Si Recorrido es plantilla → bus en Recorrido = bus habitual (nullable, sugerencia)
  - Bus en Servicio = bus real ese día (obligatorio, not null)
  - Añadir un comentario PHPDoc o renombrar a $busHabitual en Recorrido.
```

#### P4 — `Status` es una entidad genérica que mezcla estados de conceptos distintos

La entidad `Status` tiene solo `nombre: string` y es usada por `Venta`, `Boleto`, `Servicio`, `Cliente`, `Piloto` y `Usuario` vía `StatusTrait`. Esto significa que la tabla `status` contiene filas como "Activo", "Anulado", "Pagado", "En ruta"... mezcladas.

**Problema:** Un boleto "Anulado" y un piloto "De vacaciones" comparten la misma tabla de catálogo. No hay nada en el modelo que impida asignar el status "De vacaciones" a un boleto.

```
MEJORA: Dos opciones:
  A) Agregar un campo tipo/categoría a Status y validar en application layer.
  B) Usar PHP Enums por entidad (más seguro):
       enum EstadoBoleto: string { case ACTIVO = 'activo'; case ANULADO = 'anulado'; }
       enum EstadoServicio: string { ... }
     Esto elimina la tabla status y da seguridad de tipos en compile-time.
```

---

### 🟡 Importantes

#### P5 — `RecorridoMatrioska` tiene un naming confuso y una FK con nombre equívoco

```php
class RecorridoMatrioska {
    private ?Recorrido $recorrido = null;       // el recorrido "padre/contenedor"
    private ?Recorrido $subrecorridos = null;   // el recorrido "hijo" (nombre en plural para FK singular ⚠️)
    private ?int $posicion = null;
}
```

- El campo `$subrecorridos` es **singular en contenido** (es un `?Recorrido`, no una colección), pero está nombrado en plural. Esto es un error de naming que generará confusión.
- El concepto de "matrioska" es creativo pero no del dominio. Considerando que `Trayecto` ya tiene `trayectosHijos[]` (self-referential ManyToMany), ¿no debería `RecorridoMatrioska` modelar algo distinto?

> [!IMPORTANT]
> Clarificar la diferencia conceptual entre `Trayecto.trayectosHijos` (sub-trayecto geográfico) y `RecorridoMatrioska` (sub-recorrido operativo). Si son lo mismo, hay redundancia. Si son distintos, necesita documentación.

```
MEJORA: Renombrar $subrecorridos → $subrecorrido (singular).
        Documentar claramente la diferencia con Trayecto.trayectosHijos.
```

#### P6 — `Bus` tiene pilotos asignados como relación estática (`piloto` y `pilotoAux`)

```php
// Bus.php
private ?Piloto $piloto = null;
private ?Piloto $pilotoAux = null;
```

La asignación piloto→bus es una relación dinámica que cambia con cada servicio. Al guardarla directamente en `Bus`, se pierde el historial: ¿quién condujo el bus el martes pasado? Además, `Servicio` también tiene `piloto`, creando duplicidad.

```
MEJORA: Eliminar piloto/pilotoAux de Bus.
        La asignación operativa vive exclusivamente en Servicio (que ya tiene piloto).
        Si se necesita "piloto habitual", usar $pilotoHabitual con documentación clara.
```

#### P7 — `Enclave` tiene `ventas[]` — acoplamiento incorrecto entre geografía y negocio

```php
// Enclave.php
#[ORM\OneToMany(targetEntity: Venta::class, mappedBy: 'enclave')]
private Collection $ventas;
```

Un `Enclave` (concepto geográfico: lugar con lat/lng) no debería conocer las ventas realizadas en él. Esto mezcla la capa de geografía con la capa de negocio en sentido inverso, violando la dirección natural de dependencia.

La navegación `$enclave->getVentas()` raramente es necesaria (más bien se consulta en dirección `$venta->getEnclave()`). La colección inversa añade complejidad sin beneficio real.

```
MEJORA: Eliminar la relación inversa en Enclave (quitar ventas[]).
        Si se necesita consultar ventas por enclave, usar un repositorio:
        $ventaRepository->findByEnclave($enclave)
```

#### P8 — `Trayecto` no tiene unicidad en el par (origen, destino)

El glosario define explícitamente que un trayecto es vectorial: A→B ≠ B→A, y que delimitan dos enclaves. Sin embargo, no existe ningún `UniqueConstraint` en la entidad:

```php
// No existe:
#[ORM\UniqueConstraint(columns: ['origen_id', 'destino_id'])]
```

Esto permite crear múltiples trayectos idénticos A→B, lo que generaría ambigüedad en la asignación de precios y en la búsqueda de recorridos.

```
MEJORA:
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_trayecto_vector', columns: ['origen_id', 'destino_id'])]
class Trayecto extends Base { ... }
```

---

### 🟢 Menores / Observaciones

#### O1 — `Nacion` usa tabla `pais` — inconsistencia entre nombre de clase y tabla

```php
#[ORM\Table(name: 'pais')]
class Nacion extends Base
```

El nombre de clase `Nacion` y el nombre de tabla `pais` divergen. Esto genera confusión al escribir queries DQL/SQL directas. Si la convención es que Doctrine mapea el nombre de clase, esto es una excepción que debe documentarse.

#### O2 — `Base.__construct()` inicializa `$id = 9` (valor hardcodeado)

```php
public function __construct() {
    $this->id = 9;  // ← ¿Por qué 9?
}
```

Esto es claramente un artifact de desarrollo. Doctrine sobrescribe el ID al persistir, pero puede generar sorpresas en tests unitarios o cuando se crea una entidad sin persistir.

#### O3 — `Base.getLabel()` usa reflexión en cada llamada

```php
public function getLabel() {
    $extractor = [new ReflectionExtractor()];
    $info = new PropertyInfoExtractor(...);
    $properties = $info->getProperties($class);
    ...
}
```

Instanciar `PropertyInfoExtractor` en cada llamada a `getLabel()` es costoso. Este método es probablemente llamado en la serialización de listas. Debería cachearse por clase (e.g. con un `static array $cache`).

#### O4 — `PersonaBase` usa `Assert\Email` sin importar el namespace

```php
#[Assert\Email(message: 'The email {{ value }} is not a valid email.')]
protected ?string $email = null;
```

El `use Symfony\Component\Validator\Constraints as Assert;` no aparece en `PersonaBase.php`. Si no está importado, el atributo se ignora silenciosamente en PHP 8+ (se trata como un atributo desconocido sin efecto).

---

## 4. Multi-Tenancy: Evaluación del Modelo Nuevo

### 4.1 Estado actual del aislamiento por empresa

| Entidad | empresa_id presente | Evaluación |
|---|---|---|
| `Bus` | ✅ `empresa` (nullable: false) | Correcto |
| `Piloto` | ✅ `empresa_id` | Correcto |
| `Recorrido` | ✅ `empresa` | Correcto |
| `Servicio` | ✅ `empresa` | Correcto |
| `Venta` | ✅ `empresa` | Correcto |
| `Trayecto` | ❌ Sin empresa_id | **Global** — compartido entre empresas |
| `Enclave/Estacion/Parada` | ❌ Sin empresa_id | **Global** — compartido |
| `Cliente` | ❌ Sin empresa_id | **Global** — compartido |
| `Status` | ❌ Sin empresa_id | **Global** — catálogo |
| `Factura` | ❌ Sin empresa_id directo | Tiene FK indirecta vía Venta |
| `Boleto` | ❌ Sin empresa_id directo | Hereda contexto vía Venta/Servicio |

### 4.2 ¿Es correcto que Trayecto y Enclave sean globales?

**Sí, con matices.** Un trayecto geográfico (Guatemala → Quetzaltenango) existe independientemente de qué empresa lo opera. Varias empresas pueden operar el mismo trayecto, como describe el glosario con los "trayectos compartidos".

La empresa aparece al nivel de `Recorrido` (la configuración operativa: precio, bus habitual) y de `Servicio` (la instancia concreta con fecha y piloto). Esto es **arquitectónicamente correcto**: separación entre geografía (global) y operación (por empresa).

### 4.3 El TenantFilter no está implementado

El modelo tiene `empresa_id` en las entidades operativas correctas, pero no existe ningún mecanismo automático de aislamiento. El filtrado actualmente depende de que cada repositorio/query incluya el criterio de empresa — lo cual es propenso a errores de omisión.

```php
// Implementación recomendada del TenantFilter
final class TenantFilter extends SQLFilter
{
    // Solo estas entidades requieren filtrado automático por empresa
    private const TENANT_ENTITIES = [
        Recorrido::class, Servicio::class,
        Venta::class, Bus::class, Piloto::class,
    ];

    public function addFilterConstraint(ClassMetadata $targetEntity, string $tableAlias): string
    {
        if (!in_array($targetEntity->getName(), self::TENANT_ENTITIES)) {
            return ''; // Enclave, Trayecto, Cliente, Status: globales, sin filtro
        }
        return "{$tableAlias}.empresa_id = " . $this->getParameter('empresa_id');
    }
}
```

---

## 5. Resumen de Hallazgos Priorizados

| # | Prioridad | Problema | Impacto |
|---|---|---|---|
| P1 | 🔴 Alta | `Recorrido` ≠ recorrido del dominio (es plantilla, le falta fecha) | Semántica / Mantenibilidad |
| P2 | 🔴 Alta | `Boleto` FK doble a `Recorrido` y `Servicio` — inconsistencia potencial | Integridad |
| P3 | 🔴 Alta | `Bus.piloto` duplica la asignación de `Servicio.piloto` | Integridad / Historial |
| P4 | 🟡 Media | `Status` genérico sin tipado — estados de entidades distintas mezclados | Type Safety |
| P5 | 🟡 Media | `RecorridoMatrioska.$subrecorridos` en plural para FK singular + semántica poco clara | Claridad |
| P6 | 🟡 Media | Ausencia de `TenantFilter` Doctrine — aislamiento multi-tenant sin garantía automática | Seguridad |
| P7 | 🟡 Media | `Enclave.ventas[]` — geografía acoplada a negocio en dirección inversa | Acoplamiento |
| P8 | 🟡 Media | `Trayecto` sin `UniqueConstraint(origen_id, destino_id)` | Integridad de datos |
| O1 | 🟢 Baja | `Nacion` con tabla `pais` — inconsistencia nombre de clase vs tabla | Consistencia |
| O2 | 🟢 Baja | `Base.$id = 9` hardcodeado en constructor | Code smell |
| O3 | 🟢 Baja | `Base.getLabel()` instancia `PropertyInfoExtractor` sin caché | Performance |
| O4 | 🟢 Baja | `Assert\Email` sin import en `PersonaBase` — validación silenciosamente ignorada | Bug silencioso |

---

## 6. Diagrama del Modelo Actual

```mermaid
erDiagram
    EMPRESA ||--o{ BUS : posee
    EMPRESA ||--o{ PILOTO : emplea
    EMPRESA ||--o{ RECORRIDO : configura
    EMPRESA ||--o{ SERVICIO : opera
    EMPRESA ||--o{ VENTA : realiza

    TRAYECTO }|--|| ENCLAVE : origen
    TRAYECTO }|--|| ENCLAVE : destino
    TRAYECTO }o--o{ TRAYECTO : "contiene (hijos/padres)"

    RECORRIDO }|--|| TRAYECTO : cubre
    RECORRIDO }|--|| EMPRESA : de
    RECORRIDO }o--|| BUS : "bus habitual"
    RECORRIDO ||--o{ RECORRIDO_MATRIOSKA : tiene

    SERVICIO }|--|| RECORRIDO : instancia
    SERVICIO }|--|| EMPRESA : de
    SERVICIO }o--|| BUS : usa
    SERVICIO }o--|| PILOTO : conduce
    SERVICIO ||--o{ BOLETO : genera

    VENTA }|--|| USUARIO : registra
    VENTA }|--|| EMPRESA : de
    VENTA }o--|| ENCLAVE : en
    VENTA ||--o{ BOLETO : contiene
    VENTA ||--o| FACTURA : tiene

    BOLETO }o--|| SERVICIO : para
    BOLETO }o--|| RECORRIDO : "redundante?"
    BOLETO }|--|| CLIENTE : compra
    BOLETO }|--|| ASIENTO : reserva

    BUS ||--o{ ASIENTO : tiene
    BUS }|--|| EMPRESA : de

    ENCLAVE ||--o| ESTACION : subtype
    ENCLAVE ||--o| PARADA : subtype

    CLIENTE --|{ PERSONA_BASE : extends
    PILOTO --|{ PERSONA_BASE : extends
    USUARIO --|{ PERSONA_BASE : extends
```
