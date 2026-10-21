---
titulo: 'Claude Code hooks'
tipo: relatorio_topico
modo: topico
inicio: '2026-09-12'
fim: '2026-09-12'
total: 0
gerado_em: '2026-09-12T10:33:03+00:00'
estado: arquivado
tags:
  - noticias
  - topico
  - topico
dados: '{"titulo":"Claude Code hooks","tipo":"topico","modo":"topico","inicio":"2026-09-12","fim":"2026-09-12","gerado_em":"2026-09-12T10:33:03+00:00","total":0,"por_plataforma":[],"metodo":"web","resumo":"Researched on the web and written for «Claude Code hooks».","redacao":"Research is done. I have the official reference and guide, the changelog for dates, and one third-party example. Writing the scripts now.\n\n**Hook** Claude Code will happily run `rm -rf` if it decides that''s the fix. You can make that physically impossible.\n\nClaude Code has a feature called hooks. They''re shell scripts that run at fixed points in the agent loop, and the one you want is called PreToolUse. It fires before every tool call, and if your script exits with code 2, the call is blocked and whatever you wrote to stderr goes back to Claude as the reason. Here''s the whole thing: read the JSON on stdin, pull out `.tool_input.command` with jq, grep for `rm -rf`, echo a message to stderr, exit 2. Then register it in `.claude\/settings.json` under `PreToolUse` with the matcher set to `Bash`. One gotcha: exit 1 does not block. Only exit 2 blocks. Exit 1 just prints a warning and lets the command through. And this holds even when you run Claude with `--dangerously-skip-permissions`. The docs say a deny from a PreToolUse hook works in every permission mode, including bypass. Comment \"hooks\" and I''ll send you the link.\n\n---\n\n**Hook** You''re still fixing Claude''s formatting by hand. There''s a one-line config that does it for you forever.\n\nThe event is called PostToolUse. It fires after a tool call succeeds, and you can filter it to just file edits with the matcher `Edit|Write`. The command is one line: `jq -r ''.tool_input.file_path'' | xargs npx prettier --write`. Drop that into `.claude\/settings.json` in your project root and commit it, so your whole team gets it. Test it by asking Claude to add a line with single quotes to a JavaScript file. Open the file and Prettier has already rewritten them to double quotes. If you want the same thing for Go, swap prettier for gofmt. When the hook works, it shows nothing in the chat, so don''t panic if it looks like nothing happened. Type `\/hooks` to see every hook that''s loaded and which file it came from. Comment \"format\" and I''ll send you the link.\n\n---\n\n**Hook** Claude Code says \"done\" and it''s not done. You can make it check its own work before it''s allowed to stop.\n\nThere''s a hook event called Stop that fires every time Claude finishes a response. And since prompt-based hooks exist, you don''t even need a shell script. Set the hook type to `prompt` and write: \"Check if all tasks are complete. If not, respond with ok false and the reason.\" Claude Code sends that to Haiku by default, and if Haiku says no, the reason gets fed back to Claude as its next instruction and it keeps working. Now the gotcha. A Stop hook fires on every response, not just task completion, so it can loop. Claude Code overrides it after eight consecutive blocks with no progress. If you write a shell version instead, check the `stop_hook_active` field in the input and exit 0 when it''s true, or you''ll fight the cap. And if you actually need more than eight rounds, there''s an environment variable for it: `CLAUDE_CODE_STOP_HOOK_BLOCK_CAP`. Comment \"stop\" and I''ll send you the link.\n\n---\n\n**Hook** Claude just edited your .env file. Three lines of bash and it never happens again.\n\nSame mechanism as blocking bash commands, just aimed at file edits. Make a script at `.claude\/hooks\/protect-files.sh`. Read stdin, pull out `.tool_input.file_path` with jq, and check it against a list: `.env`, `package-lock.json`, and anything under `.git\/`. If it matches, echo \"Blocked\" to stderr and exit 2. Run `chmod +x` on it, that''s the step everyone forgets. Then register it in `.claude\/settings.json` under `PreToolUse` with the matcher `Edit|Write`. Claude gets the blocked message as feedback and adjusts instead of just failing. Two more things from the docs. Command hooks get a 10 minute default timeout, so a hanging script won''t stall you forever, and you can set `timeout` per hook in seconds. And the hooks system has grown fast. The reference now lists 33 events, and just this year they added StopFailure in March, PermissionDenied in March, and PostToolBatch in June. Comment \"protect\" and I''ll send you the link.\n\n---\n\n**Sources**\n- https:\/\/code.claude.com\/docs\/en\/hooks\n- https:\/\/code.claude.com\/docs\/en\/hooks-guide\n- https:\/\/claudefa.st\/blog\/guide\/changelog\n- https:\/\/claudefa.st\/blog\/tools\/hooks\/hooks-guide\n- https:\/\/dev.to\/tyson_cung\/how-to-block-dangerous-commands-with-claude-code-hooks-g5o","redacao_metodo":"claude-cli","topicos":[],"destaques":[],"ideias_guiao":[],"fontes":[]}'
---

# Claude Code hooks

> 0 item(s) · método: web · 2026-09-12T10:33:03+00:00

## Guiões

Research is done. I have the official reference and guide, the changelog for dates, and one third-party example. Writing the scripts now.

**Hook** Claude Code will happily run `rm -rf` if it decides that's the fix. You can make that physically impossible.

Claude Code has a feature called hooks. They're shell scripts that run at fixed points in the agent loop, and the one you want is called PreToolUse. It fires before every tool call, and if your script exits with code 2, the call is blocked and whatever you wrote to stderr goes back to Claude as the reason. Here's the whole thing: read the JSON on stdin, pull out `.tool_input.command` with jq, grep for `rm -rf`, echo a message to stderr, exit 2. Then register it in `.claude/settings.json` under `PreToolUse` with the matcher set to `Bash`. One gotcha: exit 1 does not block. Only exit 2 blocks. Exit 1 just prints a warning and lets the command through. And this holds even when you run Claude with `--dangerously-skip-permissions`. The docs say a deny from a PreToolUse hook works in every permission mode, including bypass. Comment "hooks" and I'll send you the link.

---

**Hook** You're still fixing Claude's formatting by hand. There's a one-line config that does it for you forever.

The event is called PostToolUse. It fires after a tool call succeeds, and you can filter it to just file edits with the matcher `Edit|Write`. The command is one line: `jq -r '.tool_input.file_path' | xargs npx prettier --write`. Drop that into `.claude/settings.json` in your project root and commit it, so your whole team gets it. Test it by asking Claude to add a line with single quotes to a JavaScript file. Open the file and Prettier has already rewritten them to double quotes. If you want the same thing for Go, swap prettier for gofmt. When the hook works, it shows nothing in the chat, so don't panic if it looks like nothing happened. Type `/hooks` to see every hook that's loaded and which file it came from. Comment "format" and I'll send you the link.

---

**Hook** Claude Code says "done" and it's not done. You can make it check its own work before it's allowed to stop.

There's a hook event called Stop that fires every time Claude finishes a response. And since prompt-based hooks exist, you don't even need a shell script. Set the hook type to `prompt` and write: "Check if all tasks are complete. If not, respond with ok false and the reason." Claude Code sends that to Haiku by default, and if Haiku says no, the reason gets fed back to Claude as its next instruction and it keeps working. Now the gotcha. A Stop hook fires on every response, not just task completion, so it can loop. Claude Code overrides it after eight consecutive blocks with no progress. If you write a shell version instead, check the `stop_hook_active` field in the input and exit 0 when it's true, or you'll fight the cap. And if you actually need more than eight rounds, there's an environment variable for it: `CLAUDE_CODE_STOP_HOOK_BLOCK_CAP`. Comment "stop" and I'll send you the link.

---

**Hook** Claude just edited your .env file. Three lines of bash and it never happens again.

Same mechanism as blocking bash commands, just aimed at file edits. Make a script at `.claude/hooks/protect-files.sh`. Read stdin, pull out `.tool_input.file_path` with jq, and check it against a list: `.env`, `package-lock.json`, and anything under `.git/`. If it matches, echo "Blocked" to stderr and exit 2. Run `chmod +x` on it, that's the step everyone forgets. Then register it in `.claude/settings.json` under `PreToolUse` with the matcher `Edit|Write`. Claude gets the blocked message as feedback and adjusts instead of just failing. Two more things from the docs. Command hooks get a 10 minute default timeout, so a hanging script won't stall you forever, and you can set `timeout` per hook in seconds. And the hooks system has grown fast. The reference now lists 33 events, and just this year they added StopFailure in March, PermissionDenied in March, and PostToolBatch in June. Comment "protect" and I'll send you the link.

---

**Sources**
- https://code.claude.com/docs/en/hooks
- https://code.claude.com/docs/en/hooks-guide
- https://claudefa.st/blog/guide/changelog
- https://claudefa.st/blog/tools/hooks/hooks-guide
- https://dev.to/tyson_cung/how-to-block-dangerous-commands-with-claude-code-hooks-g5o

## Resumo

Researched on the web and written for «Claude Code hooks».

## Destaques


## Tópicos
