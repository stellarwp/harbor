#!/bin/bash
#
# This script is used to prepare a release by:
# - Updating the VERSION constant in Harbor.php and replacing all instances of "TBD" in the code with the version number.
# - Writing the changelog entries for the release.
#
# Parameters
# $1 - version number (x.x.x or x.x.x.x)
# $2 - date (optional, defaults to today's date)

VERSION=$1
DATE=${2:-$(date +%Y-%m-%d)}

# Validate required parameters.
if [ -z "$VERSION" ]; then
    echo "Error: Version parameter is required"
    echo "Usage: $0 <version> [date]"
    echo "Example: $0 1.0.0 2024-01-15"
    exit 1
fi

# Validate version format
if ! [[ $VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$|^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Error: Version must be in format 'x.x.x' or 'x.x.x.x'"
    exit 1
fi

echo "Preparing release for version: $VERSION"
echo "Release date: $DATE"

# Get the base directory (parent of dev_scripts).
base_dir=$(cd "$(dirname "${BASH_SOURCE:-$0}")/.." && pwd)

echo "Setting version number..."
"$base_dir/dev_scripts/set-numeric-version.sh" "$VERSION"

echo "Writing changelog entries..."
bunx changelogger write --overwrite-version "$VERSION" --date "$DATE"

echo "Done!"
