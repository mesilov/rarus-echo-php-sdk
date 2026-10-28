## Structure

README follows description → downloads → curl installation → first use → documentation, with support/license links. Four direct Latest archive links match existing packaging names. Linux amd64/arm64 share a shell example with an explicit archive choice; macOS and Windows have native extraction/checksum examples. Extract and retain the whole package, then run from that directory; persistent PATH setup belongs in docs/install/.

Detailed material is moved rather than summarized away:

- docs/install/README.md: platform matrix, release availability, native installers, manual verification, package layout, PATH, updates and troubleshooting.
- docs/install/docker.md: existing Docker usage, publication and image-size notes.
- docs/install/php.md: Composer and PHP requirements.
- docs/connection.md: saved config, credential precedence and HTTP timeout.
- docs/cli.md: doctor, usage, media preparation and complete argument/option reference.
- docs/php-sdk.md: PHP quick start, errors, examples and API capabilities.
- docs/agent-plugin.md: agent plugin installation, invocation and generated reference workflow.
- docs/development.md: development setup and maintainer workflow.
- docs/README.md: navigation to all guides and official OpenAPI.

## Release availability

Latest remains stable 0.4.0 with no binary assets. The README carries a dated availability notice and points to the explicit RC instructions only in docs/install/. Latest curl examples are prepared for stable and are not represented as verified today. No release is created and #72 stays open until its stable-link acceptance is fulfilled. The PR includes Closes #72 for normal issue linkage, but the repository default branch is main and this PR targets dev; retain the pending release gate in tasks and the PR description.

## Maintenance and validation

Maintainer skill and CONTRIBUTING direct detailed examples to docs/ and require synchronizing docs/cli.md with the generated CLI reference. Release instructions update pinned Composer examples in docs/install/php.md, while README URLs remain version-independent. Canonical media documentation requirements are amended through a spec delta.

Check content preservation against the original README, local file links and anchors, shell syntax, available native package identity/help, unchanged generated reference, make lint-openspec, make test-unit, make lint-all and git diff --check. Latest asset availability is a recorded external blocker; native Windows/Linux execution cannot be claimed from a macOS host. Check CI and bot review after creating the PR into dev. Archive only after merge.
