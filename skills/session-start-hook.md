Set up a SessionStart hook so Claude Code on the web can run linters and tests automatically at session start.

1. Check what test and lint commands are available (package.json scripts)
2. Create or update `.claude/settings.json` with a hooks section:

```json
{
  "hooks": {
    "SessionStart": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "<install command if needed> && <lint command>"
          }
        ]
      }
    ]
  }
}
```

3. Confirm the hook runs cleanly with no errors
4. Report what the hook does on each session start
