#!/usr/bin/env bash

CHANGELOG_PATH=$(__get_changelog_file)
VERSION_PREFIX=$(git config --get gitflow.prefix.versiontag | tr -d '\r\n')

. "$HOOKS_DIR/modules/changelog-helpers.sh"

if [ ! -f "$CHANGELOG_PATH" ]; then
    {
        echo "# Changelog"
        echo
    } > "$CHANGELOG_PATH"
fi

TAGS=$(git tag -l "${VERSION_PREFIX}*" | sort -V)

if [ -z "$TAGS" ]; then
    return 0
fi

TMP_SECTIONS=$(mktemp)
TAGS_ARRAY=($TAGS)
TAG_COUNT=${#TAGS_ARRAY[@]}

for ((idx=TAG_COUNT-1; idx>=0; idx--)); do
    TAG=${TAGS_ARRAY[idx]}
    VERSION_NAME=$TAG

    if [ -n "$VERSION_PREFIX" ]; then
        VERSION_NAME=${TAG#$VERSION_PREFIX}
    fi

    if grep -q "^## \[${VERSION_NAME}\] - " "$CHANGELOG_PATH"; then
        continue
    fi

    if [ "$idx" -eq 0 ]; then
        COMMIT_RANGE="$TAG"
    else
        PREVIOUS_TAG=${TAGS_ARRAY[idx-1]}
        COMMIT_RANGE="$PREVIOUS_TAG..$TAG"
    fi

    RELEASE_DATE=$(git log -1 --format=%cs "$TAG")

    {
        echo "## [${VERSION_NAME}] - ${RELEASE_DATE}"
        echo
    } >> "$TMP_SECTIONS"

    __append_commit_list "$COMMIT_RANGE" "$TMP_SECTIONS"
done

if [ ! -s "$TMP_SECTIONS" ]; then
    rm -f "$TMP_SECTIONS"
    return 0
fi

TMP_FILE=$(mktemp)

if grep -q '^# Changelog' "$CHANGELOG_PATH"; then
    {
        IFS= read -r first_line
        echo "$first_line"
        echo
        cat "$TMP_SECTIONS"
        cat
    } < "$CHANGELOG_PATH" > "$TMP_FILE"
else
    {
        echo "# Changelog"
        echo
        cat "$TMP_SECTIONS"
        cat "$CHANGELOG_PATH"
    } > "$TMP_FILE"
fi

mv "$TMP_FILE" "$CHANGELOG_PATH"
rm -f "$TMP_SECTIONS"

return 0
