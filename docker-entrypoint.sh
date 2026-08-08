#!/bin/sh
set -e

# SQLite is bind-mounted from the host as a single file (rag.sqlite).
# If the host file is missing, Docker silently creates a directory at the
# mount point instead, which breaks SQLite. Catch that case with a clear error.
if [ -d /app/rag.sqlite ]; then
    echo "ERROR: /app/rag.sqlite is a directory, not a file." >&2
    echo "The host-side file is missing. Create it first and restart:" >&2
    echo "    docker compose down" >&2
    echo "    touch rag.sqlite" >&2
    echo "    docker compose up -d" >&2
    exit 1
fi

# Idempotent: creates tables/indexes if the database is empty.
php bin/setup.php

exec "$@"
