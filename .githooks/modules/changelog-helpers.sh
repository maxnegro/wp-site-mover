#!/usr/bin/env bash

function __append_commit_list {
    local commit_range="$1"
    local output_file="$2"
    local excluded_hashes_file="$3"

    git log --no-merges --pretty=format:'%s|%h' "$commit_range" | while IFS='|' read -r subject hash; do
        if [[ "$subject" =~ ^Bumped\ version\ to\  ]]; then
            continue
        fi

        if [ -z "$subject" ] || [ -z "$hash" ]; then
            continue
        fi

        if [ -n "$excluded_hashes_file" ] && [ -f "$excluded_hashes_file" ] && grep -qx "$hash" "$excluded_hashes_file"; then
            continue
        fi

        echo "- ${subject} (${hash})" >> "$output_file"
    done

    if [ ! -s "$output_file" ]; then
        echo "- Nessun commit rilevante trovato in questo intervallo." >> "$output_file"
    fi

    echo >> "$output_file"
}

function __extract_changelog_hashes {
    local changelog_path="$1"
    local output_file="$2"

    if [ ! -f "$changelog_path" ]; then
        : > "$output_file"
        return 0
    fi

    grep -oE '\([0-9a-f]{7,40}\)' "$changelog_path" | tr -d '()' | sort -u > "$output_file"
}
