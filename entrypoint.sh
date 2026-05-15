#!/bin/sh
set -e

chown -R www-data:www-data \
    /var/www/openroaming/signing-keys/ \
    /var/www/openroaming/config/jwt/

for f in \
    /var/www/openroaming/signing-keys/privkey.pem \
    /var/www/openroaming/signing-keys/windowsKey.pfx; do
    [ -f "$f" ] && chmod 600 "$f"
done

exec "$@"
