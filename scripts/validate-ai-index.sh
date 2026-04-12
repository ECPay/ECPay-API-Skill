#!/bin/bash
# validate-ai-index.sh — 驗證 AI Section Index 行號是否準確
# 用法: bash scripts/validate-ai-index.sh
set -eu

# Detect GNU grep (required for -P / Perl regex); falls back to ggrep on macOS (brew install grep)
GREP=grep
if ! echo "test" | grep -P 'test' >/dev/null 2>&1; then
  if command -v ggrep >/dev/null 2>&1; then
    GREP=ggrep
  else
    echo "⚠️  WARNING: GNU grep with -P support not found." >&2
    echo "   On macOS: brew install grep" >&2
    echo "   Skipping AI Section Index validation (run on Linux/CI for full check)." >&2
    exit 0
  fi
fi

TMPFILE=$(mktemp)
: > "$TMPFILE"

check_line() {
  local file="$1" label="$2" expected_line="$3"
  actual=$(sed -n "${expected_line}p" "$file")
  # End-of-range lines point to the last content line, not a heading
  if echo "$label" | grep -q "(end)"; then
    if [ -z "$actual" ]; then
      echo "FAIL: $file line $expected_line ($label)"
      echo "  Expected: non-empty content line"
      echo "  Actual:   (empty)"
      echo "1" >> "$TMPFILE"
    else
      echo "  ✓  $label (end) → \"${actual:0:60}\""
    fi
  else
    if ! echo "$actual" | grep -q "^#"; then
      echo "FAIL: $file line $expected_line ($label)"
      echo "  Expected: heading starting with '#'"
      echo "  Actual:   ${actual:0:80}"
      echo "  Hint: Update AI Section Index line number in $file"
      echo "1" >> "$TMPFILE"
    else
      echo "  ✓  $label → \"${actual:0:60}\""
    fi
  fi
}

echo "Validating AI Section Index..."

for file in guides/13-checkmacvalue.md guides/14-aes-encryption.md guides/23-multi-language-integration.md; do
  if [ ! -f "$file" ]; then
    echo "SKIP: $file not found"
    continue
  fi

  echo "  Checking $file..."

  # 提取 AI Section Index 區塊（HTML 註解內的行）
  # 支援格式: "Go E2E: line 63" 或 "Python: line 103-157"
  sed -n '/<!-- AI Section Index/,/-->/p' "$file" | \
    "$GREP" -oP '[\p{L}0-9#/.+_ -]+: line [0-9]+(-[0-9]+)?' | \
    while IFS= read -r entry; do
      # 用最後一個 ": line" 來分隔 label 和行號
      label=$(echo "$entry" | sed 's/: line [0-9].*$//')
      linenum_start=$(echo "$entry" | grep -oE 'line [0-9]+' | head -1 | grep -oE '[0-9]+')
      linenum_end=$(echo "$entry" | grep -oE 'line [0-9]+-[0-9]+' | grep -oE '[0-9]+$')
      if [ -n "$linenum_start" ]; then
        check_line "$file" "$label (start)" "$linenum_start"
      fi
      if [ -n "$linenum_end" ]; then
        check_line "$file" "$label (end)" "$linenum_end"
      fi
    done
done

# Phase 2: 交叉驗證 guides/23 導航表格與 AI Section Index 的起始行號一致
echo ""
echo "Cross-checking guides/23 navigation table against AI Section Index..."

guide23="guides/23-multi-language-integration.md"
if [ -f "$guide23" ]; then
  # 從 AI Section Index HTML 註解中提取所有起始行號（格式: "Label: line NNN-MMM"）
  # 這些行號已由 Phase 1 驗證為指向正確的 heading
  ai_starts=$(sed -n '/<!-- AI Section Index/,/-->/p' "$guide23" | \
    "$GREP" -oP '(?<=: line )[0-9]+' | sort -n | uniq)

  # 從導航表格中提取語言列的起始行號
  # 匹配格式: "| **語言** | ... | line NNN-MMM |"
  nav_starts=$("$GREP" -P '^\| \*\*' "$guide23" | \
    "$GREP" -oP '\| line \K[0-9]+(?=-[0-9]+\s*\|)' || true)

  if [ -z "$nav_starts" ]; then
    echo "  No numeric line entries in navigation table — skipping cross-check."
  else
    echo "  Checking navigation table start lines..."
    phase2_errors=0
    while IFS= read -r nav_start; do
      [ -z "$nav_start" ] && continue
      if ! echo "$ai_starts" | grep -qxF "$nav_start"; then
        echo "FAIL: guides/23 navigation table start line $nav_start not found in AI Section Index"
        echo "  Valid AI Section Index start lines: $(echo "$ai_starts" | tr '\n' ' ')"
        echo "1" >> "$TMPFILE"
        phase2_errors=$((phase2_errors + 1))
      fi
    done <<< "$nav_starts"

    if [ "$phase2_errors" -eq 0 ]; then
      echo "  Navigation table start lines match AI Section Index. ✓"
    fi
  fi
fi

ERRORS=$(wc -l < "$TMPFILE" 2>/dev/null || echo 0)
ERRORS=$(echo "$ERRORS" | tr -d ' ')
rm -f "$TMPFILE"

if [ "$ERRORS" = "0" ]; then
  echo ""
  echo "All AI Section Index entries are valid."
else
  echo ""
  echo "$ERRORS error(s) found. Please update AI Section Index."
  exit 1
fi
