#!/usr/bin/env bash

CHANGELOG_PATH=$(__get_changelog_file)
TODO_MARKER=$(__get_changelog_todo_marker)
VERSION_PREFIX=$(git config --get gitflow.prefix.versiontag | tr -d '\r\n')
VERSION_NAME="$VERSION"

if [ -n "$VERSION_PREFIX" ]; then
    VERSION_NAME=${VERSION_NAME#$VERSION_PREFIX}
fi

SECTION_START="<!-- changelog:auto:start:${VERSION_NAME} -->"
SECTION_END="<!-- changelog:auto:end:${VERSION_NAME} -->"

if [ ! -f "$CHANGELOG_PATH" ]; then
    __print_fail "CHANGELOG non trovato: $CHANGELOG_PATH"
    __print_fail "Esegui prima git flow release/hotfix start per generare il file."
    return 1
fi

if ! git diff --quiet -- "$CHANGELOG_PATH" || ! git diff --cached --quiet -- "$CHANGELOG_PATH"; then
    __print_fail "Il CHANGELOG ha modifiche non committate."
    __print_fail "Committa il CHANGELOG prima di proseguire con finish."
    return 1
fi

if grep -qF "$TODO_MARKER" "$CHANGELOG_PATH"; then
    __print_fail "Il CHANGELOG contiene ancora il promemoria TODO per la release ${VERSION_NAME}."
    __print_fail "Rivedi la sezione della release/hotfix e committala prima di proseguire."
    return 1
fi

if ! grep -qF "$SECTION_START" "$CHANGELOG_PATH" || ! grep -qF "$SECTION_END" "$CHANGELOG_PATH"; then
    __print_fail "Sezione CHANGELOG automatica non trovata per la versione ${VERSION_NAME}."
    __print_fail "Rigenera con git flow release/hotfix start ${VERSION_NAME} oppure aggiorna il CHANGELOG manualmente."
    return 1
fi

if [ -z "$(git log --format='%H' "${MASTER_BRANCH}..HEAD" -- "$CHANGELOG_PATH" | head -1)" ]; then
    __print_fail "Nessun commit del CHANGELOG trovato sul branch corrente rispetto a ${MASTER_BRANCH}."
    __print_fail "Modifica e committa il CHANGELOG prima di eseguire finish."
    return 1
fi

return 0
