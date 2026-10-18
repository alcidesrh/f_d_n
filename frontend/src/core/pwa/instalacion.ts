/**
 * Instalación de la app (PWA). Puro: el estado y los eventos del navegador
 * viven en `app/pwa.ts`.
 */

/** Texto para instalar en iPhone/iPad: Safari no ofrece el aviso de instalación. */
export const GUIA_IOS = 'En iPhone o iPad: toque Compartir y luego «Agregar a pantalla de inicio».'

/**
 * iPhone o iPad sin la app instalada: hay que explicar cómo instalarla. El
 * iPad se presenta como Mac; lo delata la pantalla táctil.
 */
export function pideGuiaIos(userAgent: string, puntosTactiles: number, instalada: boolean): boolean {
  if (instalada) return false
  return /iPhone|iPad|iPod/.test(userAgent) || (/Macintosh/.test(userAgent) && puntosTactiles > 1)
}
