#!/usr/bin/env bash
# Conecta o desconecta el stack local (contenedores + PHP del host) a la base de datos
# de producción en el VPS.
#
#   scripts/db-remota.sh on      abre el túnel SSH y apunta el stack local a la base remota
#   scripts/db-remota.sh off     cierra el túnel SSH y vuelve a la base local (compose)
#   scripts/db-remota.sh         muestra el estado: on u off
#
# Cómo funciona:
#   - Los contenedores (backend, worker, programador, worker-pesadas) reciben DATABASE_URL
#     desde compose.yaml, y esa variable gana sobre backend/.env.local. Por eso "on" genera
#     compose.db-remota.yaml (override con la URL remota) y un .env en la raíz con
#     COMPOSE_FILE, para que `docker compose` y `make` también lo usen.
#   - El PHP del host usa backend/.env.local (bloque delimitado que este script escribe).
#   - El túnel escucha en la IP del gateway de la red de Docker, no en toda la LAN.
#   - La contraseña se lee del contenedor remoto en cada "on"; no se imprime.
#
# ADVERTENCIA: la base remota es la de producción. Con "on", todo lo que escriba el
# stack local (migraciones, pruebas, tareas) escribe en producción.

set -euo pipefail

REMOTE="root@194.113.67.177"
REMOTE_CONTAINER="database"
LOCAL_PORT="15432"
LOCAL_DB_PORT="5432"
LOCAL_DB_USER="app"
LOCAL_DB_PASS='!ChangeMe!'
SERVICIOS=(backend worker programador worker-pesadas)
NETWORK="f_d_n_default"
SOCKET="${XDG_RUNTIME_DIR:-/tmp}/fdn-db-remota-${LOCAL_PORT}.sock"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
ENV_FILE="$ROOT/backend/.env.local"
OVERRIDE="$ROOT/compose.db-remota.yaml"
ROOT_ENV="$ROOT/.env"
BEGIN="# >>> db-remota (scripts/db-remota.sh) >>>"
END="# <<< db-remota <<<"
COMPOSE_LINE='COMPOSE_FILE=compose.yaml:compose.override.yaml:compose.db-remota.yaml'

ssh_opts=(-o BatchMode=yes -o ConnectTimeout=10)

# ---------- helpers ----------

urlencode() {
    local s="$1" out="" c i
    for ((i = 0; i < ${#s}; i++)); do
        c="${s:i:1}"
        case "$c" in
            [a-zA-Z0-9.~_-]) out+="$c" ;;
            *) printf -v c '%%%02X' "'$c"; out+="$c" ;;
        esac
    done
    printf '%s' "$out"
}

tunel_activo() {
    (exec 3<>"/dev/tcp/127.0.0.1/$LOCAL_PORT") 2>/dev/null
}

cerrar_tunel() {
    if [ -S "$SOCKET" ]; then
        ssh -S "$SOCKET" -O exit "$REMOTE" 2>/dev/null || true
    fi
    pkill -f "ssh .*-L [^ ]*${LOCAL_PORT}:" 2>/dev/null || true
    rm -f "$SOCKET"
    sleep 1
}

# Quita el bloque delimitado de backend/.env.local.
quitar_bloque() {
    [ -f "$ENV_FILE" ] || return 0
    local tmp
    tmp="$(mktemp)"
    awk -v b="$BEGIN" -v e="$END" '
        index($0, b) == 1 { skip = 1; next }
        index($0, e) == 1 { skip = 0; next }
        !skip { print }
    ' "$ENV_FILE" > "$tmp"
    cat "$tmp" > "$ENV_FILE"
    rm -f "$tmp"
}

# Escribe el bloque de backend/.env.local (PHP del host) apuntando a host:puerto.
escribir_bloque() {
    local modo="$1" host="$2" puerto="$3" user="$4" pass="$5" db="$6" upass
    upass="$(urlencode "$pass")"
    quitar_bloque
    {
        printf '%s\n' "$BEGIN"
        printf '# modo: %s\n' "$modo"
        printf 'DATABASE_URL="postgresql://%s:%s@%s:%s/%s?serverVersion=16&charset=utf8"\n' \
            "$user" "$upass" "$host" "$puerto" "$db"
        printf 'LOCK_DSN="postgresql+advisory://%s:%s@%s:%s/%s"\n' \
            "$user" "$upass" "$host" "$puerto" "$db"
        printf '%s\n' "$END"
    } >> "$ENV_FILE"
}

modo_env_local() {
    [ -f "$ENV_FILE" ] && awk -v b="$BEGIN" 'index($0,b)==1{f=1;next} f&&/^# modo: /{sub(/^# modo: /,"");print;exit}' "$ENV_FILE"
}

# Modo real que usan los contenedores (lo que de verdad importa).
modo_contenedores() {
    local url
    url="$(docker exec backend printenv DATABASE_URL 2>/dev/null)" || { echo "sin contenedor"; return; }
    case "$url" in
        *@database:*) echo "local" ;;
        *@127.0.0.1:*|*@localhost:*|*@host.docker.internal:*|*@172.*:*) echo "remoto" ;;
        *) echo "desconocido" ;;
    esac
}

gateway_red() {
    docker network inspect "$NETWORK" -f '{{range .IPAM.Config}}{{.Gateway}}{{end}}' 2>/dev/null
}

recrear_servicios() {
    # Recrea los servicios para que tomen DATABASE_URL del override vigente (o del compose base).
    docker compose up -d "${SERVICIOS[@]}" >/dev/null
}

# ---------- acciones ----------

estado() {
    local cont env_local tun gw
    cont="$(modo_contenedores)"
    env_local="$(modo_env_local || true)"
    tun="cerrado"; gw="$(gateway_red)"; [ -n "$gw" ] && (exec 3<>"/dev/tcp/$gw/$LOCAL_PORT") 2>/dev/null && tun="abierto ($gw:$LOCAL_PORT)"
    if [ "$cont" = "remoto" ]; then echo "on"; else echo "off"; fi
    echo "  contenedores (backend/worker/programador): $cont"
    echo "  PHP del host (backend/.env.local):         ${env_local:-sin configurar}"
    echo "  túnel SSH: $tun"
}

conectar() {
    echo "Leyendo datos del contenedor remoto..."
    local remote_ip db user pass gw
    remote_ip="$(ssh "${ssh_opts[@]}" "$REMOTE" \
        "docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $REMOTE_CONTAINER")"
    [ -n "$remote_ip" ] || { echo "No se encontró la IP de $REMOTE_CONTAINER" >&2; exit 1; }
    db="$(ssh "${ssh_opts[@]}" "$REMOTE" "docker exec $REMOTE_CONTAINER printenv POSTGRES_DB")"
    user="$(ssh "${ssh_opts[@]}" "$REMOTE" "docker exec $REMOTE_CONTAINER printenv POSTGRES_USER")"
    pass="$(ssh "${ssh_opts[@]}" "$REMOTE" "docker exec $REMOTE_CONTAINER printenv POSTGRES_PASSWORD")"
    [ -n "$pass" ] || { echo "No se pudo leer POSTGRES_PASSWORD remoto" >&2; exit 1; }

    gw="$(gateway_red)"
    [ -n "$gw" ] || { echo "No existe la red $NETWORK: levanta el stack con 'make dev' primero" >&2; exit 1; }

    # Túnel en la IP del gateway (alcanzable desde los contenedores, no desde la LAN).
    cerrar_tunel
    echo "Abriendo túnel $gw:$LOCAL_PORT -> $remote_ip:5432..."
    ssh -f -N -M -S "$SOCKET" "${ssh_opts[@]}" -o ExitOnForwardFailure=yes \
        -L "$gw:$LOCAL_PORT:$remote_ip:5432" "$REMOTE"
    for _ in 1 2 3 4 5; do (exec 3<>"/dev/tcp/$gw/$LOCAL_PORT") 2>/dev/null && break; sleep 1; done
    (exec 3<>"/dev/tcp/$gw/$LOCAL_PORT") 2>/dev/null || { echo "El túnel no levantó en $gw:$LOCAL_PORT" >&2; exit 1; }

    local upass
    upass="$(urlencode "$pass")"

    # Override de compose para los contenedores y .env de la raíz que lo activa.
    cat > "$OVERRIDE" <<EOF
# Generado por scripts/db-remota.sh on — NO editar. Lo borra "off".
services:
EOF
    for s in "${SERVICIOS[@]}"; do
        cat >> "$OVERRIDE" <<EOF
  $s:
    environment:
      DATABASE_URL: "postgresql://$user:$upass@$gw:$LOCAL_PORT/$db?serverVersion=16&charset=utf8"
      LOCK_DSN: "postgresql+advisory://$user:$upass@$gw:$LOCAL_PORT/$db"
EOF
    done
    if [ -f "$ROOT_ENV" ] && ! grep -qxF "$COMPOSE_LINE" "$ROOT_ENV"; then
        echo "Ya existe $ROOT_ENV con otro contenido; no lo toco. Revísalo." >&2; exit 1
    fi
    printf '%s\n' "$COMPOSE_LINE" > "$ROOT_ENV"

    escribir_bloque "remoto" "$gw" "$LOCAL_PORT" "$user" "$pass" "$db"
    echo "Recreando contenedores del stack..."
    recrear_servicios
    echo "Listo: stack local apunta a la base remota (producción)."
}

desconectar() {
    cerrar_tunel
    rm -f "$OVERRIDE"
    [ -f "$ROOT_ENV" ] && grep -qxF "$COMPOSE_LINE" "$ROOT_ENV" && rm -f "$ROOT_ENV"
    escribir_bloque "local" "127.0.0.1" "$LOCAL_DB_PORT" "$LOCAL_DB_USER" "$LOCAL_DB_PASS" "app"
    echo "Recreando contenedores del stack..."
    recrear_servicios
    echo "Listo: stack local apunta a la base local (compose, 127.0.0.1:$LOCAL_DB_PORT)."
}

case "${1:-}" in
    on) conectar ;;
    off) desconectar ;;
    "") estado ;;
    *) echo "Uso: $(basename "$0") [on|off]" >&2; exit 2 ;;
esac
