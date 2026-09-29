/**
 * Ticket de taquilla (80 mm), como el del sistema anterior: datos de la
 * factura electrónica + boletos + código de barras. HTML autónomo (estilos
 * en línea) para imprimir en un iframe sin arrastrar los de la aplicación.
 */
import { svg } from '@/shared/barcode/code128'
import { fecha, hora } from '@/core/venta/modelo'
import type { Comprobante } from '@/core/venta/types'

const esc = (v: unknown) =>
  String(v ?? '').replace(
    /[&<>"']/g,
    (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!,
  )

const SEP = '<div class="sep">**************************</div>'

export function ticketHtml(c: Comprobante): string {
  const f = c.factura
  const pasajeros = c.boletos
    .map(
      (b) =>
        `<tr><td>${esc(b.pasajero ?? c.cliente?.nombre)}</td><td class="r">${b.asiento}</td><td class="r">${esc(b.precio?.texto)}</td></tr>`,
    )
    .join('')
  const factura = f
    ? `<p>Número DTE: ${esc(f.numero)} | Serie DTE: ${esc(f.serie)}</p>
       <p>Certificado: ${esc(fecha(f.fechaCertificacion))} ${esc(hora(f.fechaCertificacion))}</p>
       <p class="small">UUID: ${esc(f.uuid)}</p>`
    : c.estadoFacturacion === 'pendiente'
      ? '<p class="c"><b>FACTURA ELECTRÓNICA PENDIENTE DE CERTIFICAR</b></p>'
      : `<p class="c"><b>${c.cortesia ? 'CORTESÍA' : 'SIN FACTURA ELECTRÓNICA'}</b>${c.agencia ? `<br>Agencia: ${esc(c.agencia)}` : ''}</p>`

  return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Boleto ${esc(c.codigoBarras)}</title>
<style>
  @page { size: 80mm auto; margin: 3mm; }
  * { box-sizing: border-box; }
  body { width: 74mm; margin: 0; font: 12px/1.3 "DejaVu Serif", Georgia, serif; color: #000; }
  p { margin: 2px 0; }
  h1 { font-size: 15px; margin: 0; text-align: center; }
  .c { text-align: center; } .r { text-align: right; } .small { font-size: 10px; word-break: break-all; }
  .sep { text-align: center; letter-spacing: 1px; margin: 3px 0; }
  .g { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 8px; margin: 4px 0; }
  .lbl { font-size: 10px; border-bottom: 1px dotted #000; display: inline-block; }
  table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; }
  .total { text-align: right; font-size: 14px; margin-top: 4px; }
  .bar { text-align: center; margin-top: 8px; } .bar svg { width: 60mm; height: 18mm; }
</style></head><body>
<h1>${esc(c.empresa?.nombre)}</h1>
<p class="c">${c.estacion ? `Estación: ${esc(c.estacion.direccion ?? c.estacion.nombre)}` : c.agencia ? `Agencia: ${esc(c.agencia)}` : ''}</p>
<p class="c">Empresa NIT: ${esc(c.empresa?.nit)}</p>
${SEP}
${factura}
${SEP}
<p class="c"><b>VÁLIDO ÚNICAMENTE PARA LA HORA Y FECHA DE SALIDA.</b></p>
<div class="g">
  <div><span class="lbl">Hora salida:</span><br>${esc(hora(c.recorrido?.salida))}</div>
  <div><span class="lbl">Día:</span><br>${esc(fecha(c.recorrido?.salida))}</div>
  <div><span class="lbl">Sale de:</span><br>${esc(c.origen?.nombre)}</div>
  <div><span class="lbl">Destino:</span><br>${esc(c.destino?.nombre)}</div>
</div>
${SEP}
<div class="g">
  <div><span class="lbl">Facturar a:</span><br>${esc(f?.receptorNombre ?? c.cliente?.nombre)}</div>
  <div class="r"><span class="lbl">Cliente NIT:</span><br>${esc(f?.receptorNit ?? c.cliente?.nit)}</div>
</div>
<table><tr><td><span class="lbl">Pasajeros:</span></td><td class="r"><span class="lbl">Asiento</span></td><td class="r"><span class="lbl">Precio</span></td></tr>${pasajeros}</table>
<p class="total"><span class="lbl">Total: ${esc(c.total.texto)}</span></p>
${f?.certificador ? `<p class="c">Datos del certificador: ${esc(f.certificador)}${f.certificadorNit ? ` NIT ${esc(f.certificadorNit)}` : ''}</p>` : ''}
<div class="sep">------------------------------</div>
<p>Fecha: ${esc(fecha(c.recorrido?.salida))} ${esc(hora(c.recorrido?.salida))}</p>
<p>Asientos: ${esc(c.boletos.map((b) => b.asiento).join(', '))}</p>
${c.boletos.some((b) => b.observacion) ? `<p class="small">Obs.: ${esc(c.boletos.find((b) => b.observacion)?.observacion)}</p>` : ''}
<div class="bar">${svg(c.codigoBarras)}<div>${esc(c.codigoBarras)}</div></div>
<div class="sep">------------------------------</div>
</body></html>`
}

/** Imprime el ticket en un iframe oculto (sin ventanas emergentes). */
export function imprimirTicket(c: Comprobante): void {
  const iframe = document.createElement('iframe')
  iframe.setAttribute('aria-hidden', 'true')
  Object.assign(iframe.style, {
    position: 'fixed',
    right: '0',
    bottom: '0',
    width: '0',
    height: '0',
    border: '0',
  })
  document.body.appendChild(iframe)
  const doc = iframe.contentDocument!
  doc.open()
  doc.write(ticketHtml(c))
  doc.close()
  const imprimir = () => {
    iframe.contentWindow?.focus()
    iframe.contentWindow?.print()
    setTimeout(() => iframe.remove(), 1000)
  }
  if (doc.readyState === 'complete') setTimeout(imprimir, 50)
  else iframe.onload = imprimir
}
