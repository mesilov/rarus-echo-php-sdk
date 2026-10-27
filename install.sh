#!/bin/sh
# Adapted from mesilov/bitrix24-cli f23ca85fe45b003cedd0b6fd0340da6ac850ba00 (MIT).
# See build/BITRIX24-CLI-LICENSE.txt and build/THIRD-PARTY-NOTICES.md.
# Install only public GitHub release assets; no API tokens or CI fallback.
set -eu
LC_ALL=C
export LC_ALL
fail() { printf 'rarus-echo installer: %s\n' "$*" >&2; exit 1; }
usage() {
    cat <<'HELP'
Install rarus-echo for macOS arm64 or Linux amd64/arm64, without sudo.
Usage: sh install.sh [--version TAG] [--install-dir PATH] [--no-modify-path]
Default: latest stable public release, $HOME/.local/bin.
Requires: cat, curl, tar, awk, sed, grep, mktemp, cp, mv, chmod, mkdir, rm, uname,
          and sha256sum (Linux) or shasum (macOS).
Notices and shell activation: $HOME/.local/share/rarus-echo/{package,env}.
Source that env file in your current shell after installation.
HELP
}
main() {
version=
install_dir=
modify_path=1
while [ "$#" -gt 0 ]; do
    case "$1" in
        --help|-h) usage; exit 0 ;;
        --version|--install-dir)
            [ "$#" -ge 2 ] && [ -n "$2" ] || fail "$1 requires a value"
            case "$1" in --version) version=$2 ;; *) install_dir=$2 ;; esac
            shift 2 ;;
        --no-modify-path) modify_path=0; shift ;;
        *) fail "unknown option: $1 (use --help)" ;;
    esac
done
valid_tag() {
    case "$1" in ''|*[!A-Za-z0-9._+-]*|[!A-Za-z0-9]*) return 1 ;; esac
    [ "${#1}" -le 128 ]
}
[ -z "$version" ] || valid_tag "$version" || fail 'invalid release tag'
[ -n "${HOME:-}" ] || fail 'HOME is required'
# Colon/newline paths cannot be represented as a single PATH entry.
valid_path() {
    case "$1" in /*) ;; *) fail 'paths must be absolute' ;; esac
    case "$1" in *:*) fail 'paths must not contain a colon' ;; esac
    case "$1" in *'
'*) fail 'paths must not contain a newline' ;; esac
}
install_dir=${install_dir:-$HOME/.local/bin}
share_dir=$HOME/.local/share/rarus-echo
valid_path "$install_dir"
valid_path "$share_dir"
for utility in uname cat; do
    command -v "$utility" >/dev/null 2>&1 || fail "required utility missing: $utility"
done
case "$(uname -s)/$(uname -m)" in
    Darwin/arm64) target=macos-arm64; checksum_tool=shasum ;;
    Linux/x86_64|Linux/amd64) target=linux-amd64; checksum_tool=sha256sum ;;
    Linux/aarch64|Linux/arm64) target=linux-arm64; checksum_tool=sha256sum ;;
    *) fail "unsupported target: $(uname -s)/$(uname -m); supported: macOS arm64, Linux amd64/arm64; use install.ps1 on Windows amd64" ;;
esac
for utility in curl tar awk sed grep mktemp cp mv chmod mkdir rm "$checksum_tool"; do
    command -v "$utility" >/dev/null 2>&1 || fail "required utility missing: $utility"
done
release_base=https://github.com/mesilov/rarus-echo-php-sdk/releases
if [ -z "$version" ]; then
    resolved=$(curl -q --fail --silent --show-error --location --proto '=https' --proto-redir '=https' \
        --connect-timeout 15 --max-time 120 --output /dev/null --write-out '%{url_effective}' "$release_base/latest") \
        || fail 'cannot resolve latest stable public Release; choose an available --version tag'
    case "$resolved" in "$release_base/tag/"*) version=${resolved#"$release_base/tag/"} ;; *) fail 'latest did not resolve to a public release tag' ;; esac
    valid_tag "$version" || fail 'latest returned an invalid release tag'
fi
archive=rarus-echo-$target.tar.gz
work=$(mktemp -d "${TMPDIR:-/tmp}/rarus-echo-install.XXXXXXXX") || fail 'cannot create temporary directory'
binary_stage=
package_stage=
env_stage=
old_package=
old_package_moved=0
old_binary=
old_env=
package_replaced=0
binary_replaced=0
env_replaced=0
committed=0
cleanup() {
    if [ "$committed" -eq 0 ]; then
        if [ "$binary_replaced" -eq 1 ]; then
            if [ -n "$old_binary" ]; then mv -f "$old_binary" "$install_dir/rarus-echo"; old_binary=;
            else rm -f "$install_dir/rarus-echo"; fi
        fi
        if [ "$env_replaced" -eq 1 ]; then
            if [ -n "$old_env" ]; then mv -f "$old_env" "$share_dir/env"; old_env=;
            else rm -f "$share_dir/env"; fi
        fi
        [ "$package_replaced" -eq 0 ] || rm -rf "$share_dir/package"
        if [ "$old_package_moved" -eq 1 ]; then mv "$old_package/package" "$share_dir/package"; fi
    fi
    [ -z "$old_package" ] || rm -rf "$old_package"
    [ -z "$old_binary" ] || rm -f "$old_binary"
    [ -z "$old_env" ] || rm -f "$old_env"
    rm -rf "$work"
    [ -z "$binary_stage" ] || rm -f "$binary_stage"
    [ -z "$package_stage" ] || rm -rf "$package_stage"
    [ -z "$env_stage" ] || rm -f "$env_stage"
}
trap cleanup 0
trap 'exit 130' INT
trap 'exit 143' TERM HUP
fetch() {
    curl -q --fail --silent --show-error --location --proto '=https' --proto-redir '=https' \
        --connect-timeout 15 --max-time 300 --output "$work/$1" "$release_base/download/$version/$1" \
        || fail "cannot download public Release $version asset $1; choose a release containing standalone archives with --version"
}
fetch "$archive"
fetch SHA256SUMS
# Reject missing, malformed and duplicate records for our exact asset.
expected=$(awk -v name="$archive" '
    $NF == name { count++; if (NF != 2 || length($1) != 64 || $1 ~ /[^0-9a-fA-F]/) bad=1; hash=$1 }
    END { if (count != 1 || bad) exit 1; print tolower(hash) }
' "$work/SHA256SUMS") || fail "missing, malformed or duplicate checksum for $archive"
if [ "$checksum_tool" = shasum ]; then
    actual=$(shasum -a 256 "$work/$archive")
else
    actual=$(sha256sum "$work/$archive")
fi
actual=${actual%% *}
[ "$actual" = "$expected" ] || fail "SHA-256 mismatch for $archive"
# Validate names and member types before extraction (no links or traversal).
tar -tzf "$work/$archive" > "$work/members" || fail 'invalid archive'
awk '
    /(^\/|(^|\/)\.\.?(\/|$)|[^A-Za-z0-9_.+\/-]|\/\/)/ { exit 1 }
    { name=$0; if (seen[tolower(name)]++) exit 1;
      if (name ~ /^(rarus-echo|build-info.json|runtime-sources.json|THIRD-PARTY-NOTICES.md|RARUS-ECHO-LICENSE.txt|BITRIX24-CLI-LICENSE.txt|YIIPRESS-LICENSE.txt)$/) next;
      if (name ~ /^(runtime-licenses)\//) next;
      exit 1 }
' "$work/members" || fail 'unsafe or unexpected archive member'
tar -tvzf "$work/$archive" > "$work/types" || fail 'cannot inspect archive'
awk 'substr($0,1,1) != "-" { exit 1 }' "$work/types" || fail 'archive must contain only regular files'
mkdir "$work/package"
tar -xzf "$work/$archive" -C "$work/package" || fail 'cannot unpack archive'
for file in rarus-echo build-info.json runtime-sources.json THIRD-PARTY-NOTICES.md RARUS-ECHO-LICENSE.txt BITRIX24-CLI-LICENSE.txt YIIPRESS-LICENSE.txt; do
    [ -f "$work/package/$file" ] && [ -s "$work/package/$file" ] || fail "archive missing required file: $file"
done
have_license=0
while IFS= read -r member; do
    case "$member" in runtime-licenses/*)
        [ -f "$work/package/$member" ] && [ -s "$work/package/$member" ] || fail "empty runtime license: $member"
        have_license=1 ;;
    esac
done < "$work/members"
[ "$have_license" -eq 1 ] || fail 'archive missing runtime licenses'
chmod 755 "$work/package/rarus-echo"
reported=$("$work/package/rarus-echo" --version --no-ansi) || fail 'candidate --version failed; check platform compatibility and macOS launch permissions'
identity_prefix="RARUS Echo CLI $version (source "
case "$reported" in "$identity_prefix"*')') ;; *) fail 'candidate version does not match the selected release' ;; esac
source_sha=${reported#"$identity_prefix"}
source_sha=${source_sha%')'}
case "$source_sha" in ''|*[!0-9a-f]*) fail 'candidate has an invalid source SHA' ;; esac
[ "${#source_sha}" -eq 40 ] || fail 'candidate has an invalid source SHA'
# Stage both destinations on their own filesystem before committing the binary.
mkdir -p "$install_dir" "$share_dir"
[ ! -d "$install_dir/rarus-echo" ] || fail 'binary destination is a directory'
[ ! -d "$share_dir/env" ] || fail 'activation destination is a directory'
[ ! -L "$install_dir/rarus-echo" ] || fail 'binary destination must not be a symlink'
[ ! -L "$share_dir/env" ] || fail 'activation destination must not be a symlink'
# Never follow preexisting directory symlinks while replacing package notices.
[ ! -L "$share_dir/package" ] || fail 'package destination must not be a symlink'
binary_stage=$(mktemp "$install_dir/.rarus-echo.XXXXXXXX")
cp "$work/package/rarus-echo" "$binary_stage"
chmod 755 "$binary_stage"
staged_version=$("$binary_stage" --version --no-ansi) || fail 'staged binary cannot execute in install directory'
[ "$staged_version" = "$reported" ] || fail 'staged binary version mismatch'
package_stage=$(mktemp -d "$share_dir/.package.XXXXXXXX")
for file in build-info.json runtime-sources.json THIRD-PARTY-NOTICES.md RARUS-ECHO-LICENSE.txt BITRIX24-CLI-LICENSE.txt YIIPRESS-LICENSE.txt runtime-licenses; do
    cp -R "$work/package/$file" "$package_stage/"
done
# Single-quote shell strings, including literal quotes in user directories.
quote() { printf "'%s'" "$(printf '%s' "$1" | sed "s/'/'\\\\''/g")"; }
quoted_dir=$(quote "$install_dir")
quoted_env=$(quote "$share_dir/env")
cat > "$work/env" <<ENV
# rarus-echo shell activation (safe to source repeatedly).
case ":\${PATH:-}:" in
    *:$quoted_dir:*) ;;
    *) export PATH=$quoted_dir\${PATH:+:\$PATH} ;;
esac
ENV
if [ "$modify_path" -eq 1 ]; then
    zsh_dir=${ZDOTDIR:-$HOME}
    valid_path "$zsh_dir"
    mkdir -p "$zsh_dir"
    login_profile=$HOME/.profile
    for profile in "$HOME/.bash_profile" "$HOME/.bash_login"; do
        if [ -f "$profile" ]; then login_profile=$profile; break; fi
    done
    source_line="[ ! -f $quoted_env ] || . $quoted_env # rarus-echo installer"
    for profile in "$zsh_dir/.zshrc" "$HOME/.bashrc" "$login_profile"; do
        if ! grep -Fqx "$source_line" "$profile" 2>/dev/null; then
            printf '\n%s\n' "$source_line" >> "$profile" || fail "cannot update shell profile: $profile"
        fi
    done
fi
env_stage=$(mktemp "$share_dir/.env.XXXXXXXX")
cp "$work/env" "$env_stage"
chmod 644 "$env_stage"
# Only installer-owned package/env entries are replaced; config and data are untouched.
if [ -e "$install_dir/rarus-echo" ] || [ -L "$install_dir/rarus-echo" ]; then
    old_binary=$(mktemp "$install_dir/.previous.XXXXXXXX")
    cp -p "$install_dir/rarus-echo" "$old_binary" || fail 'cannot back up installed binary'
fi
if [ -e "$share_dir/env" ] || [ -L "$share_dir/env" ]; then
    old_env=$(mktemp "$share_dir/.previous-env.XXXXXXXX")
    cp -p "$share_dir/env" "$old_env" || fail 'cannot back up shell activation'
fi
if [ -e "$share_dir/package" ]; then
    old_package=$(mktemp -d "$share_dir/.previous.XXXXXXXX")
    mv "$share_dir/package" "$old_package/package" || fail 'cannot back up license notices'
    old_package_moved=1
fi
mv "$package_stage" "$share_dir/package" || fail 'cannot replace license notices'
package_stage=
package_replaced=1
mv -f "$binary_stage" "$install_dir/rarus-echo" || fail 'cannot replace installed binary'
binary_stage=
binary_replaced=1
mv -f "$env_stage" "$share_dir/env" || fail 'cannot replace shell activation'
env_stage=
env_replaced=1
committed=1
printf 'Installed rarus-echo %s at %s/rarus-echo\nSHA-256: %s\nNotices: %s/package\n' "$version" "$install_dir" "$expected" "$share_dir"
printf 'Activate in your current shell:\n  . %s\n  rarus-echo --version\n' "$quoted_env"
if [ "$modify_path" -eq 1 ]; then
    printf 'New zsh/bash sessions load the env file from their startup profiles.\n'
else
    printf 'Shell profiles were left unchanged (--no-modify-path).\n'
fi
}

main "$@"
