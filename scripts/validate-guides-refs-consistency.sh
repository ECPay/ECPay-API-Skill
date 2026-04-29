#!/usr/bin/env bash
# validate-guides-refs-consistency.sh — 驗證 guides/ 與 references/ 及 scripts/ 的交叉一致性
# 涵蓋 5 個維度：URL 格式、SDK 類別名、協定模式、測試帳號、SNAPSHOT 欄位名
# 用法: bash scripts/validate-guides-refs-consistency.sh
set -euo pipefail

ERRORS=()
WARNINGS=()

# ─── 維度 1: URL 格式一致性 ─────────────────────────────────────────────────────
# references/ 全部使用 /{id}.md 格式，guides/ 不應使用 ?p= 格式
echo "── 維度 1: URL 格式一致性 ──"
legacy_urls=$(grep -rn 'developers\.ecpay\.com\.tw/?p=' guides/ 2>/dev/null || true)
if [ -n "$legacy_urls" ]; then
  ERRORS+=("維度1: guides/ 中發現 legacy ?p= 格式 URL（應統一為 /{id}.md 格式）:")
  while IFS= read -r line; do
    ERRORS+=("  $line")
  done <<< "$legacy_urls"
else
  echo "✅  guides/ 中無 legacy ?p= 格式 URL。"
fi

# ─── 維度 2: SNAPSHOT 欄位名大小寫一致性（guides vs SDK PHP） ────────────────────
echo "── 維度 2: SNAPSHOT 欄位名驗證 ──"

# B2B ItemTax vs ItemTaxType 永久守護
# 排除 B2C vs B2B 對照表格（同一行同時出現 ItemTaxType 與 ItemTax 為對照說明，不是錯誤）
# 只抓 B2B SNAPSHOT 參數表中單獨使用 ItemTaxType 的情況
# 實作：先抓含 Items[].ItemTaxType 的表格行，再排除同時含 Items[].ItemTax（非 Type）的對照行
b2b_snapshot_err=$(grep -n '|.*Items\[\]\.ItemTaxType' guides/05-invoice-b2b.md 2>/dev/null | \
  grep -v 'Items\[\]\.ItemTax[^T]' || true)
if [ -n "$b2b_snapshot_err" ]; then
  ERRORS+=("維度2: guides/05-invoice-b2b.md 的 SNAPSHOT 參數表使用了 ItemTaxType（B2C 欄位），B2B 應為 ItemTax:")
  while IFS= read -r line; do
    ERRORS+=("  $line")
  done <<< "$b2b_snapshot_err"
else
  echo "✅  B2B 發票欄位 ItemTax / ItemTaxType 區分正確。"
fi

# SDK 範例中的 B2B ItemTaxType 錯誤偵測
sdk_b2b_itemtaxtype=$(grep -rn 'ItemTaxType' scripts/SDK_PHP/example/Invoice/B2B/ 2>/dev/null || true)
if [ -n "$sdk_b2b_itemtaxtype" ]; then
  WARNINGS+=("維度2: SDK 範例 Invoice/B2B/ 中使用了 ItemTaxType（官方 API 為 ItemTax）— 此為 SDK 已知問題:")
  while IFS= read -r line; do
    WARNINGS+=("  $line")
  done <<< "$sdk_b2b_itemtaxtype"
fi

# ─── 維度 3: SDK 類別/Factory alias 引用驗證 ──────────────────────────────────────
echo "── 維度 3: SDK 類別名驗證 ──"

# 從 Factory.php 提取所有合法的 service alias
factory_file="scripts/SDK_PHP/src/Factories/Factory.php"
if [ -f "$factory_file" ]; then
  # 提取 Factory alias（單引號包裹的字串）— 使用 POSIX ERE 代替 PCRE
  factory_aliases=$(grep -oE "'[A-Z][A-Za-z]+Service'" "$factory_file" | tr -d "'" | sort -u)

  # 從 guides/ 提取引用的 Factory alias — 僅匹配 Factory::create('XxxService') 或獨立出現的 XxxService
  # 排除子字串匹配（如 ProductServiceID 中的 ProductService）
  guide_service_refs=$(grep -ohE "'[A-Z][A-Za-z]+Service'" guides/*.md | tr -d "'" | sort -u)

  # 檢查 guides 引用的是否都存在於 Factory
  missing_in_factory=()
  for ref in $guide_service_refs; do
    # 也在 src/ 目錄中搜尋（某些是直接使用的 class）
    if ! echo "$factory_aliases" | grep -qx "$ref" && \
       ! find scripts/SDK_PHP/src/ -name "${ref}.php" 2>/dev/null | grep -q .; then
      missing_in_factory+=("$ref")
    fi
  done

  if [ ${#missing_in_factory[@]} -gt 0 ]; then
    ERRORS+=("維度3: guides/ 引用了 SDK 中不存在的 Service 類別:")
    for m in "${missing_in_factory[@]}"; do
      ERRORS+=("  • $m")
    done
  else
    echo "✅  guides/ 引用的所有 SDK Service 類別均存在於 Factory 或 src/。"
  fi
else
  WARNINGS+=("維度3: 找不到 $factory_file，跳過 SDK 類別驗證。")
fi

# ─── 維度 4: 協定模式關鍵字一致性 ──────────────────────────────────────────────────
echo "── 維度 4: 協定模式驗證 ──"

# 驗證核心 guides 有標記協定模式
declare -A GUIDE_MODE_MAP=(
  ["guides/01-payment-aio.md"]="CMV-SHA256"
  ["guides/02-payment-ecpg.md"]="AES-JSON"
  ["guides/03-payment-backend.md"]="AES-JSON"
  ["guides/04-invoice-b2c.md"]="AES-JSON"
  ["guides/05-invoice-b2b.md"]="AES-JSON"
  ["guides/06-logistics-domestic.md"]="CMV-MD5"
  ["guides/07-logistics-allinone.md"]="AES-JSON"
  ["guides/08-logistics-crossborder.md"]="AES-JSON"
  ["guides/09-ecticket.md"]="AES-JSON"
  ["guides/25-receipt.md"]="AES-JSON"  # CBC 預設，另支援 AES-GCM 選用模式（V3.0+）
)

mode_errors=0
for guide in "${!GUIDE_MODE_MAP[@]}"; do
  expected_mode="${GUIDE_MODE_MAP[$guide]}"
  if [ -f "$guide" ]; then
    if ! grep -q "$expected_mode" "$guide"; then
      ERRORS+=("維度4: $guide 未包含預期的協定模式關鍵字 '$expected_mode'")
      mode_errors=$((mode_errors + 1))
    fi
  else
    WARNINGS+=("維度4: 找不到 $guide")
  fi
done
if [ $mode_errors -eq 0 ]; then
  echo "✅  所有核心 guides 的協定模式標記正確。"
fi

# ─── 維度 5: 測試帳號一致性 ────────────────────────────────────────────────────────
echo "── 維度 5: 測試帳號一致性 ──"

# 定義服務帳號（MerchantID:HashKey:HashIV:服務名）
declare -a TEST_ACCOUNTS=(
  "3002607:pwFHCqoQZGmho4w6:EkRm7iFT261dpevs:金流"
  "2000132:ejCk326UnaZWKisg:q9jcZX8Ib9LM8wYk:電子發票"
  "2000132:5294y06JbISpM5x9:v77hoKGq4kWxNNIS:物流"
  "2000933:XBERn1YOvpM9nfZc:h1ONHk4P4yqbl5LK:物流C2C"
  "3085340:HwiqPsywG1hLQNuN:YqITWD4TyKacYXpn:離線發票"
  "3085676:7b53896b742849d3:37a0ad3c6ffa428b:ECTicket特店"
  "3085672:b15bd8514fed472c:9c8458263def47cd:ECTicket平台商"
)

# 確認所有帳號都在 SKILL.md 中
acct_errors=0
for acct in "${TEST_ACCOUNTS[@]}"; do
  IFS=':' read -r mid hk hiv svc <<< "$acct"
  if ! grep -q "$mid" SKILL.md; then
    ERRORS+=("維度5: SKILL.md 中未找到 $svc 測試帳號 MerchantID=$mid")
    acct_errors=$((acct_errors + 1))
  fi
  if ! grep -q "$hk" SKILL.md; then
    ERRORS+=("維度5: SKILL.md 中未找到 $svc 測試帳號 HashKey=$hk")
    acct_errors=$((acct_errors + 1))
  fi
  if ! grep -q "$hiv" SKILL.md; then
    ERRORS+=("維度5: SKILL.md 中未找到 $svc 測試帳號 HashIV=$hiv")
    acct_errors=$((acct_errors + 1))
  fi
done

# 確認 AGENTS.md 和 GEMINI.md 也包含所有主帳號
for platform_file in AGENTS.md GEMINI.md; do
  if [ -f "$platform_file" ]; then
    for acct in "${TEST_ACCOUNTS[@]}"; do
      IFS=':' read -r mid hk hiv svc <<< "$acct"
      if ! grep -q "$mid" "$platform_file"; then
        ERRORS+=("維度5: $platform_file 中未找到 $svc 測試帳號 MerchantID=$mid")
        acct_errors=$((acct_errors + 1))
      fi
    done
  fi
done

if [ $acct_errors -eq 0 ]; then
  echo "✅  所有測試帳號在 SKILL.md / AGENTS.md / GEMINI.md 中一致。"
fi

# ─── 附加: references/README.md 檔案數驗證 ──────────────────────────────────────
echo "── 附加: references 檔案數驗證 ──"
ref_file_count=$(find references/ -name '*.md' ! -name 'README.md' -type f 2>/dev/null | wc -l | tr -d ' ')
# 使用 POSIX ERE 提取數字
readme_claim=$(grep -oE '[0-9]+ 個檔案' references/README.md 2>/dev/null | grep -oE '[0-9]+' || echo "0")
if [ "$ref_file_count" = "$readme_claim" ]; then
  echo "✅  references/ 檔案數 ($ref_file_count) 與 README.md 宣稱 ($readme_claim) 一致。"
else
  ERRORS+=("附加: references/ 實際檔案數 ($ref_file_count) ≠ README.md 宣稱 ($readme_claim)")
fi

# ─── 報告 ─────────────────────────────────────────────────────────────────────────
echo ""
echo "════════════════════════════════════════"

if [ ${#WARNINGS[@]} -gt 0 ]; then
  echo "⚠️  警告 (${#WARNINGS[@]}):"
  for w in "${WARNINGS[@]}"; do
    echo "  $w"
  done
  echo ""
fi

if [ ${#ERRORS[@]} -gt 0 ]; then
  echo "❌  錯誤 (${#ERRORS[@]}):"
  for e in "${ERRORS[@]}"; do
    echo "  $e"
  done
  echo ""
  echo "════════════════════════════════════════"
  exit 1
else
  echo "✅  全部 5 維度驗證通過！guides ↔ references ↔ scripts 一致性確認。"
  echo "════════════════════════════════════════"
  exit 0
fi
