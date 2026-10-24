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
      'Para hablar con varias personas a la vez, cree una conversación de grupo desde la bandeja y elija a sus miembros.',
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
    id: 'responder-y-acciones-de-mensaje',
    tema: 'chat',
    titulo: 'Responder y acciones de un mensaje',
    resumen: 'Responder citando, copiar o ver el mensaje citado.',
    para: [],
    pasos: [
      'En el teléfono: mantenga pulsado un mensaje para abrir sus acciones (responder, copiar, ver el citado).',
      'Al responder, el mensaje citado aparece encima de su respuesta.',
    ],
    palabras: ['citar', 'responder', 'copiar', 'reply'],
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
