"""Reject provenance claims that do not match committed application source."""
import json
from pathlib import Path
import re
import subprocess


def git(root, *args):
    return subprocess.check_output(["git", "-C", str(root), *args], text=True).strip()


def validate_sha(sha):
    if not re.fullmatch(r"[0-9a-f]{40}", sha):
        raise ValueError("Source SHA must contain 40 lowercase hexadecimal characters")
    return sha


def verify_checkout(root, source_sha=None):
    head = validate_sha(git(root, "rev-parse", "HEAD"))
    if source_sha is not None and validate_sha(source_sha) != head:
        raise ValueError("Source SHA differs from checkout HEAD")
    if git(root, "status", "--porcelain=v1", "--untracked-files=normal", "--ignore-submodules=none"):
        raise ValueError("Packaging requires a clean checkout")
    config = json.loads((Path(root) / "build/config.json").read_text())
    paths = config["application_paths"] + [config["dependency_lock"]]
    if git(root, "ls-files", "--others", "--ignored", "--exclude-standard", "--", *paths):
        raise ValueError("Ignored application files are not part of HEAD")
    return head



if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source-sha", required=True)
    args = parser.parse_args()
    print(verify_checkout(Path(__file__).resolve().parent.parent, args.source_sha))
