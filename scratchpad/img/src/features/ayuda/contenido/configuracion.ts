/** Configuración de la aplicación: menús, campos, formularios y la publicación de los cambios. */
import type { Guia } from '@/core/ayuda/tipos'
import { CONFIG, MENUS } from './audiencias'

export const guiasConfiguracion: Guia[] = [
  {
    id: 'menus-de-navegacion',
    tema: 'configuracion',
    titulo: 'Menús de navegación',
    resumen: 'Qué aparece en el menú lateral y en la barra de la derecha, y para quién.',
    para: [MENUS],
    pasos: [
      'Abra «Menús» en la configuración.',
      'Cada menú es un árbol: un grupo agrupa a sus hijos y no navega; un ítem abre una pantalla, un enlace o una dirección escrita a mano.',
      'Arrastre los ítems para cambiar su orden o su nivel. La paleta a un costado trae los ítems disponibles.',
      'En cada menú elija las áreas donde aparece (barra izquierda o derecha) y para qué cuentas.',
      'Pulse «Guardar».',
    ],
    ojo: [
      'Cada ítem solo aparece si la cuenta puede abrir su pantalla: un menú no le muestra nada que no pueda usar.',
      'Un ítem puede estar en varios menús.',
    ],
    abrir: { etiqueta: 'Abrir Menús', destino: { name: 'menu-builder' } },
    pantallas: ['menu-builder'],
    palabras: ['menu', 'barra', 'navegacion', 'arbol', 'orden del menu', 'ocultar opcion'],
  },
  {
    id: 'campos-y-columnas',
    tema: 'configuracion',
    titulo: 'Campos y columnas de los listados',
    resumen: 'Qué campos tiene cada entidad y qué columnas muestra su listado.',
    para: [CONFIG],
    pasos: [
      'En un listado, pulse «Configurar entidad» (o abra «Configuración de entidades»).',
      'Cada campo tiene su posición en el panel: arrástrelo para moverlo.',
      'En cada campo puede ajustar su etiqueta, si se edita en línea, si filtra y si ordena.',
      'Pulse «Guardar cambios». Si sale sin guardar, aparece «Descartar cambios y recargar».',
    ],
    ojo: [
      'Un campo marcado como propiedad de la entidad no se edita aquí.',
      'Los cambios se publican según el modo de publicación (vea «Publicar cambios de configuración»).',
    ],
    palabras: ['columnas', 'campos', 'configurar listado', 'etiqueta de campo', 'edicion en linea'],
  },
  {
    id: 'constructor-de-formularios',
    tema: 'configuracion',
    titulo: 'Constructor de formularios',
    resumen: 'La distribución de los campos de un formulario, en cuadrículas.',
    para: [CONFIG],
    pasos: [
      'Abra el constructor de formularios de la configuración.',
      'Cree una cuadrícula raíz con «Crear grid raíz» y asigne campos a sus celdas con «Asignar a celda».',
      'Para anidar otra cuadrícula dentro de una celda, use «Anidar grid en celda».',
      'Pulse «Guardar distribución» al terminar.',
    ],
    ojo: ['Eliminar contenido borra lo que hay en la celda; no borra el campo de la entidad.'],
    palabras: ['formulario', 'distribucion', 'grid', 'disposicion', 'campos del formulario'],
  },
  {
    id: 'publicar-cambios',
    tema: 'configuracion',
    titulo: 'Publicar cambios de configuración',
    resumen: 'Cómo llegan a todos los navegadores los cambios de campos, listados y menús.',
    para: [CONFIG],
    pasos: [
      'Abra «Publicación» en la configuración. Muestra el modo y lo pendiente de publicar.',
      'Si el modo es automático, los cambios se publican al terminar cada guardado.',
      'Si el modo es manual, pulse «Publicar». Puede dejar una nota para el registro.',
      '«Avisar en cada navegador» pide a los equipos abiertos que recarguen. «Recargar todo» lo hace de una vez.',
    ],
    ojo: [
      'Un formulario abierto no se recarga solo: muestra «Recargar» para que lo haga usted, sin perder lo que escribió.',
      'Las últimas revisiones quedan listadas con su nota y su fecha.',
    ],
    abrir: { etiqueta: 'Abrir Publicación', destino: { name: 'metadata-publicacion' } },
    pantallas: ['metadata-publicacion'],
    palabras: ['publicar', 'recargar', 'version', 'cambios pendientes', 'revision'],
  },
]
