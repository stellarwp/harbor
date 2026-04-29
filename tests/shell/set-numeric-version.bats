#!/usr/bin/env bats

SCRIPT="$BATS_TEST_DIRNAME/../../dev_scripts/set-numeric-version.sh"

setup() {
	TMPDIR="$(mktemp -d)"
	export HARBOR_PROJECT_ROOT="$TMPDIR"
	HARBOR_PHP="$TMPDIR/src/Harbor/Harbor.php"
	mkdir -p "$TMPDIR/src/Harbor"
	cat > "$HARBOR_PHP" <<'PHP'
<?php
class Harbor {
	public const VERSION = '0.0.0';
}
PHP
}

teardown() {
	rm -rf "$TMPDIR"
}

# --- validate() ---

@test "errors when no version argument is given" {
	run "$SCRIPT"
	[ "$status" -eq 0 ]
	[[ "$output" == *"Error: You must pass in the version"* ]]
}

@test "errors when version is not in x.x.x format" {
	run "$SCRIPT" "1.0"
	[ "$status" -eq 0 ]
	[[ "$output" == *"Error: replace_value argument must be in the format"* ]]
}

@test "errors when version contains non-numeric segments" {
	run "$SCRIPT" "1.0.abc"
	[ "$status" -eq 0 ]
	[[ "$output" == *"Error: replace_value argument must be in the format"* ]]
}

@test "accepts x.x.x version format" {
	run "$SCRIPT" "1.2.3"
	[ "$status" -eq 0 ]
	[[ "$output" != *"Error"* ]]
}

@test "accepts x.x.x.x version format" {
	run "$SCRIPT" "1.2.3.4"
	[ "$status" -eq 0 ]
	[[ "$output" != *"Error"* ]]
}

# --- dry run ---

@test "dry run skips validation and exits 0 when no TBD found" {
	run "$SCRIPT" "" true
	[ "$status" -eq 0 ]
	[[ "$output" == *"Dry run"* ]]
}

@test "dry run exits 1 when TBD is found in files" {
	echo "version: TBD" > "$TMPDIR/example.php"
	run "$SCRIPT" "" true
	[ "$status" -eq 1 ]
	[[ "$output" == *"Found TBD"* ]]
}

@test "dry run exits 1 when Harbor.php has TBD" {
	cat > "$HARBOR_PHP" <<'PHP'
<?php
class Harbor {
	public const VERSION = 'TBD';
}
PHP
	run "$SCRIPT" "" true
	[ "$status" -eq 1 ]
	[[ "$output" == *"Found TBD in Harbor.php"* ]]
}

# --- replace_tbd_in_files() ---

@test "replaces TBD in a PHP file" {
	echo "version: TBD" > "$TMPDIR/example.php"
	run "$SCRIPT" "2.0.0"
	grep -q "2.0.0" "$TMPDIR/example.php"
}

@test "does not replace TBD in .md files" {
	echo "version: TBD" > "$TMPDIR/example.md"
	run "$SCRIPT" "2.0.0"
	grep -q "TBD" "$TMPDIR/example.md"
}

@test "does not replace TBD in .yml files" {
	echo "version: TBD" > "$TMPDIR/example.yml"
	run "$SCRIPT" "2.0.0"
	grep -q "TBD" "$TMPDIR/example.yml"
}

# --- update_harbor_version() ---

@test "updates VERSION constant in Harbor.php" {
	run "$SCRIPT" "3.1.4"
	grep -q "public const VERSION = '3.1.4'" "$HARBOR_PHP"
}
