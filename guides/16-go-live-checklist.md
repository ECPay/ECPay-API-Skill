> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 測試→上線切換完整檢查清單

> 若需確認最新 API 端點或參數異動，可從 `references/` 對應服務檔案 web_fetch 取得最新官方規格。

## 概述

從測試環境切換到正式環境前，逐項檢查以確保安全、正確、合規。

## 帳號與環境

- [ ] 已向綠界申請正式帳號並通過審核
- [ ] 已取得正式環境的 MerchantID、HashKey、HashIV
- [ ] 已將所有 URL 從 `-stage` 切換到正式域名

### URL 對照

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/` 各服務對應檔案

| 服務 | 測試 | 正式 |
|------|------|------|
| 金流 AIO | payment**-stage**.ecpay.com.tw | payment.ecpay.com.tw |
| 站內付 2.0（Token 取得 / 建立交易）| ecpg**-stage**.ecpay.com.tw | ecpg.ecpay.com.tw |
| ECPG（查詢 / 授權 / 請退款）| ecpayment**-stage**.ecpay.com.tw | ecpayment.ecpay.com.tw |
| 物流 | logistics**-stage**.ecpay.com.tw | logistics.ecpay.com.tw |
| 電子發票 | einvoice**-stage**.ecpay.com.tw | einvoice.ecpay.com.tw |
| 電子票證 | ecticket**-stage**.ecpay.com.tw | ecticket.ecpay.com.tw |
| 特店後台 | vendor**-stage**.ecpay.com.tw | vendor.ecpay.com.tw |

> **⚠️ 站內付 2.0 常見錯誤：雙 Domain 混淆**
>
> 站內付 2.0（及綁卡等 Token API）使用**兩個不同的 domain**：
> - **Token 取得 / 建立交易**：`ecpg-stage.ecpay.com.tw`（GetTokenbyTrade、CreatePayment 等）
> - **查詢 / 授權 / 請退款**：`ecpayment-stage.ecpay.com.tw`（QueryTrade、DoAction 等）
>
> ⚠️ **幕後授權 / 幕後取號只走 `ecpayment` domain，沒有雙 Domain 問題。**
> 常見錯誤是將 站內付2.0 的 Token API 也打向 `ecpayment` domain，導致 404；或把查詢 API 打向 `ecpg` domain，同樣 404。
> 切換正式環境時同樣需要注意：`ecpg.ecpay.com.tw` vs `ecpayment.ecpay.com.tw`。

### 站內付 2.0 上線前專屬清單

正式切換前，額外確認以下站內付 2.0 特有的項目：

- [ ] **雙 Domain 都已切換**：`ecpg-stage` → `ecpg`，`ecpayment-stage` → `ecpayment`（只切換一個是最常見的上線前漏洞）
- [ ] **ThreeDURL 跳轉邏輯已測試**：在測試環境用信用卡（4311-9522-2222-2222）走完完整 3D 流程，確認前端正確執行 `window.location.href = threeDUrl`
- [ ] **ReturnURL 與 OrderResultURL 兩個端點在正式環境均可接收 POST**：確認路由設定、防火牆規則、反向代理配置與測試環境一致
- [ ] **MerchantTradeNo 冪等保護已實作**：ReturnURL 可能多次重送，確認資料庫寫入使用 upsert 而非 insert
- [ ] **兩層狀態碼都有檢查**：TransCode（傳輸層）和 RtnCode（業務層），僅 RtnCode 為 1 才標記訂單完成
- [ ] **OrderResultURL 讀取 `ResultData` 表單欄位**（非 JSON body）：確認在正式環境已用 `request.form['ResultData']`（Python）/ `$_POST['ResultData']`（PHP）讀取

## 安全性

- [ ] 已更換程式碼中的 MerchantID、HashKey、HashIV 為正式帳號
- [ ] HashKey / HashIV **未出現**在前端程式碼中
- [ ] HashKey / HashIV **未出現**在版本控制（git）中
- [ ] 使用環境變數或加密設定檔管理機敏資料
- [ ] TLS 1.2 已啟用
- [ ] API 金鑰輪換機制已建立（如需要）

#### PCI DSS 範圍影響

不同整合方式影響你的 PCI DSS 合規範圍：

| 整合方式 | PCI 等級 | 說明 |
|---------|---------|------|
| **AIO（跳轉）** | SAQ-A | 最低範圍 — 消費者在綠界頁面輸入卡號，你的伺服器不接觸卡號資料 |
| **站內付 2.0**（ECPG 服務之一）| SAQ-A-EP | 中等範圍 — 你的前端頁面嵌入付款元件，但卡號直接送至綠界，不經過你的後端 |
| **幕後授權** | SAQ-D 或更高 | 最高範圍 — 你的後端直接處理卡號資料，需完整 PCI DSS 合規 |

> **建議**：除非有明確需求，優先選擇 AIO 或 ECPG 以降低 PCI 合規負擔。

#### HashKey / HashIV 輪換指引

ECPay 目前**不支援同時啟用多組 HashKey/HashIV**，輪換時需要短暫停機切換。

**輪換步驟**：

1. **申請新金鑰** — 透過綠界特店後台或聯繫客服申請新的 HashKey/HashIV
2. **測試環境驗證** — 在測試環境使用新金鑰完成至少一筆完整交易流程
3. **安排維護窗口** — 選擇交易量最低的時段（通常凌晨 2:00-5:00）
4. **切換金鑰** — 更新環境變數或密鑰管理系統中的 HashKey/HashIV
5. **驗證交易** — 立即執行一筆小額測試交易確認新金鑰正常
6. **確認舊金鑰失效** — 用舊金鑰發送測試請求，確認回傳驗證錯誤

**環境變數管理**：使用密鑰管理服務（如 AWS Secrets Manager / GCP Secret Manager / Azure Key Vault）管理 HashKey/HashIV，**永遠不要**寫入程式碼或設定檔。保留前一組金鑰至少 24 小時以防需要復原。

### 加密與安全

- [ ] 確認各服務加密方式（AIO=SHA256, 物流=MD5, ECPG/發票=AES）
- [ ] 回呼 URL 使用 FQDN 而非固定 IP
- [ ] 確認 API 呼叫頻率不超過限制（過頻觸發 403，需等 30 分鐘）
- [ ] 回呼端點已限制來源 IP（向綠界客服索取 IP 白名單）

> **取得綠界回呼 IP 範圍**：透過綠界客服 (02-2655-1775) 或特店後台工單索取。
> 取得後在你的防火牆或反向代理中設定白名單，僅允許這些 IP 存取 ReturnURL/ServerReplyURL 端點。

## 回呼 URL

- [ ] ReturnURL 可被外網存取
- [ ] ReturnURL 回應純字串 `1|OK`（無 HTML、無 BOM）
- [ ] ReturnURL 回應的 HTTP Status Code 為 200（非 201/202/204）
- [ ] ReturnURL 在 10 秒內回應（不可有阻塞 I/O 或外部 API 呼叫，逾時觸發重送）
- [ ] ReturnURL 使用 HTTPS
- [ ] ReturnURL 僅使用 80 或 443 埠
- [ ] ReturnURL 未放在 CDN 後面
- [ ] PeriodReturnURL 已設定（如使用定期定額）
- [ ] PaymentInfoURL 已設定（如使用 ATM/CVS/BARCODE）
- [ ] ServerReplyURL 已設定（如使用物流）
- [ ] ATM 付款 PaymentInfoURL 需處理 RtnCode=2（取號成功，非最終付款）
- [ ] CVS/BARCODE 付款 PaymentInfoURL 需處理 RtnCode=10100073（取號成功）

## 應用層安全

- [ ] Callback 端點已驗證來源 IP（向綠界客服索取 IP 白名單）
- [ ] MerchantTradeNo 冪等性檢查（拒絕重複訂單編號的重複處理）
- [ ] Callback 參數白名單驗證（僅接受已知欄位名稱）
- [ ] 錯誤訊息未洩露內部資訊（如資料庫 ID、堆疊追蹤）
- [ ] 所有使用者輸入已做參數化查詢（防 SQL 注入）
- [ ] 前端顯示的交易資訊已做 HTML 跳脫（防 XSS）

## 驗證邏輯

- [ ] CheckMacValue 驗證已在所有回呼中實作（必須使用 timing-safe 比較函式，**不可用 `==` 或 `===`**）

  | 語言 | Timing-Safe 函式 |
  |------|----------------|
  | PHP | `hash_equals()` |
  | Python | `hmac.compare_digest()` |
  | Node.js | `crypto.timingSafeEqual()` |
  | TypeScript | `crypto.timingSafeEqual()` |
  | Go | `subtle.ConstantTimeCompare()` |
  | Java | `MessageDigest.isEqual()` |
  | Kotlin | `MessageDigest.isEqual()` |
  | C# | `CryptographicOperations.FixedTimeEquals()` |
  | C | `CRYPTO_memcmp()` |
  | C++ | `CRYPTO_memcmp()` |
  | Rust | `subtle::ConstantTimeEq` |
  | Swift | `HMAC<SHA256>.isValidAuthenticationCode()` |
  | Ruby | `OpenSSL.secure_compare()` |

  完整說明：[guides/13 §timing-safe](./13-checkmacvalue.md)
- [ ] RtnCode 檢查已實作
- [ ] SimulatePaid 檢查已實作（測試交易不出貨）
- [ ] 防重複處理已實作（同一筆通知可能重送多次）
- [ ] AES 解密已正確實作（如使用 ECPG/發票/全方位物流）
- [ ] AES 解密已用 `test-vectors/` 目錄中的測試向量驗證，確認輸出 JSON 與預期一致

## 功能測試

- [ ] 已用正式帳號完成至少一筆小額信用卡交易
- [ ] 已驗證主要付款方式都能正常運作
- [ ] 發票功能已測試（如有使用）
- [ ] 物流功能已測試（如有使用）
- [ ] 退款 / 折讓 / 退貨流程已測試
- [ ] 定期定額已測試（如有使用）
- [ ] 綁卡功能已測試（如有使用）
- [ ] BNPL 先買後付最低金額 ≥ 3,000 元
- [ ] 定期定額：連續 6 次扣款失敗會自動取消合約
- [ ] iOS WebView 測試：LINE/Facebook 內建瀏覽器相容性

## 錯誤處理

- [ ] 錯誤處理和日誌記錄已到位
- [ ] 付款失敗的使用者體驗已處理（顯示錯誤訊息、提供重試）
- [ ] 回呼處理的例外已捕獲（不可因程式錯誤導致未回應 1|OK）
- [ ] API 超時處理已實作

## 3D Secure

- [ ] 已確認 3D Secure 2.0 相容（2025/8/1 起強制）
- [ ] 已了解 3D 驗證可能導致的付款流程變化

## 監控

- [ ] 交易成功率監控已建立
- [ ] 回呼失敗警示已建立
- [ ] 異常交易金額警示已建立

### 上線後第一天觀察重點

- 建立訂單 → callback 接收的比例是否接近 1:1
- callback 處理時間是否在 10 秒內（超時會觸發重送）
- 有無 CheckMacValue 驗證失敗（可能代表 HashKey 設定錯誤）
- ATM/CVS 訂單的 RtnCode=2/10100073 是否被正確處理（非錯誤）

## 緊急復原計畫

建議使用環境變數（如 `ECPAY_FEATURE_FLAG`）作為 Feature Flag 控制收款功能啟用狀態，出問題時免重新部署即可切換。

### 環境快速切換步驟

1. 在環境變數管理系統中準備測試環境設定（保留 `-stage` URL）
2. 出現問題時，將 `ECPAY_ENV` 從 `production` 切回 `staging`
3. 若已實作 Feature Flag：更新環境變數後免重啟即可切換；若無 Feature Flag：需重啟服務（PHP-FPM、Java WAR、gunicorn 等皆需重啟）
4. 通知客服團隊暫停收款相關客訴處理

### 故障場景降級策略

| 故障場景 | 降級策略 | 恢復條件 |
|---------|---------|---------|
| ECPay API 全面不可用 | 啟用 Feature Flag 暫停收款，顯示維護頁面 | ECPay 狀態頁恢復正常 |
| 回呼 URL 收不到通知 | 啟動輪詢查詢訂單狀態（QueryTradeInfo） | 回呼恢復正常接收 |
| CheckMacValue 驗證失敗 | 檢查是否金鑰被輪換，暫停並聯繫綠界客服 | 確認金鑰正確 |
| 發票 API 故障 | 金流不受影響，發票改為人工補開 | 發票 API 恢復 |

### 🚨 金鑰洩漏緊急處置 SOP

若發現 HashKey/HashIV 或 MerchantID 洩漏（例如提交至公開 Git、日誌洩漏）：

1. **立即通知綠界客服**（techsupport@ecpay.com.tw / (02) 2655-1775）要求重發金鑰
2. **停用洩漏金鑰**：暫停相關服務收款（Feature Flag 或維護模式）
3. **檢查異常交易**：透過特店後台（vendor.ecpay.com.tw）查閱洩漏期間的交易紀錄
4. **更新金鑰**：取得新金鑰後更新環境變數並重啟服務
5. **回溯清理**：從 Git 歷史清除敏感值（`git filter-branch` 或 BFG Repo-Cleaner）
6. **覆盤記錄**：記錄洩漏原因、影響範圍、處理時間，更新團隊安全規範

## 環境切換最佳實踐

使用環境變數管理測試/正式環境切換（各語言完整範例見 [guides/24 多語言整合](./24-multi-language-integration.md)）：

| 環境變數 | 測試值 | 正式值 | 說明 |
|---------|--------|--------|------|
| `ECPAY_MERCHANT_ID` | `3002607`（AIO）/ `2000132`（發票） | 正式特店編號 | 特店編號 |
| `ECPAY_HASH_KEY` | 測試 HashKey | 正式 HashKey | 加密金鑰 |
| `ECPAY_HASH_IV` | 測試 HashIV | 正式 HashIV | 加密向量 |
| `ECPAY_ENV` | `staging` | `production` | 控制 base URL 切換 |

> **原則**：`ECPAY_ENV=production` 時使用 `payment.ecpay.com.tw`，否則使用 `payment-stage.ecpay.com.tw`。所有 domain 的對應關係見 [SKILL.md 環境 URL 表](../SKILL.md)。

## 上線後觀察

### 第一天觀察清單

| 指標 | 目標 | 異常處理 |
|------|------|---------|
| 第一筆真實交易 | 成功完成 | 立即檢查參數和帳號設定 |
| 交易成功率 | > 95% | < 90% 停機排查 |
| ReturnURL 回呼延遲 | < 5 秒 | 檢查伺服器效能 |
| 對帳檔核對 | 金額一致 | 逐筆比對找出差異 |
| 異常金額 | 設定警示門檻值 | 單筆 > 50,000 通知 |

### 上線後持續事項

- [ ] 確認對帳報表可正常下載
- [ ] 保留測試環境帳號供日後除錯使用

## 漸進式上線策略

1. **先小額交易測試** — 用真實帳號做 10 元測試交易
2. **先只開信用卡** — 確認穩定後再逐步開啟 ATM、CVS 等
3. **先不串發票** — 確認金流穩定後再加電子發票

## 安全防護

### 付款頁面嵌入防護

- [ ] 付款頁面**絕不使用 iframe** 嵌入 ECPay 付款頁面（瀏覽器安全限制會封鎖，導致白頁或無法付款）
  > 替代方案：使用頁面跳轉（AIO），或官方嵌入式體驗（站內付 2.0 — [guides/02](./02-payment-ecpg.md)）

### CSRF 防護

- [ ] OrderResultURL 屬 ECPay 前端 Form POST 回傳，**不可強制要求 CSRF Token**；請改驗證 `ResultData` 內容與交易狀態
- [ ] ReturnURL（server-to-server）不需 CSRF，但需驗 CheckMacValue

### XSS 防護

- [ ] 從 ECPay 回傳的參數值（TradeDesc, ItemName 等）顯示在頁面時需 HTML escape
- [ ] 不要直接將 callback 參數 innerHTML 到頁面中

## 自動化冒煙測試

上線前建議用 [guides/13](./13-checkmacvalue.md) 的測試向量驗證 CheckMacValue 加密正確性，並用 `curl` 或任意 HTTP Client 確認各端點可達（參考上方 URL 對照表）。完整多語言實作範例見 [guides/24](./24-multi-language-integration.md)。

## 相關文件

- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 金流 AIO：[guides/01-payment-aio.md](./01-payment-aio.md)
- 站內付 2.0（ECPG 服務之一）：[guides/02-payment-ecpg.md](./02-payment-ecpg.md)
- POS 刷卡機：[guides/17-pos-integration.md](./17-pos-integration.md)
- 直播收款：[guides/18-livestream-payment.md](./18-livestream-payment.md)
- 離線發票：[guides/19-invoice-offline.md](./19-invoice-offline.md)
- 錯誤碼排查：見 [guides/21-error-codes-reference.md](./21-error-codes-reference.md)
- Callback 處理：見 [guides/22-webhook-events-reference.md](./22-webhook-events-reference.md)
- 效能與擴展：見 [guides/23-performance-scaling.md](./23-performance-scaling.md)
