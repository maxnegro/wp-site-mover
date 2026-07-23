#!/usr/bin/env bash

SCRIPT_PATH="$0"; while [ -h "$SCRIPT_PATH" ]; do SCRIPT_PATH=$(readlink "$SCRIPT_PATH"); done
. "$(dirname "$SCRIPT_PATH")/functions.sh"

CHANGELOG_PATH=$(__get_changelog_file)
README_PATH="$ROOT_DIR/readme.txt"
VERSION_PREFIX=$(git config --get gitflow.prefix.versiontag | tr -d '\r\n')
VERSION_NAME="$VERSION"

if [ -n "$VERSION_PREFIX" ]; then
    VERSION_NAME=${VERSION#$VERSION_PREFIX}
fi

if [ ! -f "$CHANGELOG_PATH" ]; then
    __print_fail "CHANGELOG non trovato: $CHANGELOG_PATH"
    return 1
fi

if [ ! -f "$README_PATH" ]; then
    __print_fail "readme.txt non trovato: $README_PATH"
    return 1
fi

# Extract changelog section for current version from CHANGELOG.md
CHANGELOG_SECTION=$(awk -v ver="$VERSION_NAME" '
    BEGIN { in_section=0; buf="" }
    /^## \[/ {
        if (in_section) { exit }
        if (index($0, "## [" ver "]") == 1) {
            in_section=1
            sub(/^## \[[^\]]+\] - .*/, "= " ver " =")
            buf = buf $0 "\n"
            next
        }
    }
    in_section { buf = buf $0 "\n" }
    END { print buf }
' "$CHANGELOG_PATH")

if [ -z "$CHANGELOG_SECTION" ]; then
    __print_fail "Sezione changelog per $VERSION_NAME non trovata in $CHANGELOG_PATH"
    return 1
fi

TMP_BEFORE=$(mktemp)
TMP_AFTER=$(mktemp)

awk '/^== Changelog ==/ { exit } { print }' "$README_PATH" > "$TMP_BEFORE"
awk '/^== Changelog ==/ { found=1; next } found && /^== / { exit } { print }' "$README_PATH" > "$TMP_AFTER"

{
    cat "$TMP_BEFORE"
    printf '\n'
    printf '== Changelog ==\n\n'
    printf '%s\n' "$CHANGELOG_SECTION"
    printf '\n'
    cat "$TMP_AFTER"
} > "$README_PATH"

rm -f "$TMP_BEFORE" "$TMP_AFTER"
git add "$README_PATH"

echo "readme.txt sincronizzato con il changelog di $VERSION_NAME"
return 0
