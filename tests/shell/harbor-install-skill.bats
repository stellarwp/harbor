#!/usr/bin/env bats

SCRIPT="$BATS_TEST_DIRNAME/../../bin/harbor-install-skill"
PACKAGE="$BATS_TEST_DIRNAME/../.."
SKILL=".claude/skills/harbor-integration/SKILL.md"

setup() {
	if ! command -v php > /dev/null; then
		skip "php is not available"
	fi

	TMPDIR="$(mktemp -d)"
	PROJECT="$TMPDIR/plugin"
	mkdir -p "$PROJECT"
}

teardown() {
	rm -rf "$TMPDIR"
}

# Builds a stand-in for a vendored Harbor so tests can vary its contents.
fake_package() {
	local version="${1:-9.9.9}"

	mkdir -p "$TMPDIR/vendor/stellarwp/harbor/bin" "$TMPDIR/vendor/stellarwp/harbor/src/Harbor"
	cp "$SCRIPT" "$TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill"
	cat > "$TMPDIR/vendor/stellarwp/harbor/src/Harbor/Harbor.php" <<PHP
<?php
class Harbor {
	public const VERSION = '$version';
}
PHP
	cp -R "$PACKAGE/skill" "$TMPDIR/vendor/stellarwp/harbor/skill"
}

@test "installs the skill into .claude/skills" {
	cd "$PROJECT" && run php "$SCRIPT"

	[ "$status" -eq 0 ]
	[ -f "$PROJECT/$SKILL" ]
	[[ "$output" == *"Installed .claude/skills/harbor-integration"* ]]
}

@test "stamps the installed Harbor version into the skill" {
	fake_package "1.2.3"

	cd "$PROJECT" && run php "$TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill"

	[ "$status" -eq 0 ]
	[[ "$output" == *"from Harbor 1.2.3"* ]]
	grep -q "stellarwp/harbor 1.2.3" "$PROJECT/$SKILL"
}

@test "re-running replaces the copy without stacking stamps" {
	fake_package "1.2.3"
	cd "$PROJECT" && php "$TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill" > /dev/null

	# A version bump is what post-update-cmd re-runs look like.
	sed -i.bak "s/'1.2.3'/'1.3.0'/" "$TMPDIR/vendor/stellarwp/harbor/src/Harbor/Harbor.php"
	cd "$PROJECT" && run php "$TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill"

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

@test "replaces a symlink left by an older version of this command" {
	fake_package
	mkdir -p "$PROJECT/.claude/skills"
	ln -s "$TMPDIR/vendor/stellarwp/harbor/skill" "$PROJECT/.claude/skills/harbor-integration"

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
	rm -rf "$TMPDIR/vendor/stellarwp/harbor/skill"

	cd "$PROJECT" && run php "$TMPDIR/vendor/stellarwp/harbor/bin/harbor-install-skill"

	[ "$status" -eq 1 ]
	[[ "$output" == *"Could not locate the Harbor skill directory"* ]]
	[ ! -e "$PROJECT/$SKILL" ]
}
