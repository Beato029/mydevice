#!/bin/sh
# Render imposta $PORT a runtime; default 10000 in locale
PORT="${PORT:-10000}"
DIR="$(cd "$(dirname "$0")" && pwd)"
echo "Avvio MyDevice su porta $PORT (root: $DIR)"
exec php -S 0.0.0.0:"$PORT" -t "$DIR" "$DIR/router.php"
