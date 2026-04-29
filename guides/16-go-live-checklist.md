> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 測試→上線切換完整檢查清單

> 若需確認最新 API 端點或參數異動，可從 `references/` 對應服務檔案 web_fetch 取得最新官方規格。

## 概述

從測試環境切換到正式環境前，逐項檢查以確保安全、正確、合規。

## 🔴 紅燈檢查（5 項必過）

> **上線前必須全部通過**——任何一項未完成都可能導致交易失敗或資安風險。

- [ ] **1. 測試→正式 URL 已全數切換**：所有 API 端點已從 `-stage` 切換到正式域名。站內付 2.0 注意**雙 Domain 都要切**（`ecpg-stage` → `ecpg`，`ecpayment-stage` → `ecpayment`），只切一個是最常見的上線漏洞 — **若未切換：API 打到測試環境，所有交易為模擬交易，不產生真實資金流動**
- [ ] **2. HashKey/HashIV 已替換為正式環境值**：MerchantID、HashKey、HashIV 全部改為正式帳號（從[綠界商店後台](https://vendor.ecpay.com.tw)取得）— **若未替換：測試帳號的 HashKey 與正式帳號不同，所有 CheckMacValue / AES 驗證永遠失敗，交易全部拒絕**
- [ ] **3. 密鑰未硬編碼於前端或版本控制**：HashKey/HashIV 使用環境變數或 Secret Manager 管理，未出現在 JavaScript/HTML 或 git 歷史中 — **若硬編碼：任何人可讀取原始碼即能竄改交易簽章或冒充你的系統向 ECPay 發送請求**
- [ ] **4. Callback URL 已更新為正式環境且可接收**：ReturnURL、OrderResultURL 指向正式伺服器的 HTTPS URL（port 443），已確認可從外部接收 POST 請求 — **若未更新或無法訪問：消費者付款成功後，系統收不到通知，訂單永遠停留在「待付款」狀態，需人工補查**
- [ ] **5. RtnCode 型別判斷正確**：CMV 協定（AIO / 國內物流）的 RtnCode 為**字串** `=== '1'`；AES-JSON 協定（ECPG / 發票 / 物流 v2）的 RtnCode 為**整數** `=== 1`。型別錯誤會導致所有交易判斷失敗 — **若型別錯誤：每一筆付款成功的訂單，系統都會誤判為「付款失敗」，永遠不更新訂單狀態**

> ✅ 以上 5 項全部通過後，繼續完成下方黃燈檢查。

---

## 🟡 黃燈檢查（強烈建議）

> 以下項目強烈建議在上線前完成，可降低營運風險和客訴。

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
| ECTicket | ecticket**-stage**.ecpay.com.tw | ecticket.ecpay.com.tw |
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

> ⚠️ **SNAPSHOT 2026-03** | PCI DSS 規範可能隨版本更新，正式上線前請參照 [PCI Security Standards Council](https://www.pcisecuritystandards.org/) 最新版本。

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
- [ ] 確認 CheckMacValue 使用 `ecpayUrlEncode`（urlencode→小寫→.NET 替換），AES 使用 `aesUrlEncode`（僅 urlencode），兩者絕不混用
- [ ] 回呼 URL 使用 FQDN 而非固定 IP
- [ ] 確認 API 呼叫頻率不超過限制（過頻觸發 403，需等 30 分鐘）
- [ ] 回呼端點已限制來源 IP（向綠界客服索取 IP 白名單）
- [ ] 確認 ItemName / TradeDesc 不含系統指令關鍵字（echo、curl、python 等），否則 WAF 攔截回傳 10400011
- [ ] 確認 AES 加密後 Base64 使用標準 alphabet（+/=），非 URL-safe（-_）

> **取得綠界回呼 IP 範圍**：透過綠界客服 (02-2655-1775) 或特店後台工單索取。
> 取得後在你的防火牆或反向代理中設定白名單，僅允許這些 IP 存取 ReturnURL/ServerReplyURL 端點。

## 回呼 URL

- [ ] ReturnURL、OrderResultURL、ClientBackURL 設為**不同的 URL**（三者用途不同，不可共用同一端點）
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
- [ ] `MerchantTradeNo` 限制為英數字（≤20 字元），不可含特殊符號或中文
- [ ] `TotalAmount` 必須為正整數（不可為 0、負數或小數）
- [ ] `ItemName` / `TradeDesc` 已過濾 HTML 標籤與控制字元（`\x00-\x1F`），避免 WAF 攔截或 CheckMacValue 不符

## 驗證邏輯

- [ ] CheckMacValue 驗證必須使用 timing-safe 比較函式（**不可用 `==` 或 `===`**）。完整 12 語言 timing-safe 函式對照表：[guides/13 §timing-safe](./13-checkmacvalue.md)
- [ ] RtnCode 檢查已實作
- [ ] 確認 RtnCode 比對型別正確：AIO/物流 Callback 為字串 `"1"`，AES-JSON 服務（ECPG/發票）解密後為整數 `1`
- [ ] 確認正確處理回應格式：AIO/國內物流為 Form POST，AES-JSON 服務才回傳 JSON
- [ ] SimulatePaid 檢查已實作（測試交易不出貨）
- [ ] 防重複處理已實作（同一筆通知可能重送多次）
- [ ] AES 解密已正確實作（如使用 ECPG/發票/全方位物流）
- [ ] AES 解密已用 `test-vectors/` 目錄中的測試向量驗證，確認輸出 JSON 與預期一致
- [ ] 確認 ItemName 不超過 400 字元（含中文多位元組字元），截斷會導致 CheckMacValue 不符
- [ ] **MerchantTradeDate 時區**：確認伺服器產生的 `MerchantTradeDate` 為 UTC+8（`Asia/Taipei`），非 UTC。海外部署的伺服器務必顯式轉換時區

## 功能測試

- [ ] 已用正式帳號完成至少一筆小額信用卡交易
- [ ] 已驗證主要付款方式都能正常運作
- [ ] 發票功能已測試（如有使用）
- [ ] 發票 AllowanceByCollegiate Callback 使用 **MD5 CMV（Form POST）**，與其他發票 Callback（無 CMV）處理邏輯不同，需獨立驗證（如有使用折讓）
- [ ] 物流功能已測試（如有使用）
- [ ] 退款 / 折讓 / 退貨流程已測試
- [ ] 確認退款/請款/取消（DoAction）僅用於信用卡交易，ATM/CVS/條碼付款無退款 API
- [ ] 定期定額已測試（如有使用）
- [ ] 綁卡功能已測試（如有使用）
- [ ] BNPL 先買後付最低金額 ≥ 3,000 元
- [ ] 定期定額：連續 6 次扣款失敗會自動取消合約
- [ ] iOS WebView 測試：LINE/Facebook 內建瀏覽器相容性

### 爭議款項處理

- [ ] 了解信用卡 Chargeback（持卡人爭議）流程：ECPay 會透過 Email 通知特店提供交易證明
- [ ] 設定爭議處理窗口（通常 45-120 天）的內部流程
- [ ] 保留交易相關憑證（出貨證明、簽收紀錄）至少 180 天
- [ ] 聯繫綠界客服 (02-2655-1775) 了解爭議款項扣款機制

> ℹ️ ECPay API 不提供 Chargeback 查詢或回應 API，爭議處理透過綠界後台及 Email 進行。

## ECTicket

- [ ] 確認三層驗證已實作：TransCode（傳輸層）→ CheckMacValue（簽名驗證）→ RtnCode（業務層），缺任一層可能導致安全漏洞或誤判結果（詳見 [guides/09](./09-ecticket.md)）
- [ ] 確認使用正確的測試帳號：特店模式 MerchantID `3085676`，平台商模式 MerchantID `3085672`
- [ ] 確認 Callback（UseStatusNotifyURL）回應格式為 AES 加密 JSON + CheckMacValue（非 `1|OK`）

## 錯誤處理

- [ ] 錯誤處理和日誌記錄已到位
- [ ] 付款失敗的使用者體驗已處理（顯示錯誤訊息、提供重試）
- [ ] 回呼處理的例外已捕獲（不可因程式錯誤導致未回應 1|OK）
- [ ] API 超時處理已實作

## 3D Secure

- [ ] 已確認 3D Secure 2.0 相容（2025/8/1 起強制）
- [ ] 已了解 3D 驗證可能導致的付款流程變化

## 基礎設施

- [ ] SSL 憑證有效期 > 90 天，設定自動續約提醒
- [ ] Load Balancer 健康檢查已設定（確保 Callback 接收端始終可用）
- [ ] Callback endpoint 可從外部 IP 訪問（非僅限內網）

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

使用環境變數管理測試/正式環境切換（各語言完整範例見 [guides/23 多語言整合](./23-multi-language-integration.md)）：

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

上線前建議用 [guides/13](./13-checkmacvalue.md) 的測試向量驗證 CheckMacValue 加密正確性，並用 `curl` 或任意 HTTP Client 確認各端點可達（參考上方 URL 對照表）。完整多語言實作範例見 [guides/23](./23-multi-language-integration.md)。

## 相關文件

- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 金流 AIO：[guides/01-payment-aio.md](./01-payment-aio.md)
- 站內付 2.0（ECPG 服務之一）：[guides/02-payment-ecpg.md](./02-payment-ecpg.md)
- POS 刷卡機：[guides/17-hardware-services.md §POS 刷卡機串接指引](./17-hardware-services.md#pos-刷卡機串接指引)
- 直播收款：[guides/17-hardware-services.md §直播收款指引](./17-hardware-services.md#直播收款指引)
- 離線發票：[guides/18-invoice-offline.md](./18-invoice-offline.md)
- 錯誤碼排查：見 [guides/20-error-codes-reference.md](./20-error-codes-reference.md)
- Callback 處理：見 [guides/21-webhook-events-reference.md](./21-webhook-events-reference.md)
- 效能與擴展：見 [guides/22-performance-scaling.md](./22-performance-scaling.md)

---

## ✅ 整合驗收清單（上線前端到端驗收）

> **用法**：用實際測試帳號完成以下每個場景，確認系統行為符合預期後才上線。

### 金流 AIO（CMV-SHA256）驗收

- [ ] **下單成功**：呼叫 `AioCheckOut` 後，消費者瀏覽器跳轉到綠界付款頁，頁面顯示正確金額與商品名稱
- [ ] **信用卡付款成功**：使用測試卡號 `4311-9522-2222-2222` 完成付款，後端 ReturnURL 收到 `RtnCode='1'`（字串），`MerchantTradeNo` 與送出的一致
- [ ] **ATM 取號成功**：後端 ReturnURL 收到 `RtnCode='2'`（字串），`PaymentInfoURL` 收到虛擬帳號；模擬繳款後 ReturnURL 收到 `RtnCode='1'`
- [ ] **Callback 驗證通過**：CheckMacValue 與系統重新計算值一致（使用 timing-safe 比較）
- [ ] **Callback 回應正確**：已回應純文字 `1|OK`（HTTP 200，不含引號/換行/HTML）
- [ ] **冪等性**：Callback 重送時，訂單狀態僅更新一次（不重複入帳）

### 金流 ECPG 站內付 2.0（AES-JSON）驗收

- [ ] **GetTokenbyTrade 成功**：`TransCode === 1` **且** `RtnCode === 1`（整數），`Token` 為非空字串
- [ ] **JS SDK 渲染**：頁面出現信用卡號/有效期/安全碼三欄輸入框
- [ ] **CreatePayment 後處理 ThreeDURL**：回應含 `ThreeDInfo.ThreeDURL` 非空字串，前端執行 `window.location.href` 跳轉（2025/8 後幾乎必定觸發）
- [ ] **ReturnURL（S2S）正確接收**：讀 `php://input`（JSON POST），`TransCode === 1` 且 `RtnCode === 1`（整數），已回應 `1|OK`
- [ ] **OrderResultURL（前端）正確解析**：讀 `$_POST['ResultData']` → `json_decode` → AES 解密 Data，頁面顯示付款結果

### 電子發票（AES-JSON）驗收

- [ ] **開立成功**：`TransCode === 1` 且 `RtnCode === 1`（整數），`InvoiceNo` 格式正確（如 `AB-12345678`）
- [ ] **Callback 接收**：B2C 發票 callback 為 Form POST，`RtnCode` 為整數 `1`
- [ ] **折讓唯一性**：若有折讓，`AllowanceNo` 與原 `InvoiceNo` 對應正確

### 物流（按使用的服務）驗收

- [ ] **國內物流**：使用 CheckMacValue MD5，建單後 LogisticsID 有值，ServerReplyURL 收到狀態通知
- [ ] **全方位物流 v2**：`RqHeader.Revision: "1.0.0"` 已帶入，消費者選店後 `CreateByTempTrade` 成功（`RtnCode === 1` 整數），ServerReplyURL 回應 AES 加密 JSON

### 通用驗收

- [ ] **測試→正式環境切換**：HashKey/HashIV 改為正式值，所有 URL 已去除 `-stage` 後綴
- [ ] **回應超時**：ReturnURL 在 3 秒內回應 `1|OK`（壓力測試模擬 10 並發 callback）
- [ ] **RtnCode 型別**：CMV 類 `=== '1'`（字串）；AES-JSON 類 `=== 1`（整數）均已正確判斷
- [ ] **錯誤情境**：模擬 `RtnCode !== 1` 時，系統不更新訂單狀態（不誤判為成功）
