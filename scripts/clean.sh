#!/usr/bin/env bash
set -euo pipefail
cd /workspace
if [[ -f Makefile ]]; then make distclean; fi
rm -rf autom4te.cache .libs modules
# Remove only generated build-system files, not sources.
rm -f configure configure~ config.h.in config.h.in~ config.h config.log config.status config.nice \
  libtool Makefile Makefile.fragments Makefile.objects run-tests.php \
  php_json_mapper.h stamp-h1 acinclude.m4 aclocal.m4 install-sh missing mkinstalldirs ltmain.sh
