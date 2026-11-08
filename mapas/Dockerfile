# Herramienta de un solo uso que prepara los datos de los mapas (ADR-055): el recorte de
# OpenStreetMap para Valhalla, las teselas PMTiles del mapa base, las fuentes y los sprites.
# La corre `make mapas` (servicio `mapas` de compose.yaml, perfil `mapas`); no queda corriendo.

# CLI de PMTiles (extrae una región del build diario del planeta sin bajarlo entero).
FROM golang:1.25-bookworm AS pmtiles
RUN go install github.com/protomaps/go-pmtiles@v1.31.2

FROM debian:bookworm-slim
RUN apt-get update \
	&& apt-get install -y --no-install-recommends ca-certificates curl osmium-tool \
	&& rm -rf /var/lib/apt/lists/*
COPY --from=pmtiles /go/bin/go-pmtiles /usr/local/bin/pmtiles
# 1777: si este contenedor monta primero el volumen de Valhalla, Docker copia estos permisos al
# volumen y el usuario de Valhalla puede escribir su grafo junto al recorte.
RUN mkdir -p /srv/mapas /valhalla && chmod 1777 /valhalla
COPY preparar.sh /usr/local/bin/preparar-mapas
ENTRYPOINT ["preparar-mapas"]
