#!/bin/bash
#
# This script is used to prepare a release:
# - Update the version number in Harbor.php
# - Replace all instances of "TBD" in the code with the version number
#
# Parameters
# $1 - version number (x.x.x or x.x.x.x)
# $2 - dry run (optional, defaults to false)
#
# cSpell:ignore Irnw

base_dir=$(cd "$(dirname "${BASH_SOURCE:-$0}")" && pwd)

# Get the value to replace "TBD" with as an argument
replace_value=$1
dry_run=${2:-false}

tbd_text='TBD'
tbd_regex='\b\(v\?'"$tbd_text"'\)\b'
tbd_found=false

function validate() {
	if [ "$dry_run" = true ]; then
		echo -e "Dry run: We do not need to validate the version number\n"
		return
	fi

	# Check if replace_value is set
	if [ -z "$replace_value" ]; then
		echo "Error: You must pass in the version you wish to replace '$tbd_text' with. Example: 1.0.0"
		exit 0
	fi

	# Check if replace_value is in the correct format
	if ! [[ $replace_value =~ ^[0-9]+\.[0-9]+\.[0-9]+$|^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
		echo "Error: replace_value argument must be in the format 'x.x.x' or 'x.x.x.x'"
		exit 0
	fi
}

function replace_tbd_in_files() {
	# Find all files with "TBD" in them
	# shellcheck disable=SC2062,SC2035
	files_with_tbd=$(grep --exclude-dir={vendor,node_modules,vendor-prefixed,dev_scripts,build,build-dev,.git} --exclude='*.md' --exclude='*.yml' --exclude='*diff*' --exclude='composer.json' -Irnw "$base_dir/../" -e "$tbd_regex" | cut -d':' -f1 | sed "s|$base_dir/../||g")

	if [ "$dry_run" = true ]; then
		if [ -n "$files_with_tbd" ]; then
			echo "Found $tbd_text in:"
			echo -e "$(echo "$files_with_tbd" | sort -u)\n"
			tbd_found=true
		fi

		return
	fi

	# Loop through each file
	for file in $files_with_tbd; do
		echo "Replacing $tbd_text with $replace_value in $file"
		sed -i "s/$tbd_text/$replace_value/g" "$file"
	done
}

function update_harbor_version() {
	harbor_file="$base_dir/../src/Harbor/Harbor.php"

	if [ "$dry_run" = true ]; then
		grep -q "$tbd_regex" "$harbor_file" && {
			echo -e "Found $tbd_text in Harbor.php\n"
			tbd_found=true
		}

		return
	fi

	# Update the VERSION constant in Harbor.php
	echo "Updating VERSION constant in Harbor.php"
	sed -i "s/public const VERSION = '[0-9.]*'/public const VERSION = '$replace_value'/" "$harbor_file"
}

# validate parameters
validate

# replace TBD with version number
replace_tbd_in_files

# update Harbor.php version constant
update_harbor_version

if [ "$dry_run" = true ] && [ "$tbd_found" = true ]; then
    exit 1
fi
