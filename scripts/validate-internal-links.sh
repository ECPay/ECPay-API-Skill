#!/usr/bin/env bash
# validate-internal-links.sh — 驗證 guides/、commands/ 及所有 Markdown 檔案中的跨指南連結有效性
# 掃描整個 repo 的所有 Markdown 檔案（含 guides/、commands/、references/、scripts/）
# 中的 guides/NN 及 guides/NN-slug.md 參照，確認目標檔案存在
# 用法: bash scripts/validate-internal-links.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ERRORS=0

# Collect all existing guide files (numbered guides only, not lang-standards)
declare -A EXISTING_GUIDES
while IFS= read -r -d '' f; do
  base="$(basename "$f")"
  EXISTING_GUIDES["$base"]=1
done < <(find "$ROOT/guides" -maxdepth 1 -name '[0-9][0-9]-*.md' -print0)

# Extract all guides/NN-slug.md references and verify each exists
while IFS= read -r ref; do
  [ -z "$ref" ] && continue
  fname="${ref#guides/}"
  if [ -z "${EXISTING_GUIDES[$fname]+_}" ]; then
    echo "  BROKEN: $ref" >&2
    ERRORS=$((ERRORS + 1))
  fi
done < <(grep -r --include='*.md' -oh 'guides/[0-9][0-9]-[a-z0-9-]*\.md' "$ROOT" | sort -u || true)

# Extract guides/NN (short-form) references and verify a matching guide file exists
while IFS= read -r ref; do
  [ -z "$ref" ] && continue
  num="${ref#guides/}"
  found=0
  for key in "${!EXISTING_GUIDES[@]}"; do
    if [[ "$key" == "${num}-"* ]]; then
      found=1
      break
    fi
  done
  if [ "$found" -eq 0 ]; then
    echo "  BROKEN short-ref: $ref (no guide file starting with ${num}-)" >&2
    ERRORS=$((ERRORS + 1))
  fi
done < <(grep -r --include='*.md' -oh 'guides/[0-9][0-9]\b' "$ROOT" | sort -u || true)

if [ "$ERRORS" -gt 0 ]; then
  echo "❌  $ERRORS broken internal guide reference(s) found. See output above." >&2
  exit 1
fi

GUIDE_COUNT=$(find "$ROOT/guides" -maxdepth 1 -name '[0-9][0-9]-*.md' | wc -l | tr -d ' ')
SRC_COUNT=$(find "$ROOT" -name '*.md' -not -path '*/.git/*' -not -path '*/SDK_PHP/*' | wc -l | tr -d ' ')
echo "✅  All internal guides/ references are valid (${GUIDE_COUNT} guides, ${SRC_COUNT} source files scanned)."
