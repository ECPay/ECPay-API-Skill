#!/usr/bin/env bash
# validate-agents-parity.sh — 驗證 AGENTS.md 與 GEMINI.md 的關鍵規則區段一致性
# 受影響區段：## 決策樹、## 關鍵規則（必須遵守）、## 測試帳號
# 用法: bash scripts/validate-agents-parity.sh
#
# 注意：SKILL_OPENAI.md（ChatGPT GPTs 入口）刻意使用英文、不同段落標題（# Critical Rules）
# 且包含 GPT 平台專屬規則（共 23 條 vs AGENTS.md 的 22 條），
# 故不納入本 script 的 literal text 比對。
# SKILL_OPENAI.md 的同步由 CONTRIBUTING.md §修改指南 步驟 2 的人工程序保障。
set -euo pipefail

AGENTS="AGENTS.md"
GEMINI="GEMINI.md"
SKILL_OPENAI="SKILL_OPENAI.md"

if [ ! -f "$AGENTS" ] || [ ! -f "$GEMINI" ] || [ ! -f "$SKILL_OPENAI" ]; then
  echo "Error: Must run from repo root (AGENTS.md, GEMINI.md, or SKILL_OPENAI.md not found)" >&2
  exit 1
fi

# ─── Part 1: AGENTS.md ↔ GEMINI.md literal parity ───────────────────────────
# Extract invariant sections from ## 決策樹 through ## 即時 API 規格 (exclusive).
# These three sections must be identical between the two platform entry point files:
#   - ## 決策樹 (routing decision tree)
#   - ## 關鍵規則（必須遵守）(24 critical integration rules)
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
# Verify AGENTS.md contains safety rules that exist in SKILL.md AI注意事項 and
# SKILL_OPENAI.md but are NOT caught by the literal parity comparison above.
# Add a keyword pair here whenever a new cross-platform safety rule is introduced.
agents_rule_missing=()
grep -q "假設所有" "$AGENTS" || agents_rule_missing+=("Rule 23 (API response format): '不可假設所有 API 回應都是 JSON' missing from AGENTS.md")
grep -q "10100073"   "$AGENTS" || agents_rule_missing+=("Rule 24 (ATM/CVS code): 'RtnCode=10100073 取號成功' missing from AGENTS.md")

if [ ${#agents_rule_missing[@]} -gt 0 ]; then
  echo "❌  AGENTS.md is missing safety rules that exist in SKILL.md and SKILL_OPENAI.md:" >&2
  for m in "${agents_rule_missing[@]}"; do
    echo "    • $m" >&2
  done
  echo "    Sync these rules to AGENTS.md and GEMINI.md. See CONTRIBUTING.md for details." >&2
  exit 1
fi
echo "✅  AGENTS.md content checks passed (all required cross-platform safety rules present)."

# ─── Part 2: SKILL_OPENAI.md sanity check ────────────────────────────────────
# SKILL_OPENAI.md uses English and has GPT-platform-specific additions (23 rules).
# AGENTS.md / GEMINI.md have 24 rules (they are identical — enforced by Part 1).
# We cannot do literal comparison of SKILL_OPENAI, so we validate structural invariants:
#   (a) At least 22 numbered critical rules (SKILL_OPENAI has 23; minimum ≥ 22 is a safety floor)
#   (b) Core safety patterns present (iframe, HashKey, AES-JSON, timing-safe)
#
# NOTE: The awk must NOT use a range pattern here because /^# [A-Za-z]/ matches the
# start heading "# Critical Rules (Must Follow)" itself, causing the range to close
# immediately and produce no output. Use a flag-based approach instead.
rule_count=$(awk '/^# Critical Rules/{found=1; next} found && /^# [A-Za-z]/{exit} found{print}' "$SKILL_OPENAI" | grep -c '^[0-9]') || rule_count=0
if [ "$rule_count" -lt 22 ]; then
  echo "❌  SKILL_OPENAI.md has only $rule_count numbered critical rules (expected ≥ 22)." >&2
  echo "    Check '# Critical Rules' section in SKILL_OPENAI.md." >&2
  exit 1
fi

# Per-rule keyword validation: each entry is "rule_description;;keyword1;;keyword2"
# Both keywords must be present; if either is missing the rule is flagged.
# Note: delimiter is ;; to avoid conflict with | in patterns like "1|OK".
declare -a RULE_CHECKS=(
  "Rule 1 (iframe ban);;iframe;;站內付 2.0"
  "Rule 2 (URL-encode mixing);;ecpayUrlEncode;;aesUrlEncode"
  "Rule 3 (API response format);;Never assume all API responses are JSON;;HTML"
  "Rule 4 (HashKey exposure);;HashKey;;frontend"
  "Rule 5 (ATM/CVS codes);;10100073;;awaiting payment"
  "Rule 6 (ECPG dual domain);;ecpg;;ecpayment"
  "Rule 7 (callback format);;1|OK;;ReturnURL"
  "Rule 8 (AES double-layer);;TransCode;;RtnCode"
  "Rule 14 (URL purposes);;OrderResultURL;;ClientBackURL"
  "Rule 15 (Callback HTTP 200);;status 200;;retry"
  "Rule 17 (ATM two callbacks);;PaymentInfoURL;;TWO callbacks"
  "Rule 19 (WebView failure);;WebView;;LINE"
  "Rule 20 (DoAction credit only);;DoAction;;PaymentType"
  "Rule 21 (ECPG vs 站內付);;ECPG is not the same;;站內付 2.0"
)

missing_patterns=()
# Global keyword checks (not tied to specific rule)
grep -q "iframe" "$SKILL_OPENAI" || missing_patterns+=("iframe")
grep -q "HashKey" "$SKILL_OPENAI" || missing_patterns+=("HashKey")
grep -q "AES-JSON" "$SKILL_OPENAI" || missing_patterns+=("AES-JSON")
grep -q "ECPG" "$SKILL_OPENAI" || missing_patterns+=("ECPG")
grep -q "timing-safe" "$SKILL_OPENAI" || missing_patterns+=("timing-safe")

# Per-rule keyword checks (using ;; as delimiter to avoid | conflicts)
for rule_check in "${RULE_CHECKS[@]}"; do
  rule_desc="${rule_check%%;;*}"
  remainder="${rule_check#*;;}"
  kw1="${remainder%%;;*}"
  kw2="${remainder#*;;}"
  if ! grep -qF "$kw1" "$SKILL_OPENAI" || ! grep -qF "$kw2" "$SKILL_OPENAI"; then
    missing_patterns+=("$rule_desc: missing '$kw1' or '$kw2'")
  fi
done

if [ ${#missing_patterns[@]} -gt 0 ]; then
  echo "❌  SKILL_OPENAI.md is missing critical safety patterns:" >&2
  for p in "${missing_patterns[@]}"; do
    echo "    • $p" >&2
  done
  echo "    Check the Critical Rules section in SKILL_OPENAI.md." >&2
  exit 1
fi

echo "✅  SKILL_OPENAI.md sanity check passed ($rule_count critical rules, all safety patterns present)."

# ─── Part 3: Self-test — verify awk section extraction works ──────────────────
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
