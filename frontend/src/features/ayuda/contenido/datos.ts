/** Listados y formularios: cómo se usan en cualquier pantalla de datos. */
import type { Guia } from '@/core/ayuda/tipos'

export const guiasDatos: Guia[] = [
  {
    id: 'buscar-filtrar-ordenar',
    tema: 'datos',
    titulo: 'Buscar, filtrar y ordenar un listado',
    resumen: 'Lo que hay en casi todas las pantallas de datos.',
    para: [],
    pasos: [
      'Escriba en la búsqueda de arriba para encontrar por texto.',
      'Para filtrar por una columna, pulse el embudo de su cabecera y escriba o elija el valor. «Limpiar filtro» lo quita.',
      'Para ordenar, pulse el nombre de la columna. Otra vez invierte el orden.',
      '«Filtros» abre los filtros de la pantalla, y «Limpiar filtros» los quita todos.',
    ],
    ojo: [
      'Varios filtros de columnas se combinan con «o» por defecto. «Cómo se combinan los filtros» lo cambia a «y» si lo necesita.',
      'Un filtro a la medida (como el de tarifas por trayecto) reemplaza a los de columna: al aplicar uno, el otro se quita.',
    ],
    palabras: ['buscar', 'filtro', 'ordenar', 'embudo', 'columna', 'o y', 'filtrar'],
  },
  {
    id: 'columnas-y-vista',
    tema: 'datos',
    titulo: 'Columnas, densidad y vista',
    resumen: 'Cambiar qué columnas ve y cómo se apilan las filas.',
    para: [],
    pasos: [
      '«Columnas» muestra u oculta columnas. Arrastre la cabecera para cambiar su posición; el borde derecho la ensancha o la angosta.',
      '«Densidad» y «Filas por página» cambian cuánto cabe en la pantalla.',
      '«Restablecer vista» vuelve a las columnas, orden y filtros de fábrica.',
    ],
    ojo: [
      'La vista se recuerda en su navegador: si entra desde otro equipo, la verá como la dejó de fábrica.',
    ],
    palabras: ['columnas', 'ocultar columna', 'mover columna', 'densidad', 'filas', 'restablecer'],
  },
  {
    id: 'crear-y-editar-registros',
    tema: 'datos',
    titulo: 'Crear y editar un registro',
    resumen: 'El formulario de un registro nuevo o existente.',
    para: [],
    pasos: [
      'Pulse «Nuevo» (el +) en la barra del listado para crear.',
      'Para editar, pulse el registro o «Editar» en su fila.',
      'Complete los campos. Los obligatorios se marcan en el formulario.',
      'Pulse «Guardar» (o «Guardar cambios»). «Cancelar» descarta lo escrito.',
    ],
    ojo: [
      'Los botones aparecen solo si su cuenta puede crear, editar o eliminar ese tipo de registro.',
      'Si cambiaron los campos de la pantalla mientras la tenía abierta, aparece «Recargar»: pulselo antes de guardar.',
      'El nombre visible de un registro se arma solo a partir de sus datos; no se escribe aparte.',
    ],
    palabras: ['nuevo', 'agregar', 'formulario', 'guardar', 'cancelar', 'editar registro'],
  },
  {
    id: 'seleccion-de-varios',
    tema: 'datos',
    titulo: 'Seleccionar varios registros',
    resumen: 'Hacer lo mismo a varios a la vez.',
    para: [],
    pasos: [
      'Pulse «Modo selección» en la barra. Aparecen casillas en cada fila.',
      'Marque los registros, o «Seleccionar la página» para todos los de la pantalla.',
      'Las acciones que sirven para varios aparecen arriba. «Quitar selección» limpia la marca.',
    ],
    ojo: [
      'Las acciones de la selección se aplican a todo lo marcado, también a lo que no se ve en la pantalla.',
    ],
    palabras: ['marcar varios', 'seleccion', 'masivo', 'lote', 'casillas'],
  },
]
