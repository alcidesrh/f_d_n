#!/bin/bash

# Verificar si se ejecuta como root
if [ "$(id -u)" -ne 0 ]; then
    echo "Este script debe ejecutarse como root o con sudo."
    exit 1
fi

# Número de comandos a mostrar por usuario (ajuste según necesite; predeterminado: 50)
NUM_COMANDOS=20000000000

# Iterar sobre todos los usuarios en /etc/passwd
for user in $(cut -d: -f1 /etc/passwd); do
    # Obtener el directorio de inicio del usuario
    home=$(getent passwd "$user" | cut -d: -f6)
    
    # Verificar si el directorio de inicio existe y contiene .bash_history
    history_file="$home/.bash_history"
    if [ -d "$home" ] && [ -f "$history_file" ] && [ -r "$history_file" ]; then
        echo "=== Historial de comandos para el usuario: $user (últimos $NUM_COMANDOS comandos, más recientes primero) ==="
       echo "$home" 
        # Mostrar los últimos N comandos (tail) e invertir el orden (tac) para que los más recientes aparezcan primero
        #tail -n "$NUM_COMANDOS" "$history_file"
        
        echo ""  # Línea en blanco para separación
    fi
done
