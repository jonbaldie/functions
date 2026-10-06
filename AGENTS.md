# Agent Instructions

## Agent skills

### Issue tracker

Issues live in this repo's GitHub Issues (`jonbaldie/functions`), operated via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

## Non-Interactive Shell Commands

**ALWAYS use non-interactive flags** with file operations to avoid hanging on confirmation prompts.

Shell commands like `cp`, `mv`, and `rm` may be aliased to include `-i` (interactive) mode on some systems, causing the agent to hang indefinitely waiting for y/n input.

**Use these forms instead:**
```bash
# Force overwrite without prompting
cp -f source dest           # NOT: cp source dest
mv -f source dest           # NOT: mv source dest
rm -f file                  # NOT: rm file

# For recursive operations
rm -rf directory            # NOT: rm -r directory
cp -rf source dest          # NOT: cp -r source dest
```

**Other commands that may prompt:**
- `scp` - use `-o BatchMode=yes` for non-interactive
- `ssh` - use `-o BatchMode=yes` to fail instead of prompting
- `apt-get` - use `-y` flag
- `brew` - use `HOMEBREW_NO_AUTO_UPDATE=1` env var

## Cursor Cloud specific instructions

- Keep `php` on 8.1 (`update-alternatives --set php /usr/bin/php8.1`). `composer.lock` resolves on 8.1; `phpspec/prophecy` v1.15.0 requires PHP below 8.2.
- Webpack Encore 0.33 (webpack 4) on Node 17+ needs `NODE_OPTIONS=--openssl-legacy-provider` for `yarn encore dev`.
- `composer install` writes `.key` via `key.php`. The dev server is `php -S 0.0.0.0:3000 index.php` from `public/`. Homepage: `http://127.0.0.1:3000/` (`It works!`). Checks: `./vendor/bin/phpunit ./tests --testdox`, `./vendor/bin/phan --allow-polyfill-parser`, `./vendor/bin/phpa ./src`.
