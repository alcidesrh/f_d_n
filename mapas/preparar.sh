#!/bin/sh
# Prepara los datos de los mapas (ADR-055). Lo corre `make mapas` con la imagen de mapas/Dockerfile.
#
# Deja en /srv/mapas (volumen `mapas_data`, lo sirve Caddy en /mapas/):
#   region-AAAAMMDD.pmtiles  teselas vectoriales del mapa base, recortadas del build diario de Protomaps
#   fuentes/, sprites/       lo que pide el estilo del mapa base (copiado de protomaps/basemaps-assets)
#   mapa.json                el manifiesto: qué archivo de teselas pedir y de qué fecha son los datos
# y en /valhalla (volumen `valhalla_data`, el /custom_files de Valhalla):
#   region.osm.pbf           el recorte de OpenStreetMap del que Valhalla construye su grafo al reiniciar
#
# La región por defecto es Guatemala con sus corredores: Belice, El Salvador, el occidente de Honduras
# y el sur de México hasta Chetumal (hay enclaves en todos ellos). Se cambia con variables de entorno:
#   MAPAS_BBOX       min_lon,min_lat,max_lon,max_lat (la misma caja para las teselas y para Valhalla)
#   MAPAS_EXTRACTOS  rutas de Geofabrik (sin -latest.osm.pbf) que cubren la caja, separadas por espacios
#   MAPAS_MAXZOOM    zoom máximo de las teselas (cada nivel más duplica más o menos el tamaño)
set -eu

BBOX="${MAPAS_BBOX:--92.6,13.0,-86.8,18.8}"
EXTRACTOS="${MAPAS_EXTRACTOS:-central-america/guatemala central-america/belize central-america/el-salvador central-america/honduras north-america/mexico}"
MAXZOOM="${MAPAS_MAXZOOM:-15}"

GEOFABRIK="https://download.geofabrik.de"
PROTOMAPS="https://build.protomaps.com"
ASSETS="https://github.com/protomaps/basemaps-assets/archive/refs/heads/main.tar.gz"
SALIDA=/srv/mapas
VALHALLA=/valhalla
TRABAJO=$(mktemp -d)
trap 'rm -rf "$TRABAJO"' EXIT

paso() { printf '\n== %s\n' "$*"; }
bajar() { curl -fL --retry 3 --retry-delay 5 -sS -o "$2" "$1"; }

cd "$TRABAJO"

paso "Teselas del mapa base (Protomaps, bbox $BBOX, zoom 0-$MAXZOOM)"
# Builds diarios del planeta: se usa el más reciente de los últimos diez días.
build=""
for d in 0 1 2 3 4 5 6 7 8 9 10; do
	dia=$(date -u -d "-$d day" +%Y%m%d)
	if curl -sfI "$PROTOMAPS/$dia.pmtiles" >/dev/null; then
		build=$dia
		break
	fi
done
[ -n "$build" ] || { echo "No hay un build de Protomaps de los últimos diez días en $PROTOMAPS" >&2; exit 1; }
teselas="region-$build.pmtiles"
echo "Build $build"
pmtiles extract "$PROTOMAPS/$build.pmtiles" "$teselas" --bbox="$BBOX" --maxzoom="$MAXZOOM" --download-threads=8
ls -lh "$teselas"

paso "Fuentes y sprites (protomaps/basemaps-assets)"
bajar "$ASSETS" assets.tar.gz
mkdir assets publicar publicar/fuentes publicar/sprites
tar -xzf assets.tar.gz -C assets --strip-components=1
# Solo las familias que usa el estilo (@protomaps/basemaps): regular, medium e italic.
for familia in "Noto Sans Regular" "Noto Sans Medium" "Noto Sans Italic"; do
	cp -r "assets/fonts/$familia" publicar/fuentes/
done
cp -r assets/sprites/v4 publicar/sprites/

paso "Recorte de OpenStreetMap para Valhalla (Geofabrik: $EXTRACTOS)"
osm=""
for extracto in $EXTRACTOS; do
	nombre=$(basename "$extracto")
	echo "$extracto"
	bajar "$GEOFABRIK/$extracto-latest.osm.pbf" pais.osm.pbf
	# Fecha de los datos: la del primer extracto (Geofabrik los actualiza todos el mismo día).
	[ -n "$osm" ] || osm=$(osmium fileinfo -g header.option.osmosis_replication_timestamp pais.osm.pbf 2>/dev/null || true)
	# Cada país se recorta a la caja antes de juntarlos: México entero no hace falta en disco.
	osmium extract --bbox "$BBOX" --strategy complete_ways pais.osm.pbf -o "recorte-$nombre.osm.pbf" --overwrite
	rm -f pais.osm.pbf
done
# Los recortes se solapan en las fronteras: merge deja una sola copia de cada objeto.
osmium merge recorte-*.osm.pbf -o region.osm.pbf --overwrite
ls -lh region.osm.pbf

paso "Publicar"
# Primero los archivos y al final el manifiesto: el navegador lee el manifiesto para saber qué teselas pedir.
mv "$teselas" "$SALIDA/$teselas"
rm -rf "$SALIDA/fuentes.nuevo" "$SALIDA/sprites.nuevo"
mv publicar/fuentes "$SALIDA/fuentes.nuevo"
mv publicar/sprites "$SALIDA/sprites.nuevo"
rm -rf "$SALIDA/fuentes" "$SALIDA/sprites"
mv "$SALIDA/fuentes.nuevo" "$SALIDA/fuentes"
mv "$SALIDA/sprites.nuevo" "$SALIDA/sprites"
fecha=$(echo "$build" | sed 's/^\(....\)\(..\)\(..\)$/\1-\2-\3/')
cat >"$SALIDA/mapa.json.nuevo" <<JSON
{
  "teselas": "$teselas",
  "datos": "$fecha",
  "osm": "$osm",
  "bbox": [$BBOX],
  "preparado": "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
}
JSON
mv "$SALIDA/mapa.json.nuevo" "$SALIDA/mapa.json"
# Se conserva la anterior (una pestaña abierta puede seguir leyéndola); las más viejas se borran.
ls -1t "$SALIDA"/region-*.pmtiles | tail -n +3 | xargs -r rm -f

# Un solo .pbf en la carpeta de Valhalla: al reiniciar, si cambió (md5), reconstruye su grafo.
cp region.osm.pbf "$VALHALLA/region.osm.pbf.nuevo"
find "$VALHALLA" -maxdepth 1 -name '*.pbf' ! -name 'region.osm.pbf.nuevo' -delete
mv "$VALHALLA/region.osm.pbf.nuevo" "$VALHALLA/region.osm.pbf"

paso "Listo"
cat "$SALIDA/mapa.json"
echo "Valhalla reconstruye su grafo al reiniciar (make mapas lo reinicia); mientras tanto los km por carretera no se calculan."
