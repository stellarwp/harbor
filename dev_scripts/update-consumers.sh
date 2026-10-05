#!/usr/bin/env bash
#
# Opens a PR on each consumer plugin bumping its stellarwp/harbor requirement
# to a released version. Run it from the Update Consumers workflow
# (.github/workflows/update-consumers.yml) or locally.
#
# Usage:
#   composer release:update-consumers -- [version] [-y] [--dry-run] [owner/repo ...]
#   dev_scripts/update-consumers.sh [version] [-y] [--dry-run] [owner/repo ...]
#     version     Release version, with or without a leading "v". Defaults to
#                 the newest tag on origin.
#     -y, --yes   Skip the confirmation prompt (required when there is no TTY).
#     --dry-run   Clone and run composer, then show the diff instead of
#                 pushing it or opening a PR. Needs no confirmation.
#     owner/repo  Consumer repos to update. Defaults to CONSUMERS below.
#
# Each repo gets a chore/bump-harbor-<version> branch cut from its default
# branch with composer.json and composer.lock updated, and a PR against the
# default branch. Re-running refreshes the branch and keeps the open PR.
#
# Requirements: gh (authenticated with push access to every consumer repo),
# composer, git.

set -euo pipefail

# The Events Calendar consumes Harbor through tribe-common (its `common`
# submodule), so the PR goes there.
CONSUMERS=(
	impress-org/givewp
	stellarwp/kadence-blocks
	the-events-calendar/tribe-common
	stellarwp/learndash-core
)

PKG="stellarwp/harbor"

VERSION=""
ASSUME_YES=""
DRY_RUN=""
REPOS=()
for arg in "$@"; do
	case "$arg" in
		-y|--yes) ASSUME_YES=1 ;;
		--dry-run) DRY_RUN=1 ;;
		-*) echo "ERROR: unknown flag '$arg'"; exit 1 ;;
		*/*) REPOS+=("$arg") ;;
		*)
			[ -z "$VERSION" ] || { echo "ERROR: unexpected argument '$arg'"; exit 1; }
			VERSION="$arg"
			;;
	esac
done
[ ${#REPOS[@]} -gt 0 ] || REPOS=("${CONSUMERS[@]}")

command -v gh >/dev/null || { echo "ERROR: gh CLI is required."; exit 1; }
command -v composer >/dev/null || { echo "ERROR: composer is required."; exit 1; }

if [ -z "$VERSION" ]; then
	VERSION="$(git ls-remote --tags --refs origin 'v*' | sed 's|.*refs/tags/||' | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -1)"
fi
VERSION="${VERSION#v}"
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "ERROR: '$VERSION' is not an x.y.z version."
	exit 1
fi

BRANCH="chore/bump-harbor-${VERSION}"

echo "Bump ${PKG} to ^${VERSION} on branch ${BRANCH} in:"
printf '  • %s\n' "${REPOS[@]}"

if [ -z "$ASSUME_YES" ] && [ -z "$DRY_RUN" ]; then
	[ -t 0 ] || { echo "ERROR: no TTY for confirmation. Pass -y to proceed."; exit 1; }
	read -rp "Proceed? [y/N] " REPLY
	[[ "$REPLY" == [Yy]* ]] || { echo "Aborted."; exit 0; }
fi

# Lets composer read private VCS repositories (e.g. Kadence's prophecy-*
# packages) with the same auth gh uses.
COMPOSER_AUTH="{\"github-oauth\":{\"github.com\":\"$(gh auth token)\"}}"
export COMPOSER_AUTH

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

BODY="Updates \`${PKG}\` to [v${VERSION}](https://github.com/stellarwp/harbor/releases/tag/v${VERSION}).

Only \`composer.json\` and \`composer.lock\` change. Run \`composer install\` to pull in the new version.

Opened by Harbor's update-consumers script."

update_repo() {
	local repo="$1" dir="$TMP/${1//\//-}"

	gh repo clone "$repo" "$dir" -- --depth 1 --quiet
	cd "$dir"
	git checkout --quiet -B "$BRANCH"

	# -w lets Harbor's own dependencies move with it. Extensions are ignored
	# because the runner's PHP may not match the plugin's; each plugin pins
	# config.platform.php, so the PHP version is still checked.
	composer require "${PKG}:^${VERSION}" -w \
		--no-install --no-scripts --no-audit --no-interaction \
		--ignore-platform-req='ext-*'

	git add composer.json composer.lock
	if git diff --cached --quiet; then
		echo "${repo}: already on ${VERSION}, nothing to do."
		return 0
	fi

	if [ -n "$DRY_RUN" ]; then
		git --no-pager diff --cached -- composer.json composer.lock
		echo "${repo}: dry run, not pushing or opening a PR."
		return 0
	fi

	git commit --quiet -m "chore: bump ${PKG} to ${VERSION}"
	git push --quiet --force -u origin "$BRANCH"

	local pr
	pr="$(gh pr list -R "$repo" --head "$BRANCH" --state open --json url -q '.[0].url')"
	if [ -n "$pr" ]; then
		echo "${repo}: refreshed ${pr}"
	else
		gh pr create -R "$repo" --head "$BRANCH" \
			--title "Bump ${PKG} to ${VERSION}" --body "$BODY"
	fi
}

FAILED=()
for repo in "${REPOS[@]}"; do
	echo ""
	echo "== ${repo}"
	# Child shell so one failing repo does not stop the rest. Not `( … ) || …`:
	# that form silently disables errexit inside the child shell.
	set +e
	( set -e; update_repo "$repo" )
	status=$?
	set -e
	[ $status -eq 0 ] || FAILED+=("$repo")
done

if [ ${#FAILED[@]} -gt 0 ]; then
	echo ""
	echo "Failed:"
	printf '  • %s\n' "${FAILED[@]}"
	exit 1
fi
