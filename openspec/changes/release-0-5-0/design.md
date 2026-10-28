# Design

Start from verified origin/dev b5a5adeb65acf5cbeccc6b2714102bfc2ff39421 in a new #68 worktree. Preserve the old docs/68-release-docs worktree and unrelated local files. Current main is RC-3 source 7b954abd4220335c416027653ae1e2f9f48d8d87.

Use the exact user-requested order: preparation PR into dev, then dev-to-main promotion PR. Require local gates, native packaging CI and review-thread handling before merges. Select the full main merge SHA and create immutable tag 0.5.0. Keep earlier RC tags/assets unchanged.

The stable changelog aggregates all changes after 0.4.0, including standalone/config/installers, doctor/media, local extraction, compression, distribution/docs/skill changes and verified fixes. Preserve dated RC entries as history. Empty Unreleased stays above stable; date 2026-10-28 follows Asia/Bishkek.

Prepare explicit stable examples but retain truthful pending-publication notices until assets are verified. Create a draft stable release with curated notes; the existing tag workflow uploads its four accepted archives and SHA256SUMS. Publish only after that workflow succeeds, using non-prerelease and Latest=true. Run exact-version public acceptance on main, check all Latest archive routes and default stable installation, then update availability text and the verified stable CLI map.

Record the user's explicit stable-release direction as authorization rather than fabricated manual-test evidence. Release verification uses isolated mock-server acceptance, never the user's real credentials. Complete #72 only after four Latest links and native examples are verified; archive only after linked PRs merge. Synchronize completed release evidence and final docs into both branches, preserving exact tag/source identity.
