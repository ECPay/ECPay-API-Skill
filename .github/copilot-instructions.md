# Copilot Instructions — ECPay API Skill

This is an **AI Skill repository** (Markdown knowledge base), not a traditional software project. There is no build system, no package manager, and no application code. All content is Markdown files consumed by AI coding assistants.

## Architecture

Three-layer knowledge system:

- **`SKILL.md`** — AI entry point: decision trees, navigation, safety rules, test accounts. Routes queries to the correct guide + reference.
- **`guides/`** (29 files, indexed 00-25 + 02a/02b/02c) — Static integration knowledge with SNAPSHOT parameter tables. Provides process logic, caveats, code examples in 12 languages.
- **`references/`** (20 files, 443 URLs) — Real-time API spec gateway. Each file contains organized URLs pointing to `developers.ecpay.com.tw`. AI uses `web_fetch` on these URLs to get the latest official parameter specs before generating code.

The critical pattern: **guides/ tells you HOW to integrate; references/ gives you the CURRENT spec to integrate against.** Guides are sufficient for prototyping and initial development (parameter stability >95%). For production deployment or when API behavior doesn't match expectations, use `web_fetch` on references/ URLs to verify the latest specs.

### Four HTTP Protocol Modes

| Mode | Auth | Format | Services |
|------|------|--------|----------|
| CMV-SHA256 | CheckMacValue + SHA256 | Form POST | AIO payment |
| AES-JSON | AES-128-CBC (e-receipt also supports AES-128-GCM) | JSON POST | ECPG, invoices, logistics v2, e-receipt |
| AES-JSON + CMV | AES-128-CBC + CheckMacValue (SHA256) | JSON POST | E-tickets (CMV formula differs from AIO) |
| CMV-MD5 | CheckMacValue + MD5 | Form POST | Domestic logistics |

### Supporting Files

- **`SKILL_OPENAI.md`** — Condensed version of SKILL.md for ChatGPT GPTs (custom GPT). Upload to GPT Builder Knowledge. When both exist, SKILL_OPENAI.md takes precedence for GPT platforms.
- **`SETUP.md`** — Unified installation guide for Codex CLI, Gemini CLI, and ChatGPT GPTs. Contains Knowledge Files list for GPTs.
- **`AGENTS.md`** — Condensed entry point for OpenAI Codex CLI. Contains role, decision tree, critical rules, and test accounts.
- **`GEMINI.md`** — Condensed entry point for Google Gemini CLI. Mirrors AGENTS.md with Gemini-specific notes (Google Search instead of web_fetch).
- **`commands/`** (6 files) — Claude Code slash commands. Navigation only, ≤20 lines each. Do not duplicate SKILL.md logic.
- **`test-vectors/`** — Deterministic test vectors for CheckMacValue (SHA256/MD5), AES-128-CBC + AES-128-GCM encryption, and URL encoding comparison. Run `python test-vectors/verify.py` (requires `pycryptodome`) to validate all 25 vectors.
- **`scripts/SDK_PHP/`** — Official ECPay PHP SDK with 134 verified examples. Read-only reference; do not modify.

## Validation Commands

```bash
# Validate AI Section Index line numbers in guides/13, 14, 23
bash scripts/validate-ai-index.sh

# Validate version sync across all 9 platform entry files
bash scripts/validate-version-sync.sh

# Validate AGENTS.md ↔ GEMINI.md invariant sections (decision tree, critical rules, test accounts)
bash scripts/validate-agents-parity.sh

# Validate all internal cross-guide links (no broken references)
bash scripts/validate-internal-links.sh

# Validate guides ↔ references ↔ scripts cross-consistency
bash scripts/validate-guides-refs-consistency.sh

# Verify all 21 test vectors (CheckMacValue, AES, URL encoding)
pip install pycryptodome && python test-vectors/verify.py

# Manually trigger URL check (also runs weekly via GitHub Actions)
# Go to Actions tab → "Validate Reference URLs" → Run workflow
```

> **Platform note**: `validate-ai-index.sh` and URL-check scripts use `grep -P` (Perl regex), not available in macOS BSD grep or Windows Git Bash. Install GNU grep on macOS (`brew install grep`, ensure `ggrep` is on PATH), or run via CI (ubuntu-latest).

CI runs automatically:
- **`validate.yml`**: On PRs and main-branch pushes that modify any guide or script file, `test-vectors/`, or platform entry files (SKILL.md, AGENTS.md, etc.); runs all four validation scripts + `python test-vectors/verify.py`
- **`validate-references.yml`**: Weekly Monday 02:00 UTC + manual trigger

## Key Conventions

### Version Sync (mandatory)

When changing the version number, update these files. **CI-validated** (script fails if missing):

- `SKILL.md` front-matter `version` field (line 3)
- `SKILL_OPENAI.md` version reference (line 3)
- `README.md` version string (line 5)
- `SETUP.md` version reference (line 3)
- `AGENTS.md` version reference (line 3)
- `GEMINI.md` version reference (line 3)
- `.github/copilot-instructions.md` version reference (line 75)
- `CONTRIBUTING.md` version reference (English summary section)

**Manual-update** (not checked by script, but should stay in sync):

- `vscode_copilot.md` · `visual_studio_2026.md` · `業務說明.md`
- `../CLAUDE.md` version and guide count (root repo instructions)

`validate-version-sync.sh` checks the canonical version pattern from SKILL.md front-matter (e.g. `V3.1`). `CHANGELOG.md` uses three-segment semver (`1.0.0`) per Keep a Changelog convention — the entry-point files and CHANGELOG may use the same or different number of segments; both must be updated when releasing.

### Platform Entry File Parity

`AGENTS.md` (Codex CLI) and `GEMINI.md` (Gemini CLI) must be **literally identical** in three sections: `## 決策樹`, `## 關鍵規則（必須遵守）`, and `## 測試帳號`. The `validate-agents-parity.sh` script enforces this in CI.

`SKILL_OPENAI.md` (ChatGPT GPTs) is intentionally different — it uses English, different headings (`# Critical Rules`), and GPT-specific rules. It is **not** checked by the parity script. When adding a new critical rule, manually sync the substance (not the exact text) into `SKILL_OPENAI.md` and add a grep keyword to `validate-agents-parity.sh` Part 1b.

### SNAPSHOT Timestamps

Parameter tables in `guides/` are marked with SNAPSHOT blockquotes in this exact format:

```
> ⚠️ **SNAPSHOT 2026-XX** | 來源：`references/Path/檔案名.md`
```

When updating a guide's parameter content, update its SNAPSHOT date. Guides also have a line-1 header with `最後更新：2026-XX` — **both dates must match**. Update both when modifying parameter content. Do NOT bump the header date for structural-only changes (renumbering, link fixes, formatting) that don't touch parameter content. AI is instructed to use guides/ for initial development and verify against live specs via `references/` before production deployment.

### AI Section Index

`guides/13`, `guides/14`, and `guides/23` contain HTML comment indexes with line number ranges for each language section (e.g., `Python: line 103-157`). After editing these files, **always run `bash scripts/validate-ai-index.sh`** to confirm line numbers still point to correct headings.

### Security-Critical: Timing-Safe Comparison

All crypto verification code **must** use timing-safe comparison functions. Never use `==` or `===` for CheckMacValue/signature validation:

| Language | Function |
|----------|----------|
| PHP | `hash_equals()` |
| Python | `hmac.compare_digest()` |
| Node.js | `crypto.timingSafeEqual()` |
| TypeScript | `crypto.timingSafeEqual()` |
| Go | `subtle.ConstantTimeCompare()` |
| Java | `MessageDigest.isEqual()` |
| Kotlin | `MessageDigest.isEqual()` |
| C# | `CryptographicOperations.FixedTimeEquals()` |
| C | `CRYPTO_memcmp()` **(OpenSSL)** |
| C++ | `CRYPTO_memcmp()` **(OpenSSL)** |
| Rust | `subtle::ConstantTimeEq` |
| Swift | `HMAC<SHA256>.isValidAuthenticationCode()` |
| Ruby | `OpenSSL.secure_compare()` |

### URL Encoding: Two Different Functions

This is the #1 source of cross-language bugs. ECPay has two distinct URL encode flows:

- **`ecpayUrlEncode`** (for CheckMacValue): `urlencode()` → `strtolower()` → `.NET replacements` — see guides/13
- **`aesUrlEncode`** (for AES): `urlencode()` only (no .NET replacements, no lowercase) — see guides/14

**Never mix them.** The authoritative source is `scripts/SDK_PHP/src/Services/UrlService.php`.

### references/ Files Format

Each file follows this structure:
1. AI instruction header (blockquote explaining `web_fetch` usage)
2. Last verification date
3. Section headings with one URL per line

Do not change this format. URLs follow the pattern `https://developers.ecpay.com.tw/{numeric_id}.md`.

### Guide File Naming

Guides use `{NN}-{slug}.md` (zero-padded two-digit index, kebab-case slug). Current range: 00-24. When adding a guide, use the next available index and update `SKILL.md`'s decision tree and file index table.

## Modification Checklist

- [ ] Editing `guides/13`, `14`, or `23`? → Run `bash scripts/validate-ai-index.sh`
- [ ] Changing parameter tables in guides? → Update SNAPSHOT date
- [ ] Bumping version? → Sync across SKILL.md, SKILL_OPENAI.md, README.md, SETUP.md, AGENTS.md, GEMINI.md, copilot-instructions.md, CLAUDE.md (root), CONTRIBUTING.md (English summary)
- [ ] Adding a new language? → Create `guides/lang-standards/{language}.md` + add crypto impl to guides/13+14 + E2E to guides/23 + update AI Section Index in all three + run `bash scripts/validate-ai-index.sh` + update language count in SKILL.md
- [ ] Adding a new API? → Add guide + reference file + update SKILL.md decision tree
- [ ] Modifying critical rules in AGENTS.md/GEMINI.md? → Run `bash scripts/validate-agents-parity.sh`
- [ ] Modifying `commands/`? → Keep ≤20 lines, navigation focus only
- [ ] `scripts/SDK_PHP/`? → Track official SDK only, no custom changes
