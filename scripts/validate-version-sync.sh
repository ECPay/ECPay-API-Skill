#!/usr/bin/env bash
# validate-version-sync.sh
# Verifies that the version number in SKILL.md is consistent across all 9 version-synced files.
# Exit code 0 = all consistent; non-zero = mismatch(es) found.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Extract canonical version from SKILL.md front-matter (e.g. version: "1.0" -> 1.0)
# Use tr to strip carriage returns in case the file has CRLF line endings.
CANONICAL=$(grep -m1 '^version:' "$ROOT/SKILL.md" | tr -d '\r' | sed 's/version:[[:space:]]*"\(.*\)"/\1/')

if [[ -z "$CANONICAL" ]]; then
  echo "ERROR: Could not extract version from SKILL.md front-matter" >&2
  exit 1
fi

echo "Canonical version from SKILL.md: V$CANONICAL"
echo ""

ERRORS=0

check_file() {
  local FILE="$1"
  # Escape dots in version (e.g. "1.0" → "1\.0") and anchor with non-alphanumeric boundary
  # to prevent "V1.0" matching "V1.10" (substring) or "V1X0" (unescaped dot = any char)
  local ESCAPED="${CANONICAL//./\\.}"
  if grep -qE "V${ESCAPED}([^[:alnum:]]|$)" "$ROOT/$FILE"; then
    echo "  OK  $FILE"
  else
    echo "  FAIL $FILE  (expected pattern 'V$CANONICAL' not found)"
    ERRORS=$((ERRORS + 1))
  fi
}

FILES=(
  "SKILL_OPENAI.md"
  "README.md"
  "SETUP.md"
  "AGENTS.md"
  "GEMINI.md"
  ".github/copilot-instructions.md"
)

for f in "${FILES[@]}"; do
  check_file "$f"
done

# Check CONTRIBUTING.md English summary separately (uses different format)
if grep -q "29 integration guides" "$ROOT/CONTRIBUTING.md"; then
  echo "  OK  CONTRIBUTING.md (guide count)"
else
  echo "  FAIL CONTRIBUTING.md  (expected '29 integration guides' in English summary)"
  ERRORS=$((ERRORS + 1))
fi

echo ""
if [[ $ERRORS -eq 0 ]]; then
  echo "Version sync check passed: all 8 synced files (1 source + 7 dependents) contain V$CANONICAL"
else
  echo "Version sync check FAILED: $ERRORS file(s) missing V$CANONICAL or guide count" >&2
  exit 1
fi