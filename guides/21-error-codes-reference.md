> 對應 ECPay API 版本 | 最後更新：2026-03

# 全服務錯誤碼集中參考

> 💡 **不知道哪裡出錯？** 前往 [guides/15 — 除錯指南](./15-troubleshooting.md) 從症狀開始排查。本頁以**錯誤碼**為導向查找。

## 遇到錯誤怎麼查？

```
遇到錯誤？
├── 有數字錯誤碼（如 10200073）
│   ├── AIO 金流 → 見下方 §AIO 金流錯誤碼（CMV-SHA256）
│   ├── 站內付 2.0 → 見下方 §站內付2.0 / 幕後授權錯誤碼
│   └── 物流 / 發票 → 見對應服務章節
├── TransCode ≠ 1 或 RtnCode ≠ 1（AES-JSON 服務）
│   └── 見下方 §錯誤碼閱讀方式 AES-JSON 段落
└── 收到 ErrorMessage 文字訊息
    └── 查對應服務的 references/ API 文件（見 references/README.md）
```

## 常見錯誤碼快速查找

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/` 各服務對應檔案

| 錯誤碼 | 服務 | 一句話原因 | 詳見 |
|--------|------|-----------|------|
| **TransCode=1** | AES-JSON 全服務 | 外層成功，需再檢查 RtnCode | 下方 §AES-JSON |
| **TransCode!=1** | AES-JSON 全服務 | 外層失敗（加密/格式/傳輸層問題；電子票證另需檢查 CheckMacValue） | [guides/14](./14-aes-encryption.md) + [guides/15](./15-troubleshooting.md) |
| 1 | 全服務 | 交易成功 | — |
| 2 | AIO 金流 | ATM 取號成功（等待轉帳） | AIO 金流 |
| 10100073 | AIO 金流 | CVS/BARCODE 取號成功（等待繳費） | AIO 金流 |
| 10200009 | AIO 金流 | 訂單已過期 | AIO 金流 |
| 10200043 | 站內付 2.0/AIO | 3D 驗證失敗 | 站內付 2.0/AIO |
| 10200047 | AIO 金流 | MerchantTradeNo 重複 | AIO 金流 |
| 10200050 | AIO 金流 | TotalAmount 超出範圍 | AIO 金流 |
| 10200058 | 站內付 2.0/AIO | 信用卡授權失敗 | 站內付 2.0/AIO |
| 10200073 | CMV 驗證服務 | CheckMacValue 驗證失敗 | AIO/物流/電子票證 |
| 10200095 | AIO 金流 | 重複付款 | AIO 金流 |
| 10200105 | AIO 金流 | 金額低於 BNPL 門檻（最低 3,000） | AIO 金流 |
| 10200115 | 站內付 2.0/AIO | 信用卡授權逾時 | 站內付 2.0/AIO |
| 10300006 | 國內物流 | 物流訂單已過期 | 物流 |
| 10100058 | AIO 金流 | ATM 繳費期限已過 | AIO 金流 |
| 10100058 | 電子發票 | 發票作業逾時（⚠️ 同一代碼跨服務含意不同，依收到 callback 的服務判斷） | 發票 |
| 100** | AES-JSON | TransCode 外層錯誤（加密/格式問題） | AES-JSON |

> 完整錯誤碼按服務分類列於下方各節。快速排查流程請見 [guides/15-troubleshooting.md](./15-troubleshooting.md) 的快速排查決策樹。
> **未列出的錯誤碼**：請 `web_fetch` references/ 對應文件查閱（如 `references/Payment/全方位金流API技術文件.md`），API 文件的「回傳參數說明」或「RtnCode 對照表」章節有完整清單。

## 錯誤碼閱讀方式

ECPay API 的錯誤回傳分為兩種模式：

### CMV-SHA256（AIO 金流）/ CMV-MD5（國內物流）— 單層 RtnCode

回應格式為 pipe-separated 或 URL-encoded 字串，直接檢查 `RtnCode`：
- `RtnCode=1`：交易成功
- `RtnCode=2`：ATM 取號成功（非錯誤，等待轉帳）
- `RtnCode=10100073`：CVS/BARCODE 取號成功（非錯誤，等待繳費）
- 其他值：錯誤

> **CMV-SHA256** 使用 SHA256 CheckMacValue，**CMV-MD5**（國內物流）使用 **MD5** CheckMacValue。兩者回應解析方式相同，但雜湊演算法不同。

### AES-JSON（站內付 2.0、幕後授權、電子發票、全方位物流 v2、跨境物流）— 雙層 TransCode → RtnCode

> ⚠️ **電子票證**也使用 AES-JSON 格式，但額外包含 CheckMacValue（SHA256），屬於 AES-JSON + CMV 協議。錯誤碼邏輯相同，但多一層 CheckMacValue 驗證。詳見 [guides/09](./09-ecticket.md)。

回應格式為三層 JSON，需先檢查外層 `TransCode` 再解密 `Data` 檢查 `RtnCode`：
- `TransCode=1` + `RtnCode=1`：成功
- `TransCode≠1`：外層錯誤（通常是加密/格式問題）
- `TransCode=1` + `RtnCode≠1`：業務邏輯錯誤

```json
{
  "MerchantID": "3002607",
  "RpHeader": { "Timestamp": 1709618401 },
  "TransCode": 1,
  "TransMsg": "",
  "Data": "Base64EncodedAESEncryptedString..."
}
```

## AIO 金流錯誤碼（CMV-SHA256）

以下錯誤碼來自 [guides/01-payment-aio.md](./01-payment-aio.md) 和 [guides/15-troubleshooting.md](./15-troubleshooting.md) 的實際記載。

### 成功狀態碼

| RtnCode | 含義 | 處理方式 |
|---------|------|---------|
| 1 | 付款成功 | 正常處理訂單 |
| 2 | ATM 取號成功 | 等待消費者轉帳，**勿視為錯誤** |
| 10100073 | CVS/BARCODE 取號成功 | 等待消費者繳費，**勿視為錯誤** |

### 錯誤碼

| RtnCode | 含義 | 可重試 | 處理方式 |
|---------|------|--------|---------|
| 10100001 | 超商代碼已失效 | 否 | 重新取號 |
| 10100058 | ATM 繳費期限已過 | 否 | 重新建立訂單取號 |
| 10200009 | 訂單已過期 | 否 | 檢查 ExpireDate 設定，重新建立訂單 |
| 10200043 | 3D 驗證失敗 | 是 | 請消費者重新進行 3D 驗證 |
| 10200047 | MerchantTradeNo 重複 | 否 | 使用不同的訂單編號（最長 20 字元，僅英數字） |
| 10200050 | 金額不符 | 否 | 檢查 TotalAmount 是否正確 |
| 10200058 | 信用卡授權失敗 | 是 | 請消費者確認卡片資訊或更換信用卡 |
| 10200073 | CheckMacValue 驗證失敗 | 否 | 檢查 HashKey/HashIV 和加密邏輯（見下方安全驗證段落） |
| 10200095 | 交易已付款 | 否 | 重複付款，檢查訂單是否已處理 |
| 10200105 | BNPL 金額未達最低 | 否 | TotalAmount 需 >= 3,000 元 |
| 10200115 | 信用卡授權逾時 | 是 | 請消費者重新付款 |
| 10300006 | 超商繳費期限已過 | 否 | 重新建立訂單 |

> **注意**：完整錯誤碼列表請參考官方 API 技術文件：
> [references/Payment/全方位金流API技術文件.md](../references/Payment/全方位金流API技術文件.md)

## 站內付 2.0 錯誤碼（AES-JSON）

站內付2.0 使用三層 JSON 結構。需先檢查 `TransCode`，再解密 `Data` 檢查 `RtnCode`。

### 外層 TransCode

| TransCode | 含義 | 處理方式 |
|-----------|------|---------|
| 1 | API 呼叫成功 | 解密 Data 欄位，繼續檢查 RtnCode |
| 其他 | API 層級錯誤 | 檢查 TransMsg 取得錯誤描述，通常是 AES 加密錯誤或 JSON 格式問題 |

### 內層 RtnCode（Data 解密後）

| RtnCode | 含義 | 處理方式 |
|---------|------|---------|
| 1 | 操作成功 | 正常流程 |
| 其他 | 業務錯誤 | 檢查 RtnMsg，常見原因：參數錯誤、訂單不存在、重複操作 |

### 站內付 2.0 Callback 回應格式

站內付 2.0 Callback 收到後需回應純字串：

```
1|OK
```

> **注意**：各服務的 Callback 回應格式不同：
>
> 完整 Callback 總覽表見 [guides/22-webhook-events-reference.md](./22-webhook-events-reference.md) §Callback 總覽表。
> 快速對照：AIO / 站內付 2.0 / 信用卡幕後授權 / 非信用卡幕後取號 → `1|OK`，全方位/跨境物流 → AES 加密 JSON，國內物流 → `1|OK`（MD5），電子票證 → AES 加密 JSON（Data 內 `{"RtnCode": 1, "RtnMsg": "成功"}`）。

### 站內付 2.0 雙 Domain 注意事項

| 功能 | Domain |
|------|--------|
| Token 相關（GetTokenbyTrade/GetTokenbyUser/CreatePayment） | ecpg.ecpay.com.tw |
| 查詢/請退款 | ecpayment.ecpay.com.tw |

使用錯誤的 domain 會導致連線失敗或非預期錯誤。

> **注意**：完整錯誤碼列表請參考官方 API 技術文件：
> [references/Payment/站內付2.0API技術文件Web.md](../references/Payment/站內付2.0API技術文件Web.md)

## 幕後授權錯誤碼（AES-JSON）

信用卡幕後授權使用與站內付 2.0 相同的三層 JSON 結構（AES-JSON），回應結構為 `TransCode → 解密 Data → RtnCode`。

| RtnCode | 含義 | 處理方式 |
|---------|------|---------|
| 1 | 授權成功 | 正常流程 |
| 其他 | 授權失敗 | 檢查 RtnMsg，常見：卡號錯誤、額度不足、發卡行拒絕 |

信用卡幕後授權的 Callback（ReturnURL）回應為 `1|OK`（官方規格 45907.md）。非信用卡幕後取號的 Callback（ReturnURL）回應亦為 `1|OK`。

> **注意**：幕後授權的交易訊息代碼一覽表見官方附錄：
> [references/Payment/信用卡幕後授權API技術文件.md](../references/Payment/信用卡幕後授權API技術文件.md)（附錄 / 交易訊息代碼一覽表）

## 電子發票錯誤碼（AES-JSON）

B2C/B2B 電子發票使用三層 JSON 結構（AES-JSON），回應結構為 `TransCode → 解密 Data → RtnCode`。

| RtnCode | 含義 | 處理方式 |
|---------|------|---------|
| 1 | 操作成功 | 正常流程 |
| 其他 | 開立/折讓/作廢失敗 | 檢查 RtnMsg，常見：發票號碼格式錯誤、稅額計算不符、發票已作廢 |

### 常見發票錯誤場景

| 錯誤場景 | 原因 | 解決方式 |
|----------|------|---------|
| 統一編號格式錯誤 | 必須為 8 位數字 | 驗證端點：`/B2CInvoice/CheckCompanyIdentifier` |
| 稅額與金額不符 | SalesAmount 必須等於 TaxAmount + 各項 ItemAmount 總和 | 重新計算金額 |
| 發票已開立 | RelateNumber 重複 | 使用新的關聯號碼（如 `'Inv' . time()`） |
| 載具格式錯誤（手機條碼） | 手機條碼必須 `/` 開頭共 8 碼 | 驗證端點：`/B2CInvoice/CheckBarcode` |
| 載具格式錯誤（自然人憑證） | 自然人憑證 2 碼大寫英文字母開頭共 16 碼 | 前端驗證格式 |
| 捐贈碼格式錯誤 | 捐贈碼格式不正確 | 驗證端點：`/B2CInvoice/CheckLoveCode` |

### B2B 與 B2C 發票差異

| 項目 | B2C | B2B |
|------|-----|-----|
| 端點前綴 | `/B2CInvoice/` | `/B2BInvoice/` |
| Revision | `3.0.0` | `1.0.0` |
| RqHeader | Timestamp | Timestamp + **RqID**（UUID） |
| 額外 API | — | Confirm/Reject 系列 |

> **注意**：完整錯誤碼列表請參考官方 API 技術文件：
> [references/Invoice/B2C電子發票介接技術文件.md](../references/Invoice/B2C電子發票介接技術文件.md)

## 物流錯誤碼

### 國內物流（CMV-MD5 — Form POST + CheckMacValue MD5）

| RtnCode | 含義 | 處理方式 |
|---------|------|---------|
| 1 | 操作成功（建立物流訂單） | 正常流程 |
| 0 | 操作失敗 | Pipe-separated 格式：`0|ErrorMessage`，檢查錯誤訊息 |

國內物流的回應格式有 6 種（依端點不同），主要成功格式為：

```
1|MerchantID=2000132&AllPayLogisticsID=1234567890&...
```

錯誤格式為：

```
0|ErrorMessage
```

### 全方位物流 v2（AES-JSON — AES JSON）

| RtnCode | 含義 | 處理方式 |
|---------|------|---------|
| 1 | 操作成功 | 正常流程 |
| 其他 | 物流操作失敗 | 解密 Data 後檢查 RtnMsg |

> **注意**：全方位物流 v2 使用 **AES JSON**（AES-JSON），與國內物流的 **Form + CheckMacValue MD5**（CMV-MD5）完全不同。切勿混淆兩者的認證和請求格式。

### 物流狀態碼

#### 常用物流狀態碼速查

| 狀態碼 | 含義 | 適用 |
|--------|------|------|
| 300 | 訂單處理中 | 全超商 |
| 2030 | 已出貨/已到店 | 7-ELEVEN |
| 2063 | 已取件 | 7-ELEVEN |
| 2067 | 逾期未取退回 | 7-ELEVEN |
| 3022 | 已到店 | 全家 |
| 3024 | 已取件 | 全家 |
| 3032 | 逾期退回 | 全家 |
| 3122 | 已到店 | 萊爾富 |
| 3124 | 已取件 | 萊爾富 |
| 5005 | 已配達 | 宅配 |
| 5011 | 配達失敗 | 宅配 |

> **完整狀態碼**（含數十種中間狀態）見 Excel 檔案：

- `scripts/SDK_PHP/example/Logistics/logistics_status.xlsx`（完整狀態碼對照表）
- `scripts/SDK_PHP/example/Logistics/logistics_history.xlsx`（狀態歷程對照表）

> **注意**：超商退貨（CVS Return）建單的回傳結果中不會包含 `AllPayLogisticsID`。
> 需改用 `RtnMerchantTradeNo`（綠界回傳的退貨交易編號）追蹤退貨狀態。

> **注意**：完整物流 API 規格請參考：
> [references/Logistics/物流整合API技術文件.md](../references/Logistics/物流整合API技術文件.md)

## 安全驗證相關錯誤

### CheckMacValue 驗證失敗

症狀：`10200073 CheckMacValue verify fail`（AIO 金流）

CheckMacValue 驗證失敗？→ 完整排查流程見 [guides/13 §驗證步驟](./13-checkmacvalue.md) + [guides/15 §CheckMacValue 排查](./15-troubleshooting.md)。

### AES 加密/解密失敗（AES-JSON 服務通用）

症狀：`TransCode≠1`，TransMsg 顯示加密相關錯誤

排查步驟：
1. **Key/IV 長度** — 必須取前 16 bytes（AES-128-CBC）
2. **加解密順序** — 加密前先 URL encode，解密後才 URL decode（ECPay 獨有）
3. **Padding** — 使用 PKCS7 padding
4. **Base64** — 加密後的密文必須 Base64 encode，確認沒有多餘的換行或空格
5. **JSON 格式** — 確認 JSON 字串無多餘空格或 BOM

詳見：[guides/14-aes-encryption.md](./14-aes-encryption.md)

## HTTP 層級錯誤

## PHP SDK 內部錯誤碼（RtnException）

> ℹ️ 以下錯誤碼由 `scripts/SDK_PHP/src/Config/RtnException.php` 定義，**僅在使用 ECPay 官方 PHP SDK 時可能拋出**，與 ECPay API 回傳的 RtnCode 無關。

| 碼 | 說明 | 常見原因 |
|----|------|---------|
| 102 | CMV 生成失敗 | HashKey/HashIV 設定錯誤或缺漏 |
| 103 | 回應不是合法 JSON | 網路異常或 ECPay 返回錯誤頁面（如 HTML 錯誤頁）|
| 104 | Request/Response 類別不存在 | SDK 版本不符或 autoloader 未正確設定 |
| 105 | HTTP 請求回應失敗 | cURL 傳送後 HTTP 狀態碼非 2xx |
| 106 | CheckMacValue 驗證失敗 | 回應 CMV 與本地計算結果不符，可能遭竄改或 Key 錯誤 |
| 107 | cURL 初始化失敗 | 系統 cURL 未安裝，或 PHP cURL 擴充未啟用 |
| 108 | cURL 執行失敗 | 網路不通、SSL 憑證錯誤或 DNS 解析失敗 |
| 109 | AES 解密失敗 | HashKey/HashIV 錯誤，或密文損毀（padding error）|
| 110 | AES 加密失敗 | HashKey/HashIV 為空，或 OpenSSL 擴充未啟用 |
| 111 | JSON 解密後不是合法 JSON | AES 解密成功但解密後內容非 JSON（可能 Key 錯誤）|

> ⚠️ **排查提示**：
> - 106（CMV 驗證失敗）通常代表 HashKey/HashIV 與服務不符（金流/發票/物流各有獨立帳號），或本地計算邏輯有誤，詳見 [guides/13-checkmacvalue.md](./13-checkmacvalue.md)。
> - 109（AES 解密失敗）最常見原因是 HashKey/HashIV 截取錯誤（應取前 16 bytes），詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md)。



### 403 Forbidden（Rate Limit）

ECPay 會在 API 呼叫過頻時回傳 403。觸發後需等待約 30 分鐘。

建議：
- 避免在短時間內大量呼叫 API
- 檢查是否有迴圈或重試邏輯不當
- 批次操作使用排隊機制

### Timeout / Connection Refused

- 確認 TLS 1.2 以上（ECPay 強制要求）
- 確認 DNS 解析正確（測試環境使用 `-stage` 子域名）
- 設定合理的 timeout（建議 30 秒）
- 確認防火牆規則允許連線至 ECPay domain

### ReturnURL 收不到通知

排查步驟：
1. **URL 格式** — 必須是完整的 `https://` URL
2. **防火牆** — 確認伺服器允許綠界 IP 存取
3. **埠號** — 僅支援 80/443
4. **SSL** — 必須 TLS 1.2
5. **CDN** — 不可放在 CDN 後面
6. **回應格式** — 必須回應純字串 `1|OK`（不可有 HTML 標籤、BOM），站內付 2.0 / 信用卡幕後授權亦回應 `1|OK`（官方規格 9058.md / 45907.md）
7. **編碼** — 非 ASCII 域名需用 punycode
8. **特殊字元** — URL 中不可含分號 `;`、管道 `|`、反引號 `` ` ``
9. **超時** — 處理邏輯不可太久，綠界等待回應的時間有限

**重送機制**：如果沒收到正確回應，綠界會每 5-15 分鐘重送，每天最多 4 次。務必實作冪等性處理以避免重複入帳。

### TLS 相關

```bash
# 檢查 TLS 連線
openssl s_client -connect payment.ecpay.com.tw:443 -tls1_2

# 檢查 DNS 解析
nslookup payment.ecpay.com.tw

# 測試 API 可達性
curl -v --connect-timeout 10 https://payment.ecpay.com.tw
```

## 快速排查決策樹

> 完整決策樹已移至 [guides/15-troubleshooting.md](./15-troubleshooting.md) 頂部，以該處為唯一來源。

## 環境混用檢查

| 環境 | 特徵 | 測試帳號 |
|------|------|---------|
| 測試環境 | URL 含 `-stage` | MerchantID=3002607（金流）/ 2000132（發票） |
| 正式環境 | URL 不含 `-stage` | 向綠界申請的正式帳號 |

MerchantID / HashKey / HashIV 是配對的，測試與正式環境不可混用。

## 相關文件

### 指南
- [guides/15-troubleshooting.md](./15-troubleshooting.md) — 快速排查決策樹
- [guides/22-webhook-events-reference.md](./22-webhook-events-reference.md) — Callback 欄位定義

### 官方 API 文件索引（含完整錯誤碼定義）
- [references/Payment/全方位金流API技術文件.md](../references/Payment/全方位金流API技術文件.md) — AIO 金流錯誤碼
- [references/Payment/站內付2.0API技術文件Web.md](../references/Payment/站內付2.0API技術文件Web.md) — 站內付 2.0 錯誤碼
- [references/Payment/信用卡幕後授權API技術文件.md](../references/Payment/信用卡幕後授權API技術文件.md) — 幕後授權錯誤碼
- [references/Invoice/B2C電子發票介接技術文件.md](../references/Invoice/B2C電子發票介接技術文件.md) — B2C 發票錯誤碼
- [references/Logistics/物流整合API技術文件.md](../references/Logistics/物流整合API技術文件.md) — 國內物流錯誤碼
- [references/Logistics/全方位物流服務API技術文件.md](../references/Logistics/全方位物流服務API技術文件.md) — 全方位物流錯誤碼
- [references/Logistics/綠界科技跨境物流API技術文件.md](../references/Logistics/綠界科技跨境物流API技術文件.md) — 跨境物流錯誤碼
- [references/Ecticket/價金保管-使用後核銷API技術文件.md](../references/Ecticket/價金保管-使用後核銷API技術文件.md) — 電子票證錯誤碼
