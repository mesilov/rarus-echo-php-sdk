# RARUS Echo Transcription Plugin Distribution

## Repository Plugin

The maintained plugin source lives at:

```text
.agent-plugins/rarus-echo-transcription/
```

The plugin is skills-only. It does not bundle an MCP server, app connector, hooks, or persistent credential storage.

## Claude Code

Claude Code supports standalone skills and plugin-packaged skills.

Standalone project or personal skill paths:

```text
.claude/skills/rarus-echo-transcription/SKILL.md
~/.claude/skills/rarus-echo-transcription/SKILL.md
```

For plugin development from this checkout, load the plugin directory:

```bash
claude --plugin-dir ./.agent-plugins/rarus-echo-transcription
```

To install from the repository-local marketplace, run these commands from the repository root:

```bash
claude plugin marketplace add ./ --scope user
claude plugin install rarus-echo-transcription@rarus-echo-plugins
```

The plugin namespace is `rarus-echo-transcription`, so the namespaced skill invocation is:

```text
/rarus-echo-transcription:transcribe downloads/audio.ogg --language=ru --task-type=diarization --speakers-correction
```

The repository marketplace lives at:

```text
.claude-plugin/marketplace.json
```

After adding the repository as a marketplace, install:

```text
/plugin install rarus-echo-transcription@rarus-echo-plugins
```

## Codex-Compatible Hosts

The OpenAI/Codex plugin manifest lives at:

```text
.agent-plugins/rarus-echo-transcription/.codex-plugin/plugin.json
```

The repository marketplace lives at:

```text
.agents/plugins/marketplace.json
```

Codex-compatible hosts that support repository marketplaces can discover the plugin from that file and install `rarus-echo-transcription`. The installed skill should be invoked by its skill name through the host's normal skill syntax; for example:

```bash
codex plugin marketplace add .
codex plugin add rarus-echo-transcription@rarus-echo-plugins
```

```text
$transcribe downloads/audio.ogg --language=ru --task-type=diarization --speakers-correction
```

Exact skill invocation syntax is host-specific. The shared skill entrypoint remains:

```text
skills/transcribe/SKILL.md
```

## Public Or Team Distribution

For public or team distribution, publish a marketplace repository or release archive and pin installs by tag/ref. Avoid relying on mutable branches for production workflows unless the team explicitly wants automatic updates.

If submitting a skills-only Claude Code plugin to OpenAI, keep each skill at `skills/<skill-name>/SKILL.md` with its references and scripts. Do not rely on Claude-only marketplace files or local settings for the core workflow.
