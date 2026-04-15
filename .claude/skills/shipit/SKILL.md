---
name: shipit
description: Push the current branch, create a GitHub PR back to main, then checkout main. Use when all commits are done and the work is ready to ship.
disable-model-invocation: true
allowed-tools: Bash(git *) Bash(gh *)
---

## Ship It

Push the current branch, create a PR, and return to main.

### Steps

1. **Verify state.** Confirm you are NOT on main (nothing to ship from main). Check for uncommitted changes — if any exist, stop and ask.

2. **Push the branch.**
   ```
   git push origin HEAD
   ```

3. **Gather context.** Run `git log main..HEAD --oneline` and `git diff main...HEAD --stat` to understand what the branch contains.

4. **Create the PR.** Use `gh pr create` targeting main. Write a concise title (under 70 chars). The body should have:
   - `## Summary` — bullet points covering the key changes across ALL commits, not just the last one.
   - `## Test plan` — bulleted checklist of what was tested (composer check, smoke tests, manual verification, etc.).
   - The attribution line: `Generated with [Claude Code](https://claude.com/claude-code)`

5. **Return to main.** After the PR is created:
   ```
   git checkout main
   ```

6. **Report.** Show the PR URL.
