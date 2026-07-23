#!/usr/bin/env bash

COLOR_RED=$(printf '\e[0;31m')
COLOR_DEFAULT=$(printf '\e[m')
ICON_CROSS=$(printf '%s✘%s' "$COLOR_RED" "$COLOR_DEFAULT")

ROOT_DIR=$(git rev-parse --show-toplevel 2> /dev/null)
HOOKS_DIR=$(cd "$(dirname "$SCRIPT_PATH")" && pwd)

if [ -f "$ROOT_DIR/.githooks/modules/git-flow-hooks-config.sh" ]; then
    . "$ROOT_DIR/.githooks/modules/git-flow-hooks-config.sh"
fi

if [ -f "$ROOT_DIR/.git/git-flow-hooks-config.sh" ]; then
    . "$ROOT_DIR/.git/git-flow-hooks-config.sh"
fi

function __print_fail {
    echo -e "  $ICON_CROSS $1"
}

function __get_commit_files {
    echo $(git diff-index --name-only --diff-filter=ACM --cached HEAD --)
}

function __get_version_file {
    if [ -z "$VERSION_FILE" ]; then
        VERSION_FILE="VERSION"
    fi

    echo "$ROOT_DIR/$VERSION_FILE"
}

function __get_changelog_file {
    if [ -z "$CHANGELOG_FILE" ]; then
        CHANGELOG_FILE="CHANGELOG"
    fi

    echo "$ROOT_DIR/$CHANGELOG_FILE"
}

function __get_changelog_todo_marker {
    if [ -z "$CHANGELOG_TODO_MARKER" ]; then
        CHANGELOG_TODO_MARKER="TODO: Rivedi e completa questa sezione, poi committala prima di eseguire release/hotfix finish."
    fi

    echo "$CHANGELOG_TODO_MARKER"
}

function __get_hotfix_version_bumplevel {
    if [ -z "$VERSION_BUMPLEVEL_HOTFIX" ]; then
        VERSION_BUMPLEVEL_HOTFIX="PATCH"
    fi

    echo "$VERSION_BUMPLEVEL_HOTFIX"
}

function __get_release_version_bumplevel {
    if [ -z "$VERSION_BUMPLEVEL_RELEASE" ]; then
        VERSION_BUMPLEVEL_RELEASE="MINOR"
    fi

    echo "$VERSION_BUMPLEVEL_RELEASE"
}
