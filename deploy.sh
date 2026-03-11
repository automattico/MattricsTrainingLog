#!/bin/sh
set -eu

if [ -f ".env.local" ]; then
  # shellcheck disable=SC1091
  . ./.env.local
fi

required_vars="
SFTP_HOST
SFTP_PORT
SFTP_USER
SFTP_REMOTE_DIR
SFTP_PASSWORD
"

for var in $required_vars; do
  eval "value=\${$var:-}"
  if [ -z "$value" ]; then
    echo "Missing required variable: $var" >&2
    exit 1
  fi
done

if [ ! -f "private/config.php" ]; then
  echo "Missing private/config.php" >&2
  exit 1
fi

SFTP_REMOTE_PRIVATE_DIR=${SFTP_REMOTE_PRIVATE_DIR:-/mattrics-private}

lftp -u "$SFTP_USER","$SFTP_PASSWORD" "sftp://$SFTP_HOST:$SFTP_PORT" <<EOF
set ssl:verify-certificate yes
set net:max-retries 2
set net:timeout 20
mkdir -p "$SFTP_REMOTE_DIR"
mkdir -p "$SFTP_REMOTE_DIR/api"
mkdir -p "$SFTP_REMOTE_PRIVATE_DIR"
rm -f "$SFTP_REMOTE_DIR/config.js"
mirror --reverse --delete --verbose \
  --exclude-glob config.js \
  --exclude-glob .htpasswd \
  --exclude-glob .well-known \
  public "$SFTP_REMOTE_DIR"
put -O "$SFTP_REMOTE_PRIVATE_DIR" private/config.php
bye
EOF

echo "Deploy finished."
