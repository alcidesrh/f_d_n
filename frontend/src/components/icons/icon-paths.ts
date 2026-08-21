/**
 * Hand-authored stroke icon set (no external icon-font/SVG dependency).
 * Each entry is the inner markup of a 24x24 stroked SVG.
 */
export const ICON_PATHS: Record<string, string> = {
  menu: '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
  minus: '<line x1="5" y1="12" x2="19" y2="12"/>',
  x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
  sun: '<circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4.5"/><line x1="12" y1="19.5" x2="12" y2="22"/><line x1="4.2" y1="4.2" x2="6" y2="6"/><line x1="18" y1="18" x2="19.8" y2="19.8"/><line x1="2" y1="12" x2="4.5" y2="12"/><line x1="19.5" y1="12" x2="22" y2="12"/><line x1="4.2" y1="19.8" x2="6" y2="18"/><line x1="18" y1="6" x2="19.8" y2="4.2"/>',
  moon: '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/>',
  palette:
    '<circle cx="12" cy="12" r="9"/><circle cx="8.2" cy="10.5" r="1.15" fill="currentColor" stroke="none"/><circle cx="12" cy="8" r="1.15" fill="currentColor" stroke="none"/><circle cx="15.8" cy="10.5" r="1.15" fill="currentColor" stroke="none"/><circle cx="10" cy="15" r="1.15" fill="currentColor" stroke="none"/>',
  bell: '<path d="M18 8.5a6 6 0 0 0-12 0c0 6.5-2.5 8.5-2.5 8.5h17S18 15 18 8.5z"/><path d="M13.7 20.5a2 2 0 0 1-3.4 0"/>',
  search: '<circle cx="11" cy="11" r="7.5"/><line x1="21" y1="21" x2="16.4" y2="16.4"/>',
  user: '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c1.4-3.6 4.3-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/>',
  logout:
    '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
  grid: '<rect x="3.5" y="3.5" width="7.5" height="7.5" rx="1.7"/><rect x="13" y="3.5" width="7.5" height="7.5" rx="1.7"/><rect x="3.5" y="13" width="7.5" height="7.5" rx="1.7"/><rect x="13" y="13" width="7.5" height="7.5" rx="1.7"/>',
  bus: '<rect x="3" y="5.5" width="18" height="11" rx="2.4"/><line x1="3" y1="10.5" x2="21" y2="10.5"/><line x1="7.5" y1="5.5" x2="7.5" y2="10.5"/><line x1="16.5" y1="5.5" x2="16.5" y2="10.5"/><circle cx="7.5" cy="18.5" r="1.6" fill="currentColor" stroke="none"/><circle cx="16.5" cy="18.5" r="1.6" fill="currentColor" stroke="none"/>',
  route:
    '<circle cx="6" cy="6" r="2.2"/><circle cx="18" cy="18" r="2.2"/><path d="M6 8.2v3.3a4 4 0 0 0 4 4h4a4 4 0 0 1 4 4"/>',
  mappin:
    '<path d="M12 21s-6.5-6-6.5-11A6.5 6.5 0 0 1 18.5 10c0 5-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
  users:
    '<circle cx="9" cy="8.2" r="3.2"/><path d="M3.2 19c1.1-3.1 3.2-4.7 5.8-4.7s4.7 1.6 5.8 4.7"/><circle cx="17" cy="8.6" r="2.4"/><path d="M15.3 14.6c1.9.4 3.3 1.8 4.1 4.1"/>',
  ticket:
    '<path d="M3.5 8.2a1.8 1.8 0 0 1 1.8-1.8h13.4a1.8 1.8 0 0 1 1.8 1.8v2.3a1.9 1.9 0 0 0 0 3.6v2.3a1.8 1.8 0 0 1-1.8 1.8H5.3a1.8 1.8 0 0 1-1.8-1.8v-2.3a1.9 1.9 0 0 0 0-3.6z"/><line x1="9.5" y1="6.5" x2="9.5" y2="17.5" stroke-dasharray="2 2.4"/>',
  barchart:
    '<line x1="5" y1="20" x2="5" y2="11"/><line x1="12" y1="20" x2="12" y2="5"/><line x1="19" y1="20" x2="19" y2="14"/>',
  alert:
    '<path d="M12 3.5 21.5 20h-19z"/><line x1="12" y1="9.5" x2="12" y2="14"/><circle cx="12" cy="17" r=".9" fill="currentColor" stroke="none"/>',
  settings:
    '<circle cx="12" cy="12" r="3.2"/><path d="M12 3.5v2.3M12 18.2v2.3M20.5 12h-2.3M5.8 12H3.5M17.8 6.2l-1.6 1.6M7.8 16.2l-1.6 1.6M17.8 17.8l-1.6-1.6M7.8 7.8 6.2 6.2"/>',
  calendar:
    '<rect x="3.5" y="5" width="17" height="15.5" rx="2.2"/><line x1="3.5" y1="9.7" x2="20.5" y2="9.7"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/>',
  check: '<circle cx="12" cy="12" r="9"/><polyline points="8 12.3 11 15.3 16.3 9"/>',
  clock: '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12.3 15.5 14.3"/>',
  maximize:
    '<polyline points="4 9 4 4 9 4"/><polyline points="15 4 20 4 20 9"/><polyline points="20 15 20 20 15 20"/><polyline points="9 20 4 20 4 15"/>',
  chevrondown: '<polyline points="6 9 12 15.5 18 9"/>',
  chevronsleft: '<polyline points="12.5 17 7 12 12.5 7"/><polyline points="19 17 13.5 12 19 7"/>',
  chevronsright: '<polyline points="11.5 17 17 12 11.5 7"/><polyline points="5 17 10.5 12 5 7"/>',
  plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
  filter: '<path d="M4 5h16l-6.2 7.4v5.4l-3.6 2v-7.4z"/>',
  wrench:
    '<path d="M14.5 6.5a4 4 0 0 0-5.4 4.7L4 16.3V20h3.7l5.1-5.1a4 4 0 0 0 4.7-5.4l-2.6 2.6-2-2z"/>',
  trend: '<polyline points="4 15 9.5 9.5 13.5 13.5 20 6"/><polyline points="14.5 6 20 6 20 11.5"/>',
  gauge:
    '<circle cx="12" cy="13" r="8.3"/><line x1="12" y1="13" x2="15.5" y2="9.3"/><line x1="6.2" y1="6.5" x2="7.5" y2="7.8"/><line x1="17.8" y1="6.5" x2="16.5" y2="7.8"/>',
  table:
    '<rect x="3.5" y="4" width="17" height="16" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="9.5" y1="4" x2="9.5" y2="20"/><line x1="14.5" y1="4" x2="14.5" y2="20"/>',
}

export type IconName = keyof typeof ICON_PATHS
