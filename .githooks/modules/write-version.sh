#!/usr/bin/env bash

VERSION_FILE=$(__get_version_file)
VERSION_PREFIX=$(git config --get gitflow.prefix.versiontag | tr -d '\r\n')

if [ -n "$VERSION_PREFIX" ]; then
    VERSION=${VERSION#$VERSION_PREFIX}
fi

echo -n "$VERSION" > "$VERSION_FILE"
git add "$VERSION_FILE"

# Update plugin header and constant
if [ -f "$ROOT_DIR/wp-site-mover.php" ]; then
    sed -i.bak -E "s/^( \* Version:)[[:space:]]+[0-9]+\.[0-9]+\.[0-9]+/\1           $VERSION/" "$ROOT_DIR/wp-site-mover.php"
    sed -i.bak -E "s/^(define\('SITEMOVER_VERSION',)[[:space:]]*'[^']+'/\1 '$VERSION'/" "$ROOT_DIR/wp-site-mover.php"
    rm -f "$ROOT_DIR/wp-site-mover.php.bak"
    git add "$ROOT_DIR/wp-site-mover.php"
fi

# Update readme.txt Stable tag
if [ -f "$ROOT_DIR/readme.txt" ]; then
    sed -i.bak -E "s/^(Stable tag:)[[:space:]]+[0-9]+\.[0-9]+\.[0-9]+/\1 $VERSION/" "$ROOT_DIR/readme.txt"
    rm -f "$ROOT_DIR/readme.txt.bak"
    git add "$ROOT_DIR/readme.txt"
fi

git commit -m "Bumped version to $VERSION"

if [ $? -ne 0 ]; then
    __print_fail "Unable to write version to $VERSION_FILE."
    return 1
else
    return 0
fi
