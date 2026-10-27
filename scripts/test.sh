#!/usr/bin/env bash
set -euo pipefail
cd /workspace
if [[ ! -f Makefile ]]; then
  /workspace/scripts/build.sh
fi
# -n is deliberately absent: make test uses the extension-aware PHP test harness.
NO_INTERACTION=1 REPORT_EXIT_STATUS=1 make test TESTS=tests
