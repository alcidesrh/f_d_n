"""Regenera el ícono y la pantalla de arranque de Android desde los de la PWA.

    python3 recursos/iconos.py     (desde movil/; requiere Pillow)
"""
from pathlib import Path
from PIL import Image, ImageDraw

RAIZ = Path(__file__).resolve().parent.parent
PWA = RAIZ.parent / 'frontend' / 'public' / 'pwa'
RES = RAIZ / 'android' / 'app' / 'src' / 'main' / 'res'

placa = Image.open(PWA / 'icono-512.png').convert('RGBA')
enmascarable = Image.open(PWA / 'icono-maskable-512.png').convert('RGBA')
# El dorado del borde del ícono enmascarable: fondo del ícono adaptable y del arranque.
FONDO = enmascarable.getpixel((4, 4))[:3]


def centrada(ancho: int, alto: int, lado: int, fondo=None) -> Image.Image:
    lienzo = Image.new('RGBA', (ancho, alto), (*fondo, 255) if fondo else (0, 0, 0, 0))
    logo = placa.resize((lado, lado), Image.LANCZOS)
    lienzo.paste(logo, ((ancho - lado) // 2, (alto - lado) // 2), logo)
    return lienzo


def redonda(lado: int) -> Image.Image:
    grande = enmascarable.resize((lado * 4, lado * 4), Image.LANCZOS)
    mascara = Image.new('L', grande.size, 0)
    ImageDraw.Draw(mascara).ellipse((0, 0, *grande.size), fill=255)
    grande.putalpha(mascara)
    return grande.resize((lado, lado), Image.LANCZOS)


for densidad, escala in {'mdpi': 1, 'hdpi': 1.5, 'xhdpi': 2, 'xxhdpi': 3, 'xxxhdpi': 4}.items():
    carpeta = RES / f'mipmap-{densidad}'
    lado = round(48 * escala)
    placa.resize((lado, lado), Image.LANCZOS).save(carpeta / 'ic_launcher.png')
    redonda(lado).save(carpeta / 'ic_launcher_round.png')
    # Adaptable: lienzo de 108dp; lo que nunca se recorta es el círculo central de 66dp.
    capa = round(108 * escala)
    centrada(capa, capa, round(60 * escala)).save(carpeta / 'ic_launcher_foreground.png')

for archivo in RES.glob('drawable*/splash.png'):
    ancho, alto = Image.open(archivo).size
    centrada(ancho, alto, round(min(ancho, alto) * 0.45), FONDO).convert('RGB').save(archivo)

color = '#%02X%02X%02X' % FONDO
(RES / 'values' / 'ic_launcher_background.xml').write_text(
    f'<?xml version="1.0" encoding="utf-8"?>\n<resources>\n    <color name="ic_launcher_background">{color}</color>\n</resources>\n'
)
print('fondo', color)
