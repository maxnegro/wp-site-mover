#!/usr/bin/env bash

CHANGELOG_PATH=$(__get_changelog_file)
TODO_MARKER=$(__get_changelog_todo_marker)
VERSION_PREFIX=$(git config --get gitflow.prefix.versiontag | tr -d '\r\n')
VERSION_NAME="$VERSION"
REQUESTED_VERSION_NAME="$VERSION"

. "$HOOKS_DIR/modules/changelog-helpers.sh"

if [ -n "$VERSION_PREFIX" ]; then
    VERSION_NAME=${VERSION_NAME#$VERSION_PREFIX}
    REQUESTED_VERSION_NAME=${REQUESTED_VERSION_NAME#$VERSION_PREFIX}
fi

SECTION_START="<!-- changelog:auto:start:${VERSION_NAME} -->"
SECTION_END="<!-- changelog:auto:end:${VERSION_NAME} -->"

if [ -z "$VERSION_NAME" ]; then
    __print_fail "Unable to determine version for changelog update."
    return 1
fi

. "$HOOKS_DIR/modules/backfill-changelog.sh"
if [ $? -ne 0 ]; then
    __print_fail "Unable to backfill changelog with previous releases."
    return 1
fi

VERSION_NAME="$REQUESTED_VERSION_NAME"
SECTION_START="<!-- changelog:auto:start:${VERSION_NAME} -->"
SECTION_END="<!-- changelog:auto:end:${VERSION_NAME} -->"

PREVIOUS_TAG=$(git tag -l "${VERSION_PREFIX}*" | sort -V | tail -2 | head -1)

if [ -n "$PREVIOUS_TAG" ]; then
    COMMIT_RANGE="$PREVIOUS_TAG..HEAD"
else
    COMMIT_RANGE="HEAD"
fi

TMP_SECTION=$(mktemp)
TMP_BODY=$(mktemp)
TMP_CHANGELOG=$(mktemp)
TMP_CLEANED=$(mktemp)
TMP_EXCLUDED_HASHES=$(mktemp)

__extract_changelog_hashes "$CHANGELOG_PATH" "$TMP_EXCLUDED_HASHES"
__append_commit_list "$COMMIT_RANGE" "$TMP_BODY" "$TMP_EXCLUDED_HASHES"

{
    echo "$SECTION_START"
    echo "## [${VERSION_NAME}] - $(date +%Y-%m-%d)"
    echo
    cat "$TMP_BODY"
    echo
    echo "> ${TODO_MARKER}"
    echo "$SECTION_END"
    echo
} > "$TMP_SECTION"

if [ ! -f "$CHANGELOG_PATH" ]; then
    {
        echo "# Changelog"
        echo
    } > "$CHANGELOG_PATH"
fi

awk -v start="$SECTION_START" -v end="$SECTION_END" '
BEGIN { skip = 0 }
$0 == start { skip = 1; next }
$0 == end { skip = 0; next }
skip == 0 { print }
' "$CHANGELOG_PATH" > "$TMP_CLEANED"

if grep -q '^# Changelog' "$TMP_CLEANED"; then
    {
        IFS= read -r first_line
        echo "$first_line"
        echo
        cat "$TMP_SECTION"
        cat
    } < "$TMP_CLEANED" > "$TMP_CHANGELOG"
else
    {
        echo "# Changelog"
        echo
        cat "$TMP_SECTION"
        cat "$TMP_CLEANED"
    } > "$TMP_CHANGELOG"
fi

mv "$TMP_CHANGELOG" "$CHANGELOG_PATH"
git add "$CHANGELOG_PATH"

rm -f "$TMP_SECTION" "$TMP_BODY" "$TMP_CLEANED" "$TMP_EXCLUDED_HASHES"

echo "CHANGELOG aggiornato in $CHANGELOG_PATH"
echo "Modifica manualmente la sezione [${VERSION_NAME}] e crea un commit prima di eseguire release/hotfix finish."

return 0
