#!/usr/bin/env bash
#
# This file is part of the rarus-echo-php-sdk package.
#
#  For the full copyright and license information, please view the LICENSE.txt
#  file that was distributed with this source code.
#
# Manage per-issue git worktrees for parallel task work.
#
# Worktrees live in <repo>/.worktree/<issue>-<slug> (git-ignored) and are
# provisioned so `make` targets work immediately:
#   - a symlink to the primary worktree .env.local (single source of secrets,
#     read on the host by both make and docker compose)
#   - a clone-copy of the primary worktree vendor/ (fast and Docker-safe; a
#     host symlink would not resolve inside the .:/var/www/html container mount)
#
# Usage:
#   bin/worktree.sh new    --issue <n> --slug <slug> [--type feature|bugfix|docs] [--base dev]
#   bin/worktree.sh remove (--issue <n> | --name <n>-<slug>) [--force]
#   bin/worktree.sh list

set -euo pipefail

die() {
    printf 'worktree: %s\n' "$1" >&2
    exit 1
}

usage() {
    cat >&2 <<'EOF'
Manage per-issue git worktrees for parallel task work.

Usage:
  bin/worktree.sh new    --issue <n> --slug <slug> [--type feature|bugfix|docs] [--base dev]
  bin/worktree.sh remove (--issue <n> | --name <n>-<slug>) [--force]
  bin/worktree.sh list
EOF
    exit "${1:-0}"
}

# Absolute path of the primary (main) worktree, i.e. the parent of the shared
# .git directory. Works when invoked from any linked worktree.
primary_root() {
    dirname "$(git rev-parse --path-format=absolute --git-common-dir)"
}

worktree_root() {
    printf '%s/.worktree' "$(primary_root)"
}

provision_secrets() {
    local primary="$1" dir="$2"
    if [ -f "$primary/.env.local" ]; then
        ln -s "$primary/.env.local" "$dir/.env.local"
        printf 'secrets: linked .env.local -> %s/.env.local\n' "$primary"
    else
        printf 'secrets: primary .env.local not found; skipped (create %s/.env.local later)\n' "$dir"
    fi
}

provision_vendor() {
    local primary="$1" dir="$2"
    if [ ! -d "$primary/vendor" ]; then
        printf 'vendor: primary vendor/ not found; running composer install\n'
        ( cd "$dir" && make composer-install )
        return
    fi
    # Prefer an APFS clone (instant, copy-on-write), fall back to a hardlink
    # copy, then to a plain recursive copy. Each yields real files inside the
    # Docker mount, unlike a host symlink.
    if cp -c -R "$primary/vendor" "$dir/vendor" 2>/dev/null; then
        printf 'vendor: cloned from primary (cp -c)\n'
    elif cp -al "$primary/vendor" "$dir/vendor" 2>/dev/null; then
        printf 'vendor: hardlinked from primary (cp -al)\n'
    else
        cp -a "$primary/vendor" "$dir/vendor"
        printf 'vendor: copied from primary (cp -a)\n'
    fi
}

cmd_new() {
    local issue='' slug='' type='feature' base='dev'
    while [ $# -gt 0 ]; do
        case "$1" in
            --issue) issue="${2:-}"; shift 2 ;;
            --slug)  slug="${2:-}"; shift 2 ;;
            --type)  type="${2:-}"; shift 2 ;;
            --base)  base="${2:-}"; shift 2 ;;
            -h|--help) usage 0 ;;
            *) die "unknown argument: $1" ;;
        esac
    done

    [ -n "$issue" ] || die "missing --issue (example: --issue 29)"
    [ -n "$slug" ] || die "missing --slug (example: --slug parallel-worktree-tooling)"
    [ -z "$type" ] && type='feature'
    [ -z "$base" ] && base='dev'

    printf '%s' "$issue" | grep -Eq '^[0-9]+$' || die "issue must be numeric: $issue"
    printf '%s' "$slug" | grep -Eq '^[a-z0-9][a-z0-9-]*$' || die "slug must be kebab-case [a-z0-9-]: $slug"
    case "$type" in
        feature|bugfix|docs) ;;
        *) die "type must be one of feature|bugfix|docs: $type" ;;
    esac

    local name="${issue}-${slug}"
    local branch="${type}/${issue}-${slug}"
    local dir; dir="$(worktree_root)/${name}"
    local primary; primary="$(primary_root)"

    [ -e "$dir" ] && die "worktree already exists: $dir"

    printf 'fetching origin/%s ...\n' "$base"
    git fetch origin "$base"

    if git show-ref --verify --quiet "refs/heads/${branch}"; then
        printf 'branch %s exists; attaching worktree to it\n' "$branch"
        git worktree add "$dir" "$branch"
    else
        git worktree add -b "$branch" "$dir" "origin/${base}"
    fi

    provision_secrets "$primary" "$dir"
    provision_vendor "$primary" "$dir"

    printf '\nworktree ready:\n  cd %s\n  branch: %s (base origin/%s)\n' "$dir" "$branch" "$base"
}

cmd_remove() {
    local issue='' name='' force=''
    while [ $# -gt 0 ]; do
        case "$1" in
            --issue) issue="${2:-}"; shift 2 ;;
            --name)  name="${2:-}"; shift 2 ;;
            --force) force='1'; shift ;;
            -h|--help) usage 0 ;;
            *) die "unknown argument: $1" ;;
        esac
    done

    local root; root="$(worktree_root)"
    local dir=''

    if [ -n "$name" ]; then
        dir="${root}/${name}"
    elif [ -n "$issue" ]; then
        printf '%s' "$issue" | grep -Eq '^[0-9]+$' || die "issue must be numeric: $issue"
        local matches=()
        local m
        for m in "${root}/${issue}-"*; do
            [ -e "$m" ] && matches+=("$m")
        done
        [ "${#matches[@]}" -eq 0 ] && die "no worktree found for issue ${issue} under ${root}"
        [ "${#matches[@]}" -gt 1 ] && die "multiple worktrees for issue ${issue}; pass --name <n>-<slug>"
        dir="${matches[0]}"
    else
        die "pass --issue <n> or --name <n>-<slug>"
    fi

    [ -e "$dir" ] || die "worktree not found: $dir"

    printf 'removing worktree: %s\n' "$dir"
    if [ -n "$force" ]; then
        git worktree remove --force "$dir"
    else
        git worktree remove "$dir"
    fi
    git worktree prune
    printf 'removed. branch kept (delete manually if no longer needed).\n'
}

cmd_list() {
    local root; root="$(worktree_root)"
    local found=''
    while IFS= read -r line; do
        case "$line" in
            "${root}/"*) printf '%s\n' "$line"; found='1' ;;
        esac
    done < <(git worktree list)
    [ -n "$found" ] || printf '(no per-issue worktrees under %s)\n' "$root"
}

main() {
    [ $# -ge 1 ] || usage 1
    local sub="$1"; shift
    case "$sub" in
        new)    cmd_new "$@" ;;
        remove) cmd_remove "$@" ;;
        list)   cmd_list "$@" ;;
        -h|--help|help) usage 0 ;;
        *) die "unknown subcommand: $sub (expected new|remove|list)" ;;
    esac
}

main "$@"
