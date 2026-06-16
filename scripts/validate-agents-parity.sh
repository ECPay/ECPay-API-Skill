#!/usr/bin/env bash
# validate-agents-parity.sh — 驗證 AGENTS.md 與 GEMINI.md 的關鍵規則區段一致性
# 受影響區段：## 決策樹、## 關鍵規則（必須遵守）、## 測試帳號
# 用法: bash scripts/validate-agents-parity.sh
set -euo pipefail

AGENTS="AGENTS.md"
GEMINI="GEMINI.md"

if [ ! -f "$AGENTS" ] || [ ! -f "$GEMINI" ]; then
  echo "Error: Must run from repo root (AGENTS.md or GEMINI.md not found)" >&2
  exit 1
fi

# ─── Part 1: AGENTS.md ↔ GEMINI.md literal parity ───────────────────────────
# Extract invariant sections from ## 決策樹 through ## 即時 API 規格 (exclusive).
# These three sections must be identical between the two platform entry point files:
#   - ## 決策樹 (routing decision tree)
#   - ## 關鍵規則（必須遵守）(27 critical integration rules)
#   - ## 測試帳號 (test credentials)
# Platform-specific sections (## 啟動指示 and ## 即時 API 規格) are intentionally different.
agents_section=$(awk '/^## 即時 API 規格$/{exit} /^## 決策樹$/{p=1} p' "$AGENTS")
gemini_section=$(awk '/^## 即時 API 規格$/{exit} /^## 決策樹$/{p=1} p' "$GEMINI")

if [ "$agents_section" = "$gemini_section" ]; then
  echo "✅  AGENTS.md ↔ GEMINI.md invariant sections are identical (決策樹 + 關鍵規則 + 測試帳號)."
else

  echo "❌  AGENTS.md ↔ GEMINI.md divergence detected in invariant sections:" >&2
  echo "" >&2
  diff <(echo "$agents_section") <(echo "$gemini_section") >&2 || true
  echo "" >&2
  echo "Please sync the above changes across both files. Refer to CONTRIBUTING.md for details." >&2
  exit 1
fi

# ─── Part 1b: AGENTS.md safety-rule content checks ───────────────────────────
# Verify AGENTS.md contains safety rules that exist in SKILL.md AI注意事項
# but are NOT caught by the literal parity comparison above.
# Add a keyword pair here whenever a new cross-platform safety rule is introduced.
agents_rule_missing=()
grep -q "假設所有" "$AGENTS" || agents_rule_missing+=("Rule 23 (API response format): '不可假設所有 API 回應都是 JSON' missing from AGENTS.md")
grep -q "10100073"   "$AGENTS" || agents_rule_missing+=("Rule 24 (ATM/CVS code): 'RtnCode=10100073 取號成功' missing from AGENTS.md")
grep -q "冪等"       "$AGENTS" || agents_rule_missing+=("Rule 25 (Callback idempotency): '冪等' missing from AGENTS.md")
grep -q "消毒"       "$AGENTS" || agents_rule_missing+=("Rule 26 (Input validation): '消毒' missing from AGENTS.md")
grep -q "02-2655-1775" "$AGENTS" || agents_rule_missing+=("Rule 27 (Out of scope): '02-2655-1775' missing from AGENTS.md")

if [ ${#agents_rule_missing[@]} -gt 0 ]; then
  echo "❌  AGENTS.md is missing safety rules that exist in SKILL.md:" >&2
  for m in "${agents_rule_missing[@]}"; do
    echo "    • $m" >&2
  done
  echo "    Sync these rules to AGENTS.md and GEMINI.md. See CONTRIBUTING.md for details." >&2
  exit 1
fi
echo "✅  AGENTS.md content checks passed (all required cross-platform safety rules present)."

# ─── Part 2: Self-test — verify awk section extraction works ──────────────────
# Validates that Part 1's awk extraction produced meaningful content.
# Reuses $agents_section (already extracted in Part 1) to avoid re-running awk.
# NOTE: On Windows (Git Bash/PowerShell), awk Chinese character patterns may not match.
#       This self-test produces a WARNING (not failure) in that case, since CI (ubuntu-latest)
#       is the authoritative validation environment where awk works correctly.
selftest_len=${#agents_section}
if [ "$selftest_len" -lt 200 ]; then
  echo "⚠️   Self-test WARNING: awk extracted only ${selftest_len} chars from $AGENTS."
  echo "    On Windows/non-UTF8 locales, Chinese character awk patterns may not match."
  echo "    Run this script on Linux/macOS (or GitHub Actions) for full validation."
else
  # On Linux/macOS where awk works, verify ASCII markers that must exist
  if ! echo "$agents_section" | grep -q "MerchantID"; then
    echo "❌  Self-test failed: extracted section from $AGENTS is missing 'MerchantID' (test accounts)." >&2
    exit 1
  fi
  if ! echo "$agents_section" | grep -q "guides/01"; then
    echo "❌  Self-test failed: extracted section from $AGENTS is missing 'guides/01' (decision tree)." >&2
    exit 1
  fi
  echo "✅  validate-agents-parity.sh self-test passed (awk extraction verified, ${selftest_len} chars)."
fi
