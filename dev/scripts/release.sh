#!/usr/bin/env bash
# Releases `development` into `main` without any development-only files.
#
#   bash dev/scripts/release.sh 1.0.0
#
# 1. checks you are on a clean `development`
# 2. merges development into main (--no-ff, --no-commit)
# 3. removes every path in .release-exclude from the merge result
# 4. commits "Release vX.Y.Z", tags vX.Y.Z, switches back to development
#
# NEVER merge main back into development: main's history deletes the dev files,
# so that merge would delete them on development too. Hotfixes go on development
# (or a hotfix/* branch cut from development) and are released as a patch version.

main() {
	set -euo pipefail
	local version="${1:-}"
	if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ ]]; then
		echo "usage: release.sh <semver>   e.g. release.sh 1.0.0" >&2; exit 1
	fi
	local tag="v$version"

	cd "$(git rev-parse --show-toplevel)"
	[[ "$(git symbolic-ref --short HEAD)" == "development" ]] || { echo "Run from the development branch." >&2; exit 1; }
	[[ -z "$(git status --porcelain --untracked-files=no)" ]] || { echo "Working tree not clean; commit or stash first." >&2; exit 1; }
	git rev-parse -q --verify "refs/tags/$tag" >/dev/null && { echo "Tag $tag already exists." >&2; exit 1; }

	# Read the exclude list now: it disappears from the worktree once main is checked out.
	local -a excludes=()
	while IFS= read -r line; do
		line="${line%$'\r'}"
		[[ -z "$line" || "$line" == \#* ]] && continue
		excludes+=("${line%/}")
	done < .release-exclude

	git checkout -q main
	# Conflicts on excluded files (modify/delete) are expected and resolved below.
	git merge --no-ff --no-commit development -m "Release $tag" >/dev/null 2>&1 || true
	git rev-parse -q --verify MERGE_HEAD >/dev/null || {
		echo "Merge did not start (nothing to release?). Back on development." >&2
		git checkout -q development; exit 1; }

	# Removes tracked copies from index + worktree; ignored local files (e.g. settings.local.json) stay.
	for p in "${excludes[@]}"; do
		git rm -r -q -f --ignore-unmatch -- "$p"
	done

	local unresolved
	unresolved=$(git diff --name-only --diff-filter=U)
	if [[ -n "$unresolved" ]]; then
		echo "Merge conflicts outside the excluded paths — resolve manually on main, then commit:" >&2
		echo "$unresolved" >&2
		exit 1
	fi

	git commit -q -m "Release $tag"
	git tag -a "$tag" -m "Release $tag"
	git checkout -q development
	echo "Released $tag on main. Push with: git push origin main development --follow-tags"
}

main "$@"
exit
