#!/usr/bin/env bats

SCRIPT="$BATS_TEST_DIRNAME/../../bin/harbor-install-skill"
PACKAGE="$BATS_TEST_DIRNAME/../.."
SKILL=".claude/skills/harbor-integration/SKILL.md"

setup() {
	if ! command -v php > /dev/null; then
		skip "php is not available"
	fi

	PROJECT="$BATS_TEST_TMPDIR/plugin"
	mkdir -p "$PROJECT"
}

# Builds a stand-in for a vendored Harbor so tests can vary its contents.
fake_package() {
	local version="${1:-9.9.9}"

	mkdir -p "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/bin" "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/src/Harbor"
	cp "$SCRIPT" "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill"
	cat > "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/src/Harbor/Harbor.php" <<PHP
<?php
class Harbor {
	public const VERSION = '$version';
}
PHP
	cp -R "$PACKAGE/skill" "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/skill"
}

@test "installs the skill into .claude/skills" {
	cd "$PROJECT" && run php "$SCRIPT"

	[ "$status" -eq 0 ]
	[ -f "$PROJECT/$SKILL" ]
	[[ "$output" == *"Installed .claude/skills/harbor-integration"* ]]
}

@test "stamps the installed Harbor version into the skill" {
	fake_package "1.2.3"

	cd "$PROJECT" && run php "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill"

	[ "$status" -eq 0 ]
	[[ "$output" == *"from Harbor 1.2.3"* ]]
	grep -q "stellarwp/harbor 1.2.3" "$PROJECT/$SKILL"
}

@test "re-running replaces the copy without stacking stamps" {
	fake_package "1.2.3"
	cd "$PROJECT" && php "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill" > /dev/null

	# A version bump is what post-update-cmd re-runs look like.
	sed -i.bak "s/'1.2.3'/'1.3.0'/" "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/src/Harbor/Harbor.php"
	cd "$PROJECT" && run php "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill"

	[ "$status" -eq 0 ]
	[ "$(grep -c 'Installed by' "$PROJECT/$SKILL")" -eq 1 ]
	grep -q "stellarwp/harbor 1.3.0" "$PROJECT/$SKILL"
	! grep -q "stellarwp/harbor 1.2.3" "$PROJECT/$SKILL"
}

@test "replaces a stale directory left by an earlier install" {
	mkdir -p "$PROJECT/.claude/skills/harbor-integration/nested"
	echo "stale" > "$PROJECT/.claude/skills/harbor-integration/nested/old.md"
	echo "stale" > "$PROJECT/.claude/skills/harbor-integration/SKILL.md"

	cd "$PROJECT" && run php "$SCRIPT"

	[ "$status" -eq 0 ]
	[ ! -e "$PROJECT/.claude/skills/harbor-integration/nested" ]
	! grep -q "stale" "$PROJECT/$SKILL"
}

@test "replaces a directory holding a symlink without touching the link target" {
	mkdir -p "$PROJECT/.claude/skills/harbor-integration" "$BATS_TEST_TMPDIR/elsewhere"
	echo "keep" > "$BATS_TEST_TMPDIR/elsewhere/keep.md"
	ln -s "$BATS_TEST_TMPDIR/elsewhere" "$PROJECT/.claude/skills/harbor-integration/linked"

	cd "$PROJECT" && run php "$SCRIPT"

	[ "$status" -eq 0 ]
	[ ! -e "$PROJECT/.claude/skills/harbor-integration/linked" ]
	[ -f "$BATS_TEST_TMPDIR/elsewhere/keep.md" ]
}

@test "replaces a symlink left by an older version of this command" {
	fake_package
	mkdir -p "$PROJECT/.claude/skills"
	ln -s "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/skill" "$PROJECT/.claude/skills/harbor-integration"

	cd "$PROJECT" && run php "$SCRIPT"

	[ "$status" -eq 0 ]
	[ ! -L "$PROJECT/.claude/skills/harbor-integration" ]
	[ -f "$PROJECT/$SKILL" ]
}

@test "refuses to run from Harbor's own root" {
	cd "$PACKAGE" && run php "$SCRIPT"

	[ "$status" -eq 1 ]
	[[ "$output" == *"not from Harbor itself"* ]]
}

@test "fails when the skill directory is missing" {
	fake_package
	rm -rf "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/skill"

	cd "$PROJECT" && run php "$BATS_TEST_TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill"

	[ "$status" -eq 1 ]
	[[ "$output" == *"Could not locate the Harbor skill directory"* ]]
	[ ! -e "$PROJECT/$SKILL" ]
}

@test "the skill and the integration guide list every public global function" {
	functions="$(grep -oE 'function lw_harbor_[a-z_]+' "$PACKAGE/src/Harbor/global-functions.php" | awk '{print $2}')"
	[ -n "$functions" ]

	for fn in $functions; do
		for doc in skill/SKILL.md docs/guides/integration.md; do
			grep -q "\`$fn\`" "$PACKAGE/$doc" || { echo "$fn is missing from $doc"; return 1; }
		done
	done
}
