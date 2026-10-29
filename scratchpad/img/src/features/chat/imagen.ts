/**
 * Reduce las fotos antes de subirlas: lado mayor a 1600 px en JPEG (una foto
 * de celular de 4 MB queda en ~300 KB). Las que ya son chicas, los GIF y lo
 * que el navegador no sabe decodificar se suben tal cual.
 */
import { medidasReducidas } from '@/core/chat/modelo'

const MAX_LADO = 1600
const CALIDAD = 0.82
const SIN_REDUCIR_BYTES = 400 * 1024

export async function prepararArchivo(archivo: File): Promise<{ blob: Blob; nombre: string }> {
  const intacto = { blob: archivo as Blob, nombre: archivo.name }
  if (!archivo.type.startsWith('image/') || archivo.type === 'image/gif' || typeof createImageBitmap === 'undefined') return intacto
  let imagen: ImageBitmap
  try {
    imagen = await createImageBitmap(archivo, { imageOrientation: 'from-image' })
  } catch {
    return intacto
  }
  const { ancho, alto } = medidasReducidas(imagen.width, imagen.height, MAX_LADO)
  if (archivo.size <= SIN_REDUCIR_BYTES && ancho === imagen.width) {
    imagen.close()
    return intacto
  }
  const lienzo = document.createElement('canvas')
  lienzo.width = ancho
  lienzo.height = alto
  lienzo.getContext('2d')?.drawImage(imagen, 0, 0, ancho, alto)
  imagen.close()
  const blob = await new Promise<Blob | null>((r) => lienzo.toBlob(r, 'image/jpeg', CALIDAD))
  if (!blob || blob.size >= archivo.size) return intacto
  return { blob, nombre: archivo.name.replace(/\.[^.]+$/, '') + '.jpg' }
}

/** Tipos que acepta el chat (el backend vuelve a verificar por contenido). */
export const ACEPTA = 'image/*,application/pdf,.pdf,.docx,.xlsx,.txt,.csv'
