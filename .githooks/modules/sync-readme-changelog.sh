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

# Extract ONLY the current version section from CHANGELOG.md
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

# Normalize blank lines: collapse multiple empty lines into one
CHANGELOG_SECTION=$(printf '%s\n' "$CHANGELOG_SECTION" | sed '/^$/N;/^\n$/D')

TMP_BEFORE=$(mktemp)
TMP_AFTER=$(mktemp)
TMP_UPGRADE=$(mktemp)

# Extract everything before == Changelog == section
awk '/^== Changelog ==/ { exit } { print }' "$README_PATH" > "$TMP_BEFORE"

# Extract existing Upgrade Notice section if present
if grep -q '^== Upgrade Notice ==' "$README_PATH"; then
    awk -v upgrade_marker="== Upgrade Notice ==" '
        BEGIN { found_upgrade=0 }
        $0 == upgrade_marker { found_upgrade=1; next }
        found_upgrade && /^== / { exit }
        found_upgrade { print }
    ' "$README_PATH" > "$TMP_UPGRADE"
fi

# Extract everything after the relevant bottom section
if [ -s "$TMP_UPGRADE" ]; then
    awk -v upgrade_marker="== Upgrade Notice ==" '
        BEGIN { found_upgrade=0; found_next=0 }
        $0 == upgrade_marker { found_upgrade=1; next }
        found_upgrade && !found_next && /^== / { found_next=1; print; next }
        found_next { print }
    ' "$README_PATH" > "$TMP_AFTER"
else
    awk -v changelog_marker="== Changelog ==" '
        BEGIN { found_changelog=0; found_next=0 }
        $0 == changelog_marker { found_changelog=1; next }
        found_changelog && !found_next && /^== / { found_next=1; print; next }
        found_next { print }
    ' "$README_PATH" > "$TMP_AFTER"
fi

# Build new Upgrade Notice section: new version first, deduplicated, then existing ones
UPGRADE_ENTRY="= ${VERSION_NAME} =
TODO: write upgrade notice for ${VERSION_NAME}."

UPGRADE_SECTION="$UPGRADE_ENTRY"

if [ -s "$TMP_UPGRADE" ]; then
    EXISTING=$(cat "$TMP_UPGRADE")
    # Remove any existing entry for the current version to avoid duplication
    FILTERED=$(printf '%s\n' "$EXISTING" | awk -v ver="$VERSION_NAME" '
        BEGIN { skip=0 }
        /^= / {
            ver_line=$0
            sub(/^= | =$/, "", ver_line)
            if (ver_line == ver) { skip=1; next }
            else { skip=0 }
        }
        skip { next }
        { print }
    ')
    if [ -n "$FILTERED" ]; then
        UPGRADE_SECTION="${UPGRADE_SECTION}

${FILTERED}"
    fi
fi

# Normalize blank lines in upgrade section
UPGRADE_SECTION=$(printf '%s\n' "$UPGRADE_SECTION" | sed '/^$/N;/^\n$/D')

{
    cat "$TMP_BEFORE"
    printf '\n'
    printf '== Changelog ==\n\n'
    printf '%s\n' "$CHANGELOG_SECTION"
    printf '\n'
    printf '== Upgrade Notice ==\n\n'
    printf '%s\n' "$UPGRADE_SECTION"
    printf '\n'
    cat "$TMP_AFTER"
} > "$README_PATH"

rm -f "$TMP_BEFORE" "$TMP_AFTER" "$TMP_UPGRADE"
git add "$README_PATH"

echo "readme.txt sincronizzato con il changelog completo di $VERSION_NAME"
return 0
