#!/usr/bin/env bats

PACKAGE="$BATS_TEST_DIRNAME/../.."

@test "the skill and the integration guide list every public global function" {
	functions="$(grep -oE 'function lw_harbor_[a-z_]+' "$PACKAGE/src/Harbor/global-functions.php" | awk '{print $2}')"
	[ -n "$functions" ]

	for fn in $functions; do
		for doc in skill/SKILL.md docs/guides/integration.md; do
			grep -q "\`$fn\`" "$PACKAGE/$doc" || { echo "$fn is missing from $doc"; return 1; }
		done
	done
}
