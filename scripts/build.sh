#!/usr/bin/env bash
set -euo pipefail
cd /workspace
# Regenerate scripts when the extension definition changes.
if [[ ! -f configure || config.m4 -nt configure ]]; then
  phpize
fi
if [[ ! -f Makefile || configure -nt Makefile || config.m4 -nt Makefile ]]; then
  CFLAGS="${CFLAGS:--O0 -g3 -fno-omit-frame-pointer}" ./configure --enable-json_mapper
fi
make -j"$(nproc)"
php -d extension=/workspace/modules/json_mapper.so -r 'if (!extension_loaded("json_mapper")) { exit(1); } echo "json_mapper loaded\n";'
