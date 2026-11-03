/** Marcador del bus en los mapas de seguimiento (en vivo y reproducción): círculo del color de la empresa con una flecha según el rumbo. */
import L from 'leaflet'
import './marcador.css'

export function iconoBus(color: string, rumbo: number, { activo = false, quieto = false }: { activo?: boolean; quieto?: boolean } = {}): L.DivIcon {
  return L.divIcon({
    className: 'bus-marker',
    iconSize: [34, 34],
    iconAnchor: [17, 17],
    html: `<div class="bus-marker-body${activo ? ' is-active' : ''}${quieto ? ' is-still' : ''}" style="--c:${color}"><svg viewBox="0 0 24 24" width="22" height="22" style="transform:rotate(${rumbo.toFixed(0)}deg)" aria-hidden="true"><path d="M12 2 19 21 12 17 5 21Z" fill="#fff"/></svg></div>`,
  })
}
