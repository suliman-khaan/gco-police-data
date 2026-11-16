#!/usr/bin/env bash
# Copies the repo guard hooks into .git/hooks. Run once per clone (from development):
#   bash dev/scripts/install-hooks.sh
set -euo pipefail
root=$(git rev-parse --show-toplevel)
hooks=$(git rev-parse --git-path hooks)
for h in pre-commit pre-merge-commit; do
	cp "$root/dev/hooks/$h" "$hooks/$h"
	chmod +x "$hooks/$h"
	echo "installed $hooks/$h"
done
