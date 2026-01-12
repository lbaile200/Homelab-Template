#!/bin/bash

# Define the directory to check
CHECK_DIR="/home/lucaswbailey/"
# Define your Uptime Kuma Push URL/API Key (replace with your actual URL and API key)
UPTIME_KUMA_PUSH_URL="https://uptime.homelabcabbage.synology.me/api/push/KrjchCwMwB?status=up&msg=OK&ping="

# Attempt to create and remove a temporary file
if touch "$CHECK_DIR/tempfile.tmp" && rm "$CHECK_DIR/tempfile.tmp"; then
  # Success: send a 'up' signal to Uptime Kuma
  curl -m 10 --retry 3 "$UPTIME_KUMA_PUSH_URL?status=up&msg=OK"
else
  # Failure (filesystem likely read-only or full): send a 'down' signal
  curl -m 10 --retry 3 "$UPTIME_KUMA_PUSH_URL?status=down&msg=Filesystem_Read-Only_or_Error"
fi

