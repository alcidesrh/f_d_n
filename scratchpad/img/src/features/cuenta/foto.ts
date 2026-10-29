/**
 * Prepara la foto de perfil en el navegador: recorte cuadrado centrado y 256 px
 * en JPEG (unos 20 KB), así el servidor no necesita procesar imágenes.
 */
const LADO = 256
const CALIDAD = 0.88

export async function recortarCuadrada(archivo: File): Promise<Blob> {
  if (!archivo.type.startsWith('image/')) throw new Error('Elija un archivo de imagen.')
  let imagen: ImageBitmap
  try {
    imagen = await createImageBitmap(archivo, { imageOrientation: 'from-image' })
  } catch {
    throw new Error('No se pudo leer la imagen. Use JPG, PNG o WebP.')
  }
  const lado = Math.min(imagen.width, imagen.height)
  const lienzo = document.createElement('canvas')
  lienzo.width = lienzo.height = Math.min(LADO, lado)
  lienzo
    .getContext('2d')
    ?.drawImage(
      imagen,
      (imagen.width - lado) / 2,
      (imagen.height - lado) / 2,
      lado,
      lado,
      0,
      0,
      lienzo.width,
      lienzo.height,
    )
  imagen.close()
  const blob = await new Promise<Blob | null>((r) => lienzo.toBlob(r, 'image/jpeg', CALIDAD))
  if (!blob) throw new Error('No se pudo preparar la imagen.')
  return blob
}
