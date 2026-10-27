# Compact media source recipe

FFmpeg 9.0.2 is pinned in config.json. The official release detached signature was
verified on 2026-10-27 with the FFmpeg development signing key fingerprint
FCF986EA15E6E293A5644F10B4322F04D67658D8. The exact signature and public key are
included here so a maintainer can repeat `gpg --import upstream-signing-key.asc`
and `gpg --verify upstream-source.tar.xz.asc ffmpeg-9.0.2.tar.xz`.
The builder always verifies the committed SHA-256, including offline/cached inputs.

Run `python3 build/media/build.py TARGET --destination /absolute/output` from the
repository root. Use `--source /path/ffmpeg-9.0.2.tar.xz` for an offline build.
The supplied source package preserves that archive byte for byte plus this entire
recipe, notices, patches (none in this version), effective flags and compiler ID.
Extract media-source.tar.xz into an empty directory. The self-contained recipe
is under recipe/build/media; licenses, linkage helper and CC0 acceptance fixtures
are included under recipe/. Run `python3 recipe/build/media/build.py TARGET
--destination /absolute/output --source /absolute/ffmpeg-9.0.2.tar.xz` or extract
the upstream archive and configure manually. Install a native C compiler, bash, make and strip; reproduce the effective
configure arguments in effective-build.json and run `make -j2 ffmpeg ffprobe`.

Linux uses musl-gcc and static linkage. macOS uses Xcode clang with a macOS 15.0
floor, static FFmpeg libraries and only system dylibs; final tools are ad-hoc signed.
Windows uses MSYS2 UCRT64 GCC/make with static libgcc, system UCRT, and no GPL,
nonfree or external multimedia libraries. Windows acceptance runs on Server 2022;
Windows 11 is its documented desktop floor. Toolchain pins do not promise bitwise
reproducibility. Native acceptance is mandatory for each target; another platform's
success is not acceptance evidence.

FFmpeg components are disabled by default. Only file/pipe protocols, listed audio
codecs, demuxers/muxers and normalization filters are enabled. No FFplay, video
codecs, capture devices, network access, GPL or nonfree components are enabled.
Build cache keys include source/profile, recipe bytes, target and compiler/OS;
reused output is hash-checked and native-accepted again by package/release gates.
