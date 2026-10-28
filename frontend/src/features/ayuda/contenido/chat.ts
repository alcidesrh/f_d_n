/** Mensajes internos: conversar, adjuntar y compartir registros. */
import type { Guia } from '@/core/ayuda/tipos'

export const guiasChat: Guia[] = [
  {
    id: 'conversar',
    tema: 'chat',
    titulo: 'Conversar con compañeros',
    resumen: 'Mensajes entre usuarios, en directo o en grupos.',
    para: [],
    antes: 'Se hablaba por teléfono o por grupos externos; ahora todo queda dentro del sistema.',
    pasos: [
      'Abra «Mensajes» (el icono de la cabecera o el menú).',
      'Busque a una persona o a una conversación en el buscador de arriba.',
      'Escriba en el campo de abajo y envíe con Enter (o con el botón de enviar).',
      'Para hablar con varias personas a la vez, cree una conversación de grupo desde la bandeja y elija a sus miembros. Después, el botón de la cabecera del grupo pone o quita su foto.',
    ],
    ojo: [
      'Una agencia solo conversa con su propia gente y con la administración.',
      '«Visto» indica quién ya leyó el mensaje.',
    ],
    abrir: { etiqueta: 'Abrir Mensajes', destino: { name: 'chat' } },
    pantallas: ['chat'],
    palabras: ['chat', 'mensaje', 'grupo', 'directo', 'comunicacion interna', 'visto'],
  },
  {
    id: 'grupos-abiertos-y-dirigidos',
    tema: 'chat',
    titulo: 'Grupos abiertos y dirigidos',
    resumen: 'Quién escribe en un grupo, editar sus miembros y sumar otros grupos.',
    para: [],
    pasos: [
      'Al crear un grupo elija quién escribe: «Abierto» (todos los miembros envían y reciben) o «Dirigido» (todos reciben; solo envían el dueño y quienes él elija).',
      'Quien creó el grupo es su dueño: con el lápiz de la cabecera cambia el nombre, el modo, agrega o quita personas y, en uno dirigido, marca «Escribe» en quienes pueden enviar.',
      'Un grupo abierto puede ser miembro de otro, como una persona: todos sus miembros reciben, y junto al nombre de quien escribe se ve el del grupo por el que llega.',
      'Los demás miembros ven la información del grupo con el botón ⓘ y pueden salir de él.',
    ],
    ojo: [
      'Un grupo que es miembro de otro solo puede ser abierto y no puede tener grupos como miembros.',
      'La regla de las agencias vale para todos los que llegan por un grupo miembro.',
    ],
    pantallas: ['chat'],
    palabras: ['grupo', 'dirigido', 'abierto', 'difusion', 'anuncios', 'dueño', 'editar grupo', 'miembros', 'subgrupo'],
  },
  {
    id: 'responder-y-acciones-de-mensaje',
    tema: 'chat',
    titulo: 'Responder y acciones de un mensaje',
    resumen: 'Responder, reenviar, editar, copiar o eliminar un mensaje.',
    para: [],
    pasos: [
      'En la computadora: pase el ratón sobre un mensaje y use ⋮ para ver sus acciones. En el teléfono: manténgalo pulsado.',
      'Al responder, el mensaje citado aparece encima de su respuesta.',
      '«Reenviar» lo manda tal cual (texto, fotos y registros) a otras personas o grupos, marcado «Reenviado».',
      '«Editar» (solo los suyos) lo carga en el redactor; al enviarlo, la versión corregida sale como respuesta al original, marcada «editado». El original no cambia.',
      '«Eliminar» lo borra para todos y deja «Se eliminó este mensaje». El dueño de un grupo puede eliminar los mensajes de cualquiera en su grupo.',
    ],
    palabras: ['citar', 'responder', 'copiar', 'reply', 'reenviar', 'editar', 'eliminar', 'borrar mensaje'],
  },
  {
    id: 'adjuntar-fotos-y-archivos',
    tema: 'chat',
    titulo: 'Enviar fotos y archivos',
    resumen: 'Fotos, documentos y registros del sistema dentro de la conversación.',
    para: [],
    pasos: [
      'Pulse el clip para adjuntar: una foto o un archivo desde su equipo.',
      'También puede pegar una imagen o soltar el archivo sobre la conversación.',
      'Para adjuntar un registro del sistema (un boleto, una salida, un bus), use «Adjuntar registros»: busque, marque y envíe.',
    ],
    ojo: [
      'Los archivos tienen un límite de 10 MB. Las fotos se reducen antes de subir.',
      'El registro se muestra con su estado actual, según lo que pueda ver quien lo abre.',
    ],
    palabras: ['foto', 'archivo', 'adjunto', 'imagen', 'documento', 'compartir boleto'],
  },
  {
    id: 'enviar-registro-por-chat',
    tema: 'chat',
    titulo: 'Enviar un registro por chat',
    resumen: 'Desde un listado o una salida, mande la ficha a un compañero.',
    para: [],
    pasos: [
      'En un listado, marque uno o varios registros y use «Enviar por chat» (o pulse «Enviar por chat» en la fila).',
      'Elija a la persona o al grupo y envíe.',
      'En una salida, «Reportar por chat» manda el reporte de la salida.',
    ],
    ojo: [
      'El destinatario ve el registro con lo que su cuenta puede ver; si no puede abrirlo, verá solo la ficha básica.',
    ],
    palabras: ['compartir', 'mandar', 'reportar', 'enviar ficha'],
  },
  {
    id: 'ventana-de-mensajes',
    tema: 'chat',
    titulo: 'Mensajes mientras trabaja',
    resumen: 'Abrir los mensajes sobre la pantalla donde está, sin salir de ella.',
    para: [],
    pasos: [
      'El botón de mensajes de la cabecera abre la conversación como ventana sobre la pantalla.',
      'Puede moverla y cambiar su modo: centro, esquina o flotante.',
      'En el teléfono siempre abre a pantalla completa, como una app de mensajería.',
    ],
    ojo: ['Si llega un mensaje mientras trabaja, aparece un aviso que lo lleva a la conversación.'],
    palabras: ['ventana', 'flotante', 'esquina', 'pantalla completa', 'aviso de mensaje'],
  },
]
