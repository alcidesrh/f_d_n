/**
 * Impresión de encomiendas en la térmica de 80 mm (ADR-035), como el legado:
 * el recibo (factura o «Por cobrar», con el contrato que firma el remitente)
 * y una guía por bulto para pegar en el paquete (rotada, con el código de
 * barras). HTML autónomo, igual que el ticket de boletos.
 */
import { svg } from '@/shared/barcode/code128'
import { imprimirHtml } from '@/shared/boleto/ticket'
import { terminos } from '@/core/encomienda/contrato'
import { abreviar, guias } from '@/core/encomienda/reglas'
import type { DatosEncomienda } from '@/core/encomienda/types'
import { fecha, hora } from '@/core/venta/modelo'

const esc = (v: unknown) =>
  String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!)

const SEP = '<div class="sep">**************************</div>'

const ESTILOS = `
  @page { size: 80mm auto; margin: 3mm; }
  * { box-sizing: border-box; }
  body { width: 74mm; margin: 0; font: 12px/1.3 "DejaVu Serif", Georgia, serif; color: #000; }
  p { margin: 2px 0; }
  h1 { font-size: 15px; margin: 0; text-align: center; }
  h2 { font-size: 13px; margin: 6px 0 2px; text-align: center; text-decoration: underline; }
  .c { text-align: center; } .r { text-align: right; } .small { font-size: 10px; word-break: break-all; }
  .sep { text-align: center; letter-spacing: 1px; margin: 3px 0; }
  .g { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 4px 8px; margin: 4px 0; }
  .g > div { overflow-wrap: anywhere; }
  .lbl { font-size: 10px; border-bottom: 1px dotted #000; display: inline-block; }
  table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; overflow-wrap: anywhere; }
  td.r { white-space: nowrap; padding-left: 6px; }
  .total { text-align: right; font-size: 14px; margin-top: 4px; }
  .bar { text-align: center; margin-top: 8px; } .bar svg { width: 50mm; height: 16mm; }
  .firma { margin: 40px 0 0 auto; width: 40mm; border-top: 1px solid #000; text-align: center; font-weight: bold; }
  ol { margin: 4px 0; padding-left: 16px; font-size: 11px; } li { margin-bottom: 3px; }
  .guia { page-break-after: always; break-after: page; }
  .guia:last-child { page-break-after: auto; break-after: auto; }
  .vertical { writing-mode: vertical-rl; transform: rotate(180deg); font-size: 11px; font-weight: bold; line-height: 1.35; height: 105mm; margin: 2mm auto 0; overflow-wrap: anywhere; }
  .lado { display: flex; flex-direction: column; align-items: center; gap: 3mm; margin-top: 4mm; }
  .lado .destino { writing-mode: vertical-rl; transform: rotate(180deg); font-size: 34px; font-weight: bold; letter-spacing: 2px; }
  .lado svg { width: 50mm; height: 16mm; }
`

function encabezado(e: DatosEncomienda): string {
  return `<h1>${esc(e.empresa?.nombre)}</h1>
<p class="c">${e.estacion ? `Estación: ${esc(e.estacion.direccion || e.estacion.nombre)}` : ''}</p>
${e.empresa?.telefono ? `<p class="c">TELÉFONO: ${esc(e.empresa.telefono)}</p>` : ''}
<p class="c">Empresa NIT: ${esc(e.empresa?.nit)}</p>`
}

/** Lo que dice el documento sobre el cobro: la factura, contingencia, por cobrar o sin cobro. */
function documento(e: DatosEncomienda): string {
  const f = e.factura
  if (f) {
    return `<p>Número DTE: ${esc(f.numero)} | Serie DTE: ${esc(f.serie)}</p>
<p>Certificado: ${esc(fecha(f.fechaCertificacion))} ${esc(hora(f.fechaCertificacion))}</p>
<p class="small">UUID: ${esc(f.uuid)}</p>
<p>Facturar a: ${esc(f.receptorNombre)} · NIT ${esc(f.receptorNit)}</p>${f.anulada ? '<p class="c"><b>FACTURA ANULADA</b></p>' : ''}`
  }
  if (e.contingencia) return `<p class="c"><b>DOCUMENTO EN CONTINGENCIA</b>${e.numeroAcceso ? `<br>Número de acceso: ${esc(e.numeroAcceso)}` : ''}<br>La factura electrónica se certificará después.</p>`
  if (e.modalidad === 'interna') return '<p class="c"><b>ENVÍO INTERNO · SIN COBRO</b></p>'
  return ''
}

function totalTexto(e: DatosEncomienda): string {
  if (e.modalidad === 'interna') return 'Sin cobro'
  return `${e.porCobrar ? 'Por cobrar' : 'Total'}: ${e.total.texto}`
}

/** Recibo de la encomienda (adjuntos 1 y 2 del legado). Con `contrato`, el contrato y los términos que firma el remitente. */
export function reciboHtml(e: DatosEncomienda, contrato = true): string {
  const articulos = e.articulos
    .map((a) => `<tr><td>ID: ${esc(e.codigo)}. ${esc(a.resumen)}</td><td class="r">${a.cantidad}</td><td class="r">${e.modalidad === 'interna' ? '' : esc(a.importe.texto)}</td></tr>`)
    .join('')
  const certificador = e.factura?.certificador ? `<p class="c">Datos del Certificador: ${esc(e.factura.certificador)}${e.factura.certificadorNit ? ` NIT ${esc(e.factura.certificadorNit)}` : ''}</p>` : ''
  const firma = contrato
    ? `<div class="sep">------------------------------</div>
<h2>Contrato Legal</h2>
<p>Yo <b>${esc(e.remitente.nombre)}</b> acepto los términos y condiciones enumerados en este contrato y firmo mi vinculación:</p>
<div class="firma">Firma del cliente</div>
<h2>Términos y Condiciones</h2>
<ol>${terminos(e.empresa?.nombre ?? 'transportista')
        .map((t) => `<li>${esc(t)}</li>`)
        .join('')}</ol>`
    : ''

  return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Encomienda ${esc(e.codigo)}</title><style>${ESTILOS}</style></head><body>
${e.estado === 'anulada' ? '<h1>*** ANULADA ***</h1>' : ''}
${encabezado(e)}
${SEP}
${documento(e)}
<div class="g">
  <div><span class="lbl">Recibida:</span><br>${esc(fecha(e.creada))} ${esc(hora(e.creada))}</div>
  <div><span class="lbl">Tipo:</span><br>${esc(e.modalidadEtiqueta)}</div>
  <div><span class="lbl">Sale de:</span><br>${esc(e.origen.nombre)}</div>
  <div><span class="lbl">Destino:</span><br>${esc(e.destino.nombre)}</div>
  <div><span class="lbl">Remitente:</span><br>${esc(e.remitente.nombre)}</div>
  <div><span class="lbl">Destinatario:</span><br>${esc(e.destinatario.nombre)}${e.destinatario.telefono ? `<br>Tel. ${esc(e.destinatario.telefono)}` : ''}</div>
</div>
<table><tr><td><span class="lbl">Encomiendas</span></td><td class="r"><span class="lbl">Cant</span></td><td class="r"></td></tr>${articulos}</table>
${e.descuento.centavos > 0 && e.modalidad !== 'interna' ? `<p class="r">Descuento (${e.bultos} bultos): −${esc(e.descuento.texto)}</p>` : ''}
<div class="sep">------------------------------</div>
<p class="total"><span class="lbl">${esc(totalTexto(e))}</span></p>
${e.observacion ? `<p class="small">Obs.: ${esc(e.observacion)}</p>` : ''}
<div class="bar">${svg(e.codigo)}<div>${esc(e.codigo)}</div></div>
${certificador}
${firma}
${SEP}
</body></html>`
}

/** Una guía por bulto (adjunto 3 del legado): se pega al paquete y la conserva el empleado. */
export function guiasHtml(e: DatosEncomienda): string {
  const paginas = guias(e)
    .map(
      (g) => `<section class="guia">
<h1>DOCUMENTO GUIA</h1>
${SEP}
<div class="vertical">
  Id Encomienda: ${esc(e.codigo)} Nro ${g.numero} de ${g.total}<br>
  Tipo: ${esc(e.porCobrar ? 'Por cobrar' : e.modalidadEtiqueta)}<br>
  Fecha registro: ${esc(fecha(e.creada))} ${esc(hora(e.creada))}<br>
  Usuario: ${esc(e.usuario)}<br>
  Empresa: ${esc(e.empresa?.nombre)}<br>
  Contenido: ${esc(g.articulo)}<br>
  Remitente: ${esc(e.remitente.nombre)}<br>
  Destinatario: ${esc(e.destinatario.nombre)}${e.destinatario.telefono ? ` · ${esc(e.destinatario.telefono)}` : ''}<br>
  ${e.ruta ? `Ruta: ${esc(e.ruta.nombre)}<br>` : ''}Estaciones: ${esc(e.origen.nombre)} / ${esc(e.destino.nombre)}${e.modalidad === 'con_boleto' ? '<br>Encomienda acompañada' : ''}
</div>
<div class="lado">
  <div class="destino">${esc(e.destino.alias ?? abreviar(e.destino.nombre))}</div>
  <div class="c">${svg(e.codigo)}<div>${esc(e.codigo)}</div></div>
</div>
<div class="sep">------------------------------</div>
</section>`,
    )
    .join('')

  return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Guías ${esc(e.codigo)}</title><style>${ESTILOS}</style></head><body>${paginas}</body></html>`
}


export const imprimirRecibo = (e: DatosEncomienda, contrato = true) => imprimirHtml(reciboHtml(e, contrato))

export const imprimirGuias = (e: DatosEncomienda) => imprimirHtml(guiasHtml(e))

/** Recibo y guías, uno tras otro (al registrar). */
export async function imprimirEncomienda(e: DatosEncomienda, contrato = true): Promise<void> {
  await imprimirRecibo(e, contrato)
  await imprimirGuias(e)
}
