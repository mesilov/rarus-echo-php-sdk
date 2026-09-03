#!/usr/bin/env bash
#
# This file is part of the rarus-echo-php-sdk package.
#
#  For the full copyright and license information, please view the LICENSE.txt
#  file that was distributed with this source code.
#
# Maintainer-only tooling: manage per-issue git worktrees for parallel task
# work. Invoked through the Makefile targets `make worktree-new`,
# `make worktree-remove`, and `make worktree-list`; kept here with the
# maintainer skill rather than in bin/ (which holds the published SDK CLI).
#
# Worktrees live in <repo>/.worktree/<issue>-<slug> (git-ignored) and are
# provisioned so `make` targets work immediately:
#   - a symlink to the primary worktree .env.local (single source of secrets,
#     read on the host by both make and docker compose)
#   - a clone-copy of the primary worktree vendor/ (fast and Docker-safe; a
#     host symlink would not resolve inside the .:/var/www/html container mount)
#
# Usage (preferred, via make):
#   make worktree-new ISSUE=<n> SLUG=<slug> [TYPE=feature|bugfix|docs] [BASE=dev]
#   make worktree-remove (ISSUE=<n> | NAME=<n>-<slug>) [FORCE=1]
#   make worktree-list
#
# Direct usage:
#   worktree.sh new    --issue <n> --slug <slug> [--type feature|bugfix|docs] [--base dev]
#   worktree.sh remove (--issue <n> | --name <n>-<slug>) [--force]
#   worktree.sh list

set -euo pipefail

die() {
    printf 'worktree: %s\n' "$1" >&2
    exit 1
}

usage() {
    cat >&2 <<'EOF'
Manage per-issue git worktrees for parallel task work.
Preferred entry point: make worktree-new | worktree-remove | worktree-list

Direct usage:
  worktree.sh new    --issue <n> --slug <slug> [--type feature|bugfix|docs] [--base dev]
  worktree.sh remove (--issue <n> | --name <n>-<slug>) [--force]
  worktree.sh list
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
    # Independent copies only. Prefer an APFS clone (instant, copy-on-write),
    # then a reflink copy (Linux CoW filesystems), then a plain recursive copy.
    # A hard-link copy (cp -al) is intentionally NOT used: it would share inodes
    # with the primary vendor/, so an in-place write in the worktree (e.g.
    # `composer dumpautoload`) would corrupt the primary and other worktrees.
    # Each option yields real files inside the Docker mount, unlike a host symlink.
    if cp -c -R "$primary/vendor" "$dir/vendor" 2>/dev/null; then
        printf 'vendor: cloned from primary (APFS clonefile)\n'
    elif { rm -rf "$dir/vendor"; cp -R --reflink=auto "$primary/vendor" "$dir/vendor" 2>/dev/null; }; then
        printf 'vendor: reflink-copied from primary\n'
    else
        rm -rf "$dir/vendor"
        cp -a "$primary/vendor" "$dir/vendor"
        printf 'vendor: copied from primary\n'
    fi
}

# Rollback state for a partially created worktree (see cmd_new).
_NEW_DIR=''
_NEW_BRANCH=''
_NEW_CREATED_BRANCH=0

rollback_new() {
    local rc=$?
    [ "$rc" -eq 0 ] && return 0
    [ -n "$_NEW_DIR" ] || return 0
    printf 'worktree: provisioning failed (exit %d); rolling back %s\n' "$rc" "$_NEW_DIR" >&2
    git worktree remove --force "$_NEW_DIR" 2>/dev/null || rm -rf "$_NEW_DIR"
    git worktree prune 2>/dev/null || true
    if [ "$_NEW_CREATED_BRANCH" -eq 1 ] && [ -n "$_NEW_BRANCH" ]; then
        git branch -D "$_NEW_BRANCH" 2>/dev/null || true
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

    _NEW_DIR="$dir"
    _NEW_BRANCH="$branch"
    _NEW_CREATED_BRANCH=0
    if git show-ref --verify --quiet "refs/heads/${branch}"; then
        printf 'branch %s exists; attaching worktree to it\n' "$branch"
        git worktree add "$dir" "$branch"
    else
        git worktree add -b "$branch" "$dir" "origin/${base}"
        _NEW_CREATED_BRANCH=1
    fi

    # From here the worktree exists; roll it back if provisioning fails.
    trap rollback_new EXIT
    provision_secrets "$primary" "$dir"
    provision_vendor "$primary" "$dir"
    trap - EXIT

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
        printf '%s' "$name" | grep -Eq '^[0-9]+-[a-z0-9][a-z0-9-]*$' \
            || die "name must match <issue>-<slug> (kebab-case), got: $name"
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

    # Defense in depth: never operate on a path outside the managed root.
    local canon_root canon_dir
    canon_root="$(cd "$root" && pwd -P)"
    canon_dir="$(cd "$dir" 2>/dev/null && pwd -P || true)"
    case "$canon_dir" in
        "$canon_root"/*) ;;
        *) die "refusing to remove a worktree outside ${root}: $dir" ;;
    esac

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
