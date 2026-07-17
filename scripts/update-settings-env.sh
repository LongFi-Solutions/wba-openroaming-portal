#!/bin/bash

ENV_FILE="/var/www/openroaming/.env"

if [ "$#" -eq 3 ]; then
    JWT_PASSPHRASE=""
    TRUSTED_PROXIES="$1"
    TURNSTILE_KEY="$2"
    TURNSTILE_SECRET="$3"
elif [ "$#" -eq 4 ]; then
    JWT_PASSPHRASE="$1"
    TRUSTED_PROXIES="$2"
    TURNSTILE_KEY="$3"
    TURNSTILE_SECRET="$4"
else
    echo "Erro: Número incorreto de argumentos."
    echo "Uso: $0 [JWT_PASSPHRASE] \"TRUSTED_PROXIES\" \"TURNSTILE_KEY\" \"TURNSTILE_SECRET\""
    exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
    echo "Erro: O ficheiro $ENV_FILE não existe."
    exit 1
fi

set_env() {
    local KEY="$1"
    local VALUE="$2"

    if grep -q "^$KEY=" "$ENV_FILE"; then
        sed -i "s|^$KEY=.*|$KEY=\"$VALUE\"|" "$ENV_FILE"
    else
        echo "$KEY=\"$VALUE\"" >> "$ENV_FILE"
    fi

    echo "Atualizado: $KEY"
}

if [ "$#" -eq 4 ]; then
    set_env "JWT_PASSPHRASE" "$JWT_PASSPHRASE"
else
    echo "JWT_PASSPHRASE omitida (não alterada)"
fi

set_env "TRUSTED_PROXIES" "$TRUSTED_PROXIES"
set_env "TURNSTILE_KEY" "$TURNSTILE_KEY"
set_env "TURNSTILE_SECRET" "$TURNSTILE_SECRET"

echo "Atualização concluída com sucesso."