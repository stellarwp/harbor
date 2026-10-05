#!/usr/bin/env bats
# cspell:ignore NOSYSTEM

bats_require_minimum_version 1.5.0

SCRIPT="$BATS_TEST_DIRNAME/../../dev_scripts/update-consumers.sh"
BRANCH="chore/bump-harbor-1.7.0"

# Creates a bare repo standing in for a consumer on GitHub, with composer.json
# and composer.lock requiring the given Harbor constraint.
make_remote() {
	local name="${1//\//-}" constraint="$2" work="$TEST_DIR/work/${1//\//-}"
	rm -rf "$work" "$REMOTES/$name.git"
	git init --quiet -b main "$work"
	printf '{"require":{"stellarwp/harbor":"%s"}}\n' "$constraint" > "$work/composer.json"
	printf 'locked %s\n' "${constraint#^}" > "$work/composer.lock"
	git -C "$work" add .
	git -C "$work" commit --quiet -m init
	git clone --quiet --bare "$work" "$REMOTES/$name.git"
}

setup() {
	TEST_DIR="$(mktemp -d)"
	REMOTES="$TEST_DIR/remotes"
	export REMOTES
	export STUB_LOG="$TEST_DIR/stub.log"
	touch "$STUB_LOG"

	# Keep the developer's git config (signing, hooks) out of the test.
	export GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_NOSYSTEM=1
	export GIT_AUTHOR_NAME=test GIT_AUTHOR_EMAIL=test@example.com
	export GIT_COMMITTER_NAME=test GIT_COMMITTER_EMAIL=test@example.com

	mkdir -p "$TEST_DIR/bin" "$REMOTES"

	# gh: clones come from the local bare repos, PR calls are logged.
	cat > "$TEST_DIR/bin/gh" <<'SH'
#!/usr/bin/env bash
echo "gh $*" >> "$STUB_LOG"
case "$1 $2" in
	"auth token") echo fake-token ;;
	"repo clone") git clone --quiet "$REMOTES/${3//\//-}.git" "$4" ;;
	"pr list") echo "${GH_OPEN_PR:-}" ;;
	"pr create") echo "https://github.com/$4/pull/1" ;;
esac
SH

	# composer: writes the requested constraint the way `composer require` would.
	cat > "$TEST_DIR/bin/composer" <<'SH'
#!/usr/bin/env bash
echo "composer $*" >> "$STUB_LOG"
[ "$(basename "$PWD")" != "${COMPOSER_FAIL_REPO:-}" ] || { echo "composer failed" >&2; exit 1; }
constraint="${2#*:}"
printf '{"require":{"stellarwp/harbor":"%s"}}\n' "$constraint" > composer.json
printf 'locked %s\n' "${constraint#^}" > composer.lock
SH
	chmod +x "$TEST_DIR/bin/gh" "$TEST_DIR/bin/composer"
	export PATH="$TEST_DIR/bin:$PATH"

	CONSUMERS=(
		impress-org/givewp stellarwp/kadence-blocks stellarwp/kadence-pro stellarwp/kadence-shop-kit
		stellarwp/learndash-core stellarwp/memberdash stellarwp/restrict-content-pro the-events-calendar/tribe-common
	)
	for repo in "${CONSUMERS[@]}"; do
		make_remote "$repo" "^1.6"
	done

	# A Harbor checkout whose origin has tags, for the default version lookup.
	git init --quiet -b main "$TEST_DIR/harbor-work"
	git -C "$TEST_DIR/harbor-work" commit --quiet --allow-empty -m init
	for tag in v1.6.1 v1.7.0 v2.0.0-rc.1; do
		git -C "$TEST_DIR/harbor-work" tag "$tag"
	done
	git clone --quiet --bare "$TEST_DIR/harbor-work" "$TEST_DIR/harbor.git"
	git clone --quiet "$TEST_DIR/harbor.git" "$TEST_DIR/harbor"
	cd "$TEST_DIR/harbor"
}

teardown() {
	rm -rf "$TEST_DIR"
}

remote_file() {
	git -C "$REMOTES/${1//\//-}.git" show "$BRANCH:$2"
}

@test "rejects a version that is not x.y.z" {
	run "$SCRIPT" 1.7 -y impress-org/givewp
	[ "$status" -eq 1 ]
	[[ "$output" == *"ERROR: '1.7' is not an x.y.z version."* ]]
}

@test "requires -y when there is no TTY" {
	run "$SCRIPT" 1.7.0 impress-org/givewp < /dev/null
	[ "$status" -eq 1 ]
	[[ "$output" == *"Pass -y to proceed."* ]]
}

@test "defaults to the newest stable tag on origin" {
	run "$SCRIPT" --dry-run impress-org/givewp
	[ "$status" -eq 0 ]
	[[ "$output" == *"Bump stellarwp/harbor to ^1.7.0"* ]]
}

@test "defaults to every consumer repo" {
	run "$SCRIPT" 1.7.0 --dry-run
	[ "$status" -eq 0 ]
	for repo in "${CONSUMERS[@]}"; do
		[[ "$output" == *"== $repo"* ]]
	done
}

@test "pushes the bump and opens a PR" {
	run "$SCRIPT" v1.7.0 -y impress-org/givewp
	[ "$status" -eq 0 ]
	[[ "$(remote_file impress-org/givewp composer.json)" == *'"^1.7.0"'* ]]
	[[ "$(remote_file impress-org/givewp composer.lock)" == "locked 1.7.0" ]]
	grep -q "gh pr create -R impress-org/givewp --head $BRANCH" "$STUB_LOG"
}

@test "refreshes the branch and keeps an open PR" {
	export GH_OPEN_PR="https://github.com/impress-org/givewp/pull/9"
	run "$SCRIPT" 1.7.0 -y impress-org/givewp
	[ "$status" -eq 0 ]
	[[ "$output" == *"refreshed https://github.com/impress-org/givewp/pull/9"* ]]
	[[ "$(remote_file impress-org/givewp composer.json)" == *'"^1.7.0"'* ]]
	run ! grep -q "gh pr create" "$STUB_LOG"
}

@test "does nothing when the repo is already on the version" {
	make_remote impress-org/givewp "^1.7.0"
	run "$SCRIPT" 1.7.0 -y impress-org/givewp
	[ "$status" -eq 0 ]
	[[ "$output" == *"already on 1.7.0, nothing to do."* ]]
	run ! git -C "$REMOTES/impress-org-givewp.git" rev-parse --verify --quiet "$BRANCH"
	run ! grep -q "gh pr" "$STUB_LOG"
}

@test "dry run shows the diff without committing, pushing or opening a PR" {
	# A failing pre-commit hook proves the dry run never commits.
	mkdir -p "$TEST_DIR/hooks"
	printf '#!/bin/sh\nexit 1\n' > "$TEST_DIR/hooks/pre-commit"
	chmod +x "$TEST_DIR/hooks/pre-commit"
	export GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=core.hooksPath GIT_CONFIG_VALUE_0="$TEST_DIR/hooks"

	run "$SCRIPT" 1.7.0 --dry-run impress-org/givewp
	[ "$status" -eq 0 ]
	[[ "$output" == *'+{"require":{"stellarwp/harbor":"^1.7.0"}}'* ]]
	[[ "$output" == *"impress-org/givewp: dry run"* ]]
	run ! git -C "$REMOTES/impress-org-givewp.git" rev-parse --verify --quiet "$BRANCH"
	run ! grep -q "gh pr" "$STUB_LOG"
}

@test "keeps going when one repo fails and exits non-zero" {
	export COMPOSER_FAIL_REPO=impress-org-givewp
	run "$SCRIPT" 1.7.0 -y impress-org/givewp stellarwp/kadence-blocks
	[ "$status" -eq 1 ]
	[[ "$output" == *"Failed:"*"impress-org/givewp"* ]]
	[[ "$output" != *"Failed:"*"kadence-blocks"* ]]
	[[ "$(remote_file stellarwp/kadence-blocks composer.json)" == *'"^1.7.0"'* ]]
}
