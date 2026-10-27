#!/bin/sh
# Adapted from mesilov/bitrix24-cli f23ca85fe45b003cedd0b6fd0340da6ac850ba00 (BSD-3-Clause).
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
Requires: cat, curl, tar, awk, sed, grep, mktemp, cp, mv, chmod, mkdir, rm, cmp, ls, uname,
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
for utility in curl tar awk sed grep mktemp cp mv chmod mkdir rm cmp ls "$checksum_tool"; do
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
immutable_stage=
immutable_package=
immutable_created=0
committed=0
cleanup() {
    status=$?
    # Once rollback starts, a second handled signal must not interrupt restoration
    # and then discard the only recovery copy during another cleanup pass.
    trap '' INT TERM HUP
    trap - 0
    set +e
    rollback_failed=0
    if [ "$committed" -eq 0 ]; then
        if [ "$binary_replaced" -eq 1 ]; then
            if [ -n "$old_binary" ]; then
                if mv -f "$old_binary" "$install_dir/rarus-echo"; then old_binary=; else rollback_failed=1; fi
            else rm -f "$install_dir/rarus-echo" || rollback_failed=1; fi
        fi
        if [ "$env_replaced" -eq 1 ]; then
            if [ -n "$old_env" ]; then
                if mv -f "$old_env" "$share_dir/env"; then old_env=; else rollback_failed=1; fi
            else rm -f "$share_dir/env" || rollback_failed=1; fi
        fi
        [ "$package_replaced" -eq 0 ] || rm -rf "$share_dir/package" || rollback_failed=1
        # The intent is recorded before mv. A failed move can leave the original
        # notices in place, so restore only when the private backup actually exists.
        if [ "$old_package_moved" -eq 1 ] && [ -e "$old_package/package" ]; then
            mv "$old_package/package" "$share_dir/package" || rollback_failed=1
        fi
        if [ -f "$work/profiles" ]; then
            profile_index=0
            while IFS= read -r profile; do
                profile_index=$((profile_index + 1))
                if [ -f "$work/profile-$profile_index" ]; then
                    cp -p "$work/profile-$profile_index" "$profile" || rollback_failed=1
                else rm -f "$profile" || rollback_failed=1; fi
            done < "$work/profiles"
        fi
        if [ "$immutable_created" -eq 1 ] && [ "$rollback_failed" -eq 0 ]; then
            rm -rf "$immutable_package" || rollback_failed=1
        fi
    fi
    if [ "$rollback_failed" -ne 0 ]; then
        printf 'rarus-echo installer: rollback incomplete; recovery files retained: %s %s %s %s %s\n' \
            "$old_binary" "$old_env" "$old_package" "$immutable_package" "$work" >&2
        exit 1
    fi
    [ -z "$old_package" ] || rm -rf "$old_package"
    [ -z "$old_binary" ] || rm -f "$old_binary"
    [ -z "$old_env" ] || rm -f "$old_env"
    rm -rf "$work"
    [ -z "$binary_stage" ] || rm -f "$binary_stage"
    [ -z "$package_stage" ] || rm -rf "$package_stage"
    [ -z "$env_stage" ] || rm -f "$env_stage"
    [ -z "$immutable_stage" ] || rm -rf "$immutable_stage"
    exit "$status"
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
      count=split(name, parts, "/"); prefix="";
      for (i=1; i<=count; i++) {
          prefix=prefix (i>1 ? "/" : "") parts[i]; folded=tolower(prefix);
          if (folded in spelling && spelling[folded] != prefix) exit 1;
          spelling[folded]=prefix;
          if (i<count && folded in files) exit 1;
          if (i<count) directories[folded]=1;
      }
      if (tolower(name) in directories) exit 1;
      files[tolower(name)]=1;
      if (name ~ /^(rarus-echo|build-info.json|runtime-sources.json|THIRD-PARTY-NOTICES.md|RARUS-ECHO-LICENSE.txt|BITRIX24-CLI-LICENSE.txt|YIIPRESS-LICENSE.txt|media-build-info.json|media-source.tar.xz|media-SHA256SUMS)$/) next;
      if (name ~ /^libexec\/(ffmpeg|ffprobe)$/) next;
      if (name ~ /^(runtime-licenses|media-licenses)\//) next;
      exit 1 }
' "$work/members" || fail 'unsafe or unexpected archive member'
tar -tvzf "$work/$archive" > "$work/types" || fail 'cannot inspect archive'
awk '
    { mode=substr($0,1,10); name=$NF;
      if (name ~ /^(rarus-echo|libexec\/(ffmpeg|ffprobe))$/) { if (mode != "-rwxr-xr-x") exit 1 }
      else if (mode != "-rw-r--r--") exit 1 }
' "$work/types" || fail 'archive must contain regular files with exact executable/data permissions'
mkdir "$work/package"
tar -xzf "$work/$archive" -C "$work/package" || fail 'cannot unpack archive'
for file in rarus-echo build-info.json runtime-sources.json THIRD-PARTY-NOTICES.md RARUS-ECHO-LICENSE.txt BITRIX24-CLI-LICENSE.txt YIIPRESS-LICENSE.txt; do
    [ -f "$work/package/$file" ] && [ -s "$work/package/$file" ] || fail "archive missing required file: $file"
done
have_license=0
have_media_license=0
while IFS= read -r member; do
    case "$member" in runtime-licenses/*)
        [ -f "$work/package/$member" ] && [ -s "$work/package/$member" ] || fail "empty runtime license: $member"
        have_license=1 ;;
        media-licenses/*)
        [ -f "$work/package/$member" ] && [ -s "$work/package/$member" ] || fail "empty media license: $member"
        have_media_license=1 ;;
    esac
done < "$work/members"
[ "$have_license" -eq 1 ] || fail 'archive missing runtime licenses'
# Parse complete JSON with portable awk, rejecting duplicate keys and malformed input.
# Escaped object keys are deliberately not accepted in our controlled build manifests.
json_records() {
    awk '
    function bad() { invalid=1; exit 1 }
    function ws() { while (substr(doc,pos,1) ~ /^[ \t\r\n]$/ && pos<=length(doc)) pos++ }
    function string_value(    start,c,e,n) {
        if (substr(doc,pos++,1) != "\"") bad(); start=pos;
        while (pos<=length(doc)) {
            c=substr(doc,pos++,1);
            if (c=="\"") return substr(doc,start,pos-start-1);
            if (c ~ /[[:cntrl:]]/) bad();
            if (c=="\\") {
                e=substr(doc,pos++,1);
                if (e=="u") { for(n=0;n<4;n++) if (substr(doc,pos++,1) !~ /^[0-9a-fA-F]$/) bad() }
                else if (e !~ /^["\\\/bfnrt]$/) bad();
            }
        } bad();
    }
    function value(path,depth,    c,key,child,i,start,token) {
        if (depth>64) bad(); ws(); c=substr(doc,pos,1);
        if (c=="{") {
            print path "\t@object\tobject";
            pos++; ws(); if(substr(doc,pos,1)=="}") { pos++; return }
            while(1) {
                ws(); key=string_value(); if(key ~ /[\\]/) bad();
                # Encode path delimiters so filename keys in toolchain_patches
                # remain valid without impersonating nested identity properties.
                gsub(/%/, "%25", key); gsub(/\./, "%2e", key);
                gsub(/\[/, "%5b", key); gsub(/\]/, "%5d", key);
                child=path (path!="" ? "." : "") key;
                if(seen[child]++) bad(); ws(); if(substr(doc,pos++,1)!=":") bad();
                value(child,depth+1); ws(); c=substr(doc,pos++,1); if(c=="}") return; if(c!=",") bad();
            }
        } else if(c=="[") {
            print path "\t@array\tarray";
            pos++; ws(); if(substr(doc,pos,1)=="]") { pos++; return }
            i=0; while(1) { value(path "[" i++ "]",depth+1); ws(); c=substr(doc,pos++,1); if(c=="]") return; if(c!=",") bad() }
        } else if(c=="\"") { token=string_value(); if(token !~ /[\\\t\r\n]/) print path "\t" token "\tstring" }
        else {
            start=pos; while(substr(doc,pos,1) ~ /^[^ \t\r\n,}\]]$/ && pos<=length(doc)) pos++;
            token=substr(doc,start,pos-start);
            if(token !~ /^(true|false|null|-?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][+-]?[0-9]+)?)$/) bad();
            print path "\t" token "\t" (token ~ /^(true|false|null)$/ ? "literal" : "number");
        }
    }
    { doc=doc $0 "\n" }
    END { if(invalid) exit 1; pos=1; ws(); if(substr(doc,pos,1)!="{") bad(); value("",0); ws(); if(pos<=length(doc)) bad() }
    ' "$1"
}
field() { awk -F '\t' -v key="$2" -v kind="${3:-string}" '$1==key {count++; value=$2; type=$3} END {if(count!=1 || type!=kind) exit 1; print value}' "$1"; }
hash_file() {
    if [ "$checksum_tool" = shasum ]; then hash=$(shasum -a 256 "$1"); else hash=$(sha256sum "$1"); fi
    printf '%s\n' "${hash%% *}"
}
validate_media() {
    bundle=$1
    awk '
      NF != 2 || length($1)!=64 || $1 ~ /[^0-9a-f]/ {exit 1}
      $2 !~ /^(libexec\/(ffmpeg|ffprobe)|media-build-info.json|media-source.tar.xz)$/ {exit 1}
      {if(seen[$2]++) exit 1; count++} END {if(count!=4) exit 1}
    ' "$bundle/media-SHA256SUMS" || fail 'media checksum manifest must contain exactly four valid records'
    for media_file in libexec/ffmpeg libexec/ffprobe media-build-info.json media-source.tar.xz; do
        expected_media=$(awk -v name="$media_file" '$2==name {print $1}' "$bundle/media-SHA256SUMS")
        [ "$(hash_file "$bundle/$media_file")" = "$expected_media" ] || fail "media SHA-256 mismatch: $media_file"
    done
    json_records "$bundle/media-build-info.json" > "$work/media-records" || fail 'invalid media build JSON'
    json_records "$bundle/build-info.json" > "$work/build-records" || fail 'invalid CLI build JSON'
    [ "$(field "$work/media-records" schema_version number)" = 1 ] || fail 'unsupported media manifest schema'
    [ "$(field "$work/media-records" configure_args array)" = @array ] || fail 'media configure arguments must be an array'
    [ -n "$(field "$work/media-records" compiler)" ] && [ -n "$(field "$work/media-records" minimum_os)" ] || fail 'media manifest missing compiler/minimum OS'
    [ "$(field "$work/media-records" target)" = "$target" ] || fail 'media target mismatch'
    media_version=$(field "$work/media-records" ffmpeg_version) || fail 'missing FFmpeg version'
    printf '%s\n' "$media_version" | grep -Eq '^[0-9]+(\.[0-9]+)+$' || fail 'invalid FFmpeg version'
    for key in ffmpeg_version source_sha256 profile_sha256; do
        value=$(field "$work/media-records" "$key") || fail "missing media identity: $key"
        [ "$(field "$work/build-records" "media.$key")" = "$value" ] || fail "CLI/media identity mismatch: $key"
        if [ "$key" != ffmpeg_version ]; then
            [ "${#value}" -eq 64 ] || fail "invalid media identity: $key"
            case "$value" in *[!0-9a-f]*) fail "invalid media identity: $key" ;; esac
        fi
    done
    for tool in ffmpeg ffprobe; do
        [ "$(field "$work/media-records" "binaries.$tool.path")" = "libexec/$tool" ] || fail 'unexpected media binary path'
        [ "$(field "$work/media-records" "binaries.$tool.sha256")" = "$(hash_file "$bundle/libexec/$tool")" ] || fail 'media binary manifest hash mismatch'
        tool_version=$("$bundle/libexec/$tool" -version) || fail "$tool cannot execute"
        case "$tool_version" in "$tool version $media_version "*) ;; *) fail "$tool version does not match media identity" ;; esac
    done
    [ "$(field "$work/media-records" source_package.path)" = media-source.tar.xz ] || fail 'unexpected media source path'
    [ "$(field "$work/media-records" source_package.sha256)" = "$(hash_file "$bundle/media-source.tar.xz")" ] || fail 'media source manifest hash mismatch'
    # A short generated PCM input proves the bundled demuxer/FLAC encoder and probe.
    printf '\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000\000' > "$work/check.pcm"
    rm -f "$work/check.flac"
    "$bundle/libexec/ffmpeg" -nostdin -v error -f s16le -ar 8000 -ac 1 -i "$work/check.pcm" -c:a flac "$work/check.flac" || fail 'bundled media conversion failed'
    [ -s "$work/check.flac" ] || fail 'bundled media conversion produced no output'
    probe=$("$bundle/libexec/ffprobe" -v error -select_streams a:0 -show_entries stream=codec_name -of default=nw=1:nk=1 "$work/check.flac") || fail 'bundled media probe failed'
    [ "$probe" = flac ] || fail 'bundled media probe did not confirm FLAC audio'
}
json_records "$work/package/build-info.json" > "$work/build-records" || fail 'invalid CLI build JSON'
legacy_package=0
# Frozen compatibility for the only published pre-media standalone release.
# No other version/source pair may omit the complete media payload.
if [ "$version" = 0.5.0-rc-1 ] && [ "$(field "$work/build-records" source_sha)" = e359975527bd483707dc39d23599545a77c1620d ]; then
    legacy_package=1
    awk '/^(libexec\/|media-)/ {exit 1}' "$work/members" || fail 'frozen legacy archive must not contain media entries'
    awk -F '\t' '$1=="media" || index($1,"media.")==1 {exit 1}' "$work/build-records" || fail 'frozen legacy metadata must not declare media'
    [ "$(field "$work/build-records" target)" = "$target" ] || fail 'frozen legacy archive target mismatch'
else
    for file in libexec/ffmpeg libexec/ffprobe media-build-info.json media-source.tar.xz media-SHA256SUMS; do
        [ -f "$work/package/$file" ] && [ -s "$work/package/$file" ] || fail "archive missing required file: $file"
    done
    [ "$have_media_license" -eq 1 ] || fail 'archive missing media licenses'
    validate_media "$work/package"
fi
reported=$("$work/package/rarus-echo" --version --no-ansi) || fail 'candidate --version failed; check platform compatibility and macOS launch permissions'
identity_prefix="RARUS Echo CLI $version (source "
case "$reported" in "$identity_prefix"*')') ;; *) fail 'candidate version does not match the selected release' ;; esac
source_sha=${reported#"$identity_prefix"}
source_sha=${source_sha%')'}
case "$source_sha" in ''|*[!0-9a-f]*) fail 'candidate has an invalid source SHA' ;; esac
[ "${#source_sha}" -eq 40 ] || fail 'candidate has an invalid source SHA'
[ "$(field "$work/build-records" version)" = "$version" ] && [ "$(field "$work/build-records" source_sha)" = "$source_sha" ] || fail 'CLI build metadata identity mismatch'
# Stage both destinations on their own filesystem before committing the binary.
mkdir -p "$install_dir" "$share_dir"
for directory in "$install_dir" "$share_dir" "$install_dir/.rarus-echo" "$install_dir/.rarus-echo/packages"; do
    [ ! -L "$directory" ] || fail 'package parent must not be a symlink'
    [ ! -e "$directory" ] || [ -d "$directory" ] || fail 'package parent must be a directory'
done
mkdir -p "$install_dir/.rarus-echo/packages"
immutable_package=$install_dir/.rarus-echo/packages/$version-$source_sha
[ ! -L "$immutable_package" ] || fail 'immutable package must not be a symlink'
check_existing_tree() {
    for existing_path in "$1"/* "$1"/.[!.]* "$1"/..?*; do
        [ -e "$existing_path" ] || [ -L "$existing_path" ] || continue
        [ ! -L "$existing_path" ] || fail 'existing immutable package contains a link'
        relative=${existing_path#"$immutable_package"/}
        if [ -d "$existing_path" ]; then
            awk -v prefix="$relative/" 'index($0,prefix)==1 {found=1} END {if(!found) exit 1}' "$work/members" || fail 'existing immutable package contains an unexpected directory'
            check_existing_tree "$existing_path"
        elif [ -f "$existing_path" ]; then
            awk -v name="$relative" '$0==name {found=1} END {if(!found) exit 1}' "$work/members" || fail 'existing immutable package contains an unexpected file'
            permissions=$(ls -ld "$existing_path") || fail 'cannot inspect immutable package permissions'
            case "$relative" in rarus-echo|libexec/ffmpeg|libexec/ffprobe) required_permissions=-rwxr-xr-x ;; *) required_permissions=-rw-r--r-- ;; esac
            case "$permissions" in "$required_permissions"*) ;; *) fail 'existing immutable package permissions conflict with release' ;; esac
        else fail 'existing immutable package contains a special file'; fi
    done
}
if [ -e "$immutable_package" ]; then
    [ -d "$immutable_package" ] || fail 'immutable package destination must be a directory'
    check_existing_tree "$immutable_package"
    # Compare every file, including notices and the native entrypoint. Never rewrite it.
    while IFS= read -r member; do
        [ ! -L "$immutable_package/$member" ] && [ -f "$immutable_package/$member" ] && cmp -s "$work/package/$member" "$immutable_package/$member" || fail 'existing immutable package conflicts with release'
    done < "$work/members"
    [ "$legacy_package" -eq 1 ] || validate_media "$immutable_package"
else
    immutable_stage=$(mktemp -d "$install_dir/.rarus-echo/packages/.stage.XXXXXXXX")
    cp -R "$work/package/." "$immutable_stage/"
    [ "$legacy_package" -eq 1 ] || validate_media "$immutable_stage"
fi
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
if [ "$legacy_package" -eq 0 ]; then
    for file in media-build-info.json media-licenses media-SHA256SUMS; do cp -R "$work/package/$file" "$package_stage/"; done
fi
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
    old_package_moved=1
    mv "$share_dir/package" "$old_package/package" || fail 'cannot back up license notices'
fi
# Register each rollback intent before its mutation. A handled signal can arrive
# after the rename succeeds but before the next shell assignment executes.
if [ -n "$immutable_stage" ]; then
    immutable_created=1
    mv "$immutable_stage" "$immutable_package" || fail 'cannot activate immutable package'
    immutable_stage=
fi
package_replaced=1
mv "$package_stage" "$share_dir/package" || fail 'cannot replace license notices'
package_stage=
binary_replaced=1
mv -f "$binary_stage" "$install_dir/rarus-echo" || fail 'cannot replace installed binary'
binary_stage=
env_replaced=1
mv -f "$env_stage" "$share_dir/env" || fail 'cannot replace shell activation'
env_stage=
if [ "$modify_path" -eq 1 ]; then
    profile_index=0
    : > "$work/profiles"
    for profile in "$zsh_dir/.zshrc" "$HOME/.bashrc" "$login_profile"; do
        if ! grep -Fqx "$source_line" "$profile" 2>/dev/null; then
            profile_index=$((profile_index + 1))
            if [ -e "$profile" ]; then cp -p "$profile" "$work/profile-$profile_index" || fail 'cannot back up shell profile'; fi
            printf '%s\n' "$profile" >> "$work/profiles"
            printf '\n%s\n' "$source_line" >> "$profile" || fail "cannot update shell profile: $profile"
        fi
    done
fi
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
