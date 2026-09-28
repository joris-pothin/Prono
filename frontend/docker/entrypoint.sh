#!/bin/sh
set -e

if [ ! -d node_modules ]; then
  echo "Installing JS dependencies..."
  npm install
fi

exec "$@"
