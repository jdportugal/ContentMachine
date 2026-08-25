# ContentMachine

## Remotion skills (clips pipeline)

The official Remotion skills (`npx skills add remotion-dev/skills`, installed at `.agents/skills/`, symlinked into `.claude/skills/`) are for the **clips pipeline's own LLM calls**, not for Claude Code sessions. When `EffectGenerator`/`BackgroundGenerator` run through the local `claude` CLI (`RunsClaudeCli`), they pass `useRemotionSkills` so the CLI runs from the project root and loads those skills while writing Remotion TSX. The API/OpenAI/DeepSeek paths can't use skills, so this only applies locally where the CLI is used.
