/**
 * Contrato y términos que firma el remitente en el recibo de una encomienda,
 * como los imprime el legado (`encomiendaTermica56.html.twig`), con la
 * empresa que transporta en lugar de una fija.
 */
export function terminos(empresa: string): string[] {
  return [
    `La empresa ${empresa} se excluye de toda responsabilidad penal o jurídica por irregularidades con la condición legal de la encomienda así como por requisas de autoridades avaladas. Ninguna de las circunstancias anteriores compete ni se relaciona con los servicios ni deberes de la empresa, por lo tanto la misma queda al margen de todo litigio o reclamación.`,
    'La empresa se compromete a esforzarse para transportar su carga y encomiendas con diligencia.',
    'Su encomienda podrá enviarse sujeto a espacio.',
    'Su embarque, si usted lo presenta 60 minutos antes de los horarios de salida, todavía podrá manifestarse; de lo contrario queda sujeto a espacio en otro horario del mismo día o hasta el día siguiente.',
    'La empresa se responsabiliza hasta 15 días de la recepción de la encomienda; caso contrario, no respondemos por la misma.',
    'La empresa no se responsabiliza por la encomienda mal empacada, a la que se hará referencia en las guías respectivas.',
    'Se responderá únicamente por el doble del valor de lo pagado por el flete en la guía de factura de encomienda.',
    'Es terminantemente prohibido transportar drogas, gases comprimidos, corrosivos, explosivos, materiales oxidantes, venenos líquidos y sólidos.',
    'Por su propia seguridad declare el contenido y adjunte la factura del embalaje cuando sea totalmente sellado de fábrica; de lo contrario se hará el registro respectivo.',
    'La empresa no asume responsabilidad alguna por artículos frágiles o de naturaleza perecedera.',
    'Ningún agente, empleado o representante de la empresa tendrá autorización para alterar, modificar o renunciar a cualquiera de las disposiciones de este contrato.',
    'La encomienda se almacenará 2 días después de su recepción; el tiempo adicional se cobrará a razón de Q 1.00 diario.',
  ]
}
