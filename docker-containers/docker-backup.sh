#!/usr/bin/env bash

set -euo pipefail

REMOTE_PATH="/var/www/html/docker"
DEST_BASE="$HOME/Documents/Freelance/Homelab Template/docker-containers"
HOSTS=("saturn" "thera")

mkdir -p "$DEST_BASE"

for HOST in "${HOSTS[@]}"; do
    echo "==== Processing $HOST ===="

    DEST="$DEST_BASE/$HOST"
    mkdir -p "$DEST"

    ssh "lbailey@$HOST" "
        cd $REMOTE_PATH && \
        find . -type f \( \
            -name '.env' -o \
            -name '.env.example' -o \
            -name 'docker-compose.yml' \
        \) -print0 | \
        tar --null -T - -czf -
    " | tar -xzf - -C "$DEST"

    echo "Finished $HOST → $DEST"
done

echo "All done."
