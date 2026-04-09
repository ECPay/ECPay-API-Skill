> 對應 ECPay API 版本 | 語言無關 HTTP 協議參考 | 最後更新：2026-04
>
> **更新頻率**：本協議文件每季驗證一次。最後驗證日期：2026-04。
> HTTP 基礎協議（POST、Content-Type、Domain 結構）通常穩定，年度變動機率 < 5%。

# HTTP 協議參考（非 PHP 語言必讀）

本文件是 ECPay 所有服務的 **語言無關 HTTP 協議參考**。PHP 開發者可直接使用官方 SDK，其他語言開發者必須了解底層 HTTP 協議才能正確串接。

ECPay 服務使用 **4 個 HTTP 協議模式**（CMV-SHA256 / AES-JSON / AES-JSON+CMV / CMV-MD5），衍生為下表中多種服務組合。每個模式的 Content-Type、認證方式、回應格式皆不同。

---

## 1. 協議模式總覽

| 服務 | 協議模式 | Content-Type | 認證方式 | Hash/加密 | 正式 Domain | 指南 |
|------|----------|-------------|---------|----------|-------------|------|
| AIO 全方位金流 | CMV-SHA256 | form-urlencoded | CheckMacValue | SHA256 | payment.ecpay.com.tw | [01](./01-payment-aio.md) |
| 國內物流 | CMV-MD5 | form-urlencoded | CheckMacValue | **MD5** | logistics.ecpay.com.tw | [06](./06-logistics-domestic.md) |
| 站內付2.0（Web） | AES-JSON | application/json | AES Data | AES-128-CBC | ecpg.ecpay.com.tw / ecpayment.ecpay.com.tw | [02](./02-payment-ecpg.md) |
| 站內付2.0（App） | AES-JSON | application/json | AES Data | AES-128-CBC | ecpg.ecpay.com.tw / ecpayment.ecpay.com.tw | [02](./02-payment-ecpg.md) |
| 信用卡幕後授權 | AES-JSON | application/json | AES Data | AES-128-CBC | ecpayment.ecpay.com.tw | [03](./03-payment-backend.md) |
| 非信用卡幕後取號 | AES-JSON | application/json | AES Data | AES-128-CBC | ecpayment.ecpay.com.tw | [03](./03-payment-backend.md) |
| B2C 電子發票 | AES-JSON | application/json | AES Data | AES-128-CBC | einvoice.ecpay.com.tw | [04](./04-invoice-b2c.md) |
| B2B 電子發票 | AES-JSON* | application/json | AES Data | AES-128-CBC | einvoice.ecpay.com.tw | [05](./05-invoice-b2b.md) |
| 離線電子發票 | AES-JSON | application/json | AES Data | AES-128-CBC | einvoice.ecpay.com.tw | [19](./19-invoice-offline.md) |
| 全方位物流 v2 | AES-JSON | application/json | AES Data | AES-128-CBC | logistics.ecpay.com.tw | [07](./07-logistics-allinone.md) |
| 跨境物流 | AES-JSON | application/json | AES Data | AES-128-CBC | logistics.ecpay.com.tw | [08](./08-logistics-crossborder.md) |
| 電子票證（3 種模式） | AES-JSON + CMV | application/json | AES Data + CheckMacValue | AES-128-CBC + SHA256 | ecticket.ecpay.com.tw | [09](./09-ecticket.md) |
| 直播收款 | AES-JSON（請求）/ AES-JSON+CMV（callback） | application/json | AES Data（callback 含 CheckMacValue，ECTicket 式 SHA256） | AES-128-CBC + SHA256 | ecpayment.ecpay.com.tw | [17 §直播](./17-hardware-services.md#直播收款指引) |
| AIO 對帳檔下載 | — | form-urlencoded | CheckMacValue (SHA256) | SHA256 | vendor.ecpay.com.tw(對帳用**專用域名**,非 payment.ecpay.com.tw) | 見 §對帳檔下載 |
| POS 刷卡機 | — | 專用 POS 協定 | 依 POS 規格 | — | — | [17 §POS](./17-hardware-services.md#pos-刷卡機串接指引) |
| Shopify 金流 | — | 依 Shopify 規格 | — | — | — | [10](./10-cart-plugins.md) |

> **AES-JSON***：B2B 發票的 RqHeader 與其他 AES-JSON 服務不同，多了 `RqID` 欄位，且 `Revision` 為 `1.0.0`（B2C 為 `3.0.0`）。詳見 [2.3 節](#23-aes-json-變體--b2b-發票)。

---

## 2. 協議模式詳細規格

### 2.1 CMV-SHA256 — Form POST + CheckMacValue (SHA256)

**適用服務**：AIO 全方位金流

| 項目 | 規格 |
|------|------|
| HTTP 方法 | POST |
| Content-Type | `application/x-www-form-urlencoded` |
| Accept | `text/html` |
| 認證方式 | CheckMacValue 欄位（SHA256 雜湊） |
| 正式環境 | `https://payment.ecpay.com.tw` |
| 測試環境 | `https://payment-stage.ecpay.com.tw` |

#### 請求構造步驟

1. 將所有參數組成 key=value 對
2. 計算 CheckMacValue（詳見 [guides/13-checkmacvalue.md](./13-checkmacvalue.md)）：
   - 參數依 key 字母順序排列（case-insensitive）
   - 頭尾加上 `HashKey=...&` 和 `&HashIV=...`
   - ECPay 專用 URL encode → SHA256 → 大寫
3. 將 CheckMacValue 加入參數
4. 以 `application/x-www-form-urlencoded` 格式編碼全部參數

#### 原始 HTTP 請求範例（AIO 建單）

```http
POST /Cashier/AioCheckOut/V5 HTTP/1.1
Host: payment-stage.ecpay.com.tw
Content-Type: application/x-www-form-urlencoded

MerchantID=3002607&MerchantTradeNo=TEST20260305001&MerchantTradeDate=2026%2f03%2f05+12%3a00%3a00&PaymentType=aio&TotalAmount=1000&TradeDesc=Test&ItemName=TestItem&ReturnURL=https%3a%2f%2fexample.com%2fcallback&ChoosePayment=ALL&EncryptType=1&CheckMacValue=ABCDEF1234567890...
```

> **注意**：AioCheckOut 的回應是 HTML 頁面（綠界付款頁），不是 API JSON 回應。此表單應由消費者瀏覽器提交（自動重導），而非由伺服器端 HTTP client 發送。

#### 回應格式對照表

| 端點 | 回應 Content-Type | 回應格式 | 解析方式 |
|------|------------------|---------|---------|
| `/Cashier/AioCheckOut/V5` | text/html | HTML 頁面 | 瀏覽器重導（不需程式解析） |
| `/Cashier/QueryTradeInfo/V5` | text/html | URL-encoded 字串 | 以 `&` 和 `=` 解析為 key-value |
| `/Cashier/QueryCreditCardPeriodInfo` | text/html | JSON | 標準 JSON parse |
| `/CreditDetail/DoAction` | text/html | URL-encoded 字串 | 以 `&` 和 `=` 解析為 key-value |
| `/CreditDetail/QueryTrade/V2` | application/json | JSON | 標準 JSON parse |
| `/Cashier/QueryPaymentInfo` | text/html | URL-encoded 字串 | 以 `&` 和 `=` 解析為 key-value |
| `/Cashier/CreditCardPeriodAction` | text/html | URL-encoded 字串 | 以 `&` 和 `=` 解析為 key-value |
| `/PaymentMedia/TradeNoAio` | text | 對帳檔 | 依檔案格式解析 |
| `/CreditDetail/FundingReconDetail` | text | 撥款對帳檔 | 依檔案格式解析 |

#### Callback 處理（ReturnURL / PaymentInfoURL）

1. 綠界以 Form POST 發送付款結果到你的 ReturnURL
2. 收到 POST body 後，重新計算 CheckMacValue 驗證一致性
3. 驗證通過後處理業務邏輯
4. **必須回應純字串 `1|OK`**（無 HTML 標籤、無 JSON、**無尾隨換行（`\n`）或空白**）— 許多語言的 `echo`/`print` 預設加換行，務必確認回應體為嚴格的 4 個字元
5. 若未回應 `1|OK`，綠界會重試：每 5-15 分鐘一次，每日最多 4 次

---

### 2.2 AES-JSON — JSON POST + AES-128-CBC

**適用服務**：站內付 2.0、B2C/B2B 電子發票、信用卡幕後授權、非信用卡幕後取號、全方位物流 v2、跨境物流、直播收款、離線電子發票

> ⚠️ **電子票證**雖使用 AES-JSON 結構，但額外要求 `CheckMacValue`（SHA256），屬於 AES-JSON + CMV 協議，詳見 [2.4 節](#24-aes-json--cmv--電子票證專用) 及 [guides/09](./09-ecticket.md)。

| 項目 | 規格 |
|------|------|
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證方式 | AES-128-CBC 加密 Data 欄位 + 10 分鐘 Timestamp 驗證 |

#### Domain 對照

| 服務 | 正式環境 | 測試環境 |
|------|---------|---------|
| 站內付 2.0 Token（GetTokenbyTrade/GetTokenbyUser/CreatePayment） | ecpg.ecpay.com.tw | ecpg-stage.ecpay.com.tw |
| ECPG 交易/查詢/請退款 | ecpayment.ecpay.com.tw | ecpayment-stage.ecpay.com.tw |
| 幕後授權/幕後取號 | ecpayment.ecpay.com.tw | ecpayment-stage.ecpay.com.tw |
| B2C/B2B/離線電子發票 | einvoice.ecpay.com.tw | einvoice-stage.ecpay.com.tw |
| 全方位物流 v2 | logistics.ecpay.com.tw | logistics-stage.ecpay.com.tw |
| 跨境物流 | logistics.ecpay.com.tw | logistics-stage.ecpay.com.tw |
| 直播收款 | ecpayment.ecpay.com.tw | ecpayment-stage.ecpay.com.tw |
| 電子票證 | ecticket.ecpay.com.tw | ecticket-stage.ecpay.com.tw |
| AIO 對帳檔下載 | vendor.ecpay.com.tw | vendor-stage.ecpay.com.tw |

> **注意**：ECPG 使用兩個不同 domain — Token 類及建立交易（GetTokenbyTrade、GetTokenbyUser、CreatePayment）走 `ecpg`，查詢/請退款（QueryTrade、DoAction）走 `ecpayment`。

#### RqHeader 欄位差異（服務間不一致）

> ⚠️ **不同 AES-JSON 服務的 RqHeader 欄位不同**，生成代碼時必須以對應服務的指南為準：
>
> | 服務 | RqHeader 欄位 | 範例 |
> |------|-------------|------|
> | **站內付 2.0** | **只有 `Timestamp`** — 無 `Revision` | `{"Timestamp": 1709618400}` |
> | 幕後授權/幕後取號 | 只有 `Timestamp` | `{"Timestamp": 1709618400}` |
> | **B2C 電子發票** | `Timestamp` + `Revision: "3.0.0"` | `{"Timestamp": 1709618400, "Revision": "3.0.0"}` |
> | **B2B 電子發票** | `Timestamp` + `Revision: "1.0.0"` + `RqID` | 詳見 [2.3 節](#23-aes-json-變體--b2b-發票) |
> | 全方位物流 v2 / 跨境物流 | `Timestamp` + `Revision: "1.0.0"` | `{"Timestamp": 1709618400, "Revision": "1.0.0"}` |
> | **電子票證** | **只有 `Timestamp`** — 無 `Revision`（與站內付相同） | `{"Timestamp": 1709618400}` |
>
> **最常見錯誤**：把電子發票的 `Revision: "3.0.0"` 加入站內付 2.0 請求 → ECPay 解析失敗。

#### 三層 JSON 結構

**請求格式**：

```json
{
  "MerchantID": "3002607",
  "RqHeader": {
    "Timestamp": 1709618400
  },
  "Data": "Base64EncodedAESEncryptedString..."
}
```

**回應格式**：

```json
{
  "MerchantID": "3002607",
  "RpHeader": {
    "Timestamp": 1709618401
  },
  "TransCode": 1,
  "TransMsg": "",
  "Data": "Base64EncodedAESEncryptedString..."
}
```

#### AES 加密流程（Data 欄位）

加密（請求）：業務參數 JSON → URL encode → AES-128-CBC 加密（PKCS7 padding）→ Base64 encode

解密（回應）：Base64 decode → AES-128-CBC 解密 → URL decode → JSON parse

> ⚠️ **AES-JSON 的 URL Encode 規則與 CMV 不同**
>
> - **CMV（SHA256/MD5）**：`urlencode()` 標準編碼，空格最終為 `+`（JS/Rust 等產生 `%20` 時需替換為 `+`）
> - **AES-JSON**：**外層請求體**為 application/json（Content-Type: application/json，非 URL-encoded 表單）；但 **Data 欄位內的業務 JSON** 在 AES 加密前**必須先 URL Encode**（空格→`+`，`~`→`%7E`），解密後**必須 URL Decode**
>
> 若在 AES-JSON 請求中套用 CMV 的 URL Encode 邏輯，AES 解密將失敗。詳見 [guides/14 §AES vs CMV URL Encode 對比表](./14-aes-encryption.md)。

詳細演算法與 12 語言實作見 [guides/14-aes-encryption.md](./14-aes-encryption.md)。

#### 原始 HTTP 請求範例（B2C 發票開立）

```http
POST /B2CInvoice/Issue HTTP/1.1
Host: einvoice-stage.ecpay.com.tw
Content-Type: application/json

{
  "MerchantID": "2000132",
  "RqHeader": {
    "Timestamp": 1709618400,
    "Revision": "3.0.0"
  },
  "Data": "Base64EncodedAESEncryptedString..."
}
```

#### 原始 HTTP 回應範例

```json
{
  "MerchantID": "2000132",
  "RpHeader": {
    "Timestamp": 1709618401
  },
  "TransCode": 1,
  "TransMsg": "",
  "Data": "Base64EncodedAESEncryptedString..."
}
```

解密 Data 後得到：

```json
{
  "RtnCode": 1,
  "RtnMsg": "開立發票成功",
  "InvoiceNo": "AB12345678",
  ...
}
```

#### 雙層錯誤檢查（重要）

AES-JSON 回應需要**兩層**檢查：

1. **傳輸層**：檢查 `TransCode === 1`
   - `TransCode !== 1`：傳輸失敗（加密錯誤、Timestamp 過期等），`TransMsg` 包含錯誤訊息
   - 此時 Data 欄位可能為空或無法解密

2. **業務層**：解密 Data 後檢查 `RtnCode === 1`
   - `RtnCode !== 1`：業務邏輯失敗（參數錯誤、餘額不足等），`RtnMsg` 包含錯誤訊息

```
if TransCode != 1:
    error = TransMsg  # 傳輸層錯誤
else:
    decrypted = decrypt(Data)
    if decrypted.RtnCode != 1:
        error = decrypted.RtnMsg  # 業務層錯誤
    else:
        success = decrypted  # 業務資料
```

#### Callback 處理

AES-JSON 的 Callback 處理因服務而異：

| 服務 | Callback 格式 | 商家回應格式 |
|------|-------------|------------|
| 站內付 2.0（ReturnURL） | JSON POST（三層結構，Data 需 AES 解密） | `1\|OK` |
| 站內付 2.0（OrderResultURL） | Form POST（`ResultData` 欄位為 JSON 字串，需先 JSON 解析取 `{TransCode, Data}`，再 AES 解密 `Data`） | HTML 頁面（前端跳轉） |
| 站內付 2.0（PeriodReturnURL） | JSON POST | `1\|OK` |
| 全方位物流 v2 | JSON POST（三層結構，Data 需 AES 解密） | **AES 加密 JSON**（三層結構，含 TransCode + Data） |
| 跨境物流 | JSON POST（三層結構，Data 需 AES 解密） | **AES 加密 JSON**（三層結構，含 TransCode + Data） |
| 電子票證 | JSON POST（四層結構，含 CheckMacValue） | **AES 加密 JSON + CheckMacValue**（Data 內 `{"RtnCode": 1, "RtnMsg": "成功"}`） |
| B2C 發票（線上折讓 ReturnURL） | **Form POST**（含 CheckMacValue **MD5**） | `1|OK` |

> ⚠️ B2C 發票的 `AllowanceByCollegiate` 是電子發票中唯一帶有 CheckMacValue 的 API。其 ReturnURL Callback 使用 Form POST + CheckMacValue（**MD5**，非 SHA256），格式同 AIO 金流但雜湊演算法不同。詳見 [guides/04](./04-invoice-b2c.md) 及[檢查碼機制](https://developers.ecpay.com.tw/38242.md)。

---

### 2.3 AES-JSON 變體 — B2B 發票

B2B 發票使用 AES-JSON 相同的 JSON + AES 協議，但 RqHeader 有重要差異：

| 差異項 | B2C 發票 | B2B 發票 |
|--------|---------|---------|
| RqHeader.Revision | `3.0.0` | `1.0.0` |
| RqHeader.RqID | 無 | **必填**（唯一請求識別碼，UUID 格式） |
| 端點前綴 | `/B2CInvoice/` | `/B2BInvoice/` |
| 特有 API | — | Confirm 系列（交換模式） |

**B2B 請求格式**：

```json
{
  "MerchantID": "2000132",
  "RqHeader": {
    "Timestamp": 1709618400,
    "RqID": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "Revision": "1.0.0"
  },
  "Data": "Base64EncodedAESEncryptedString..."
}
```

---

### 2.4 AES-JSON + CMV — 電子票證 / 直播收款 Callback

> ⚠️ **URLEncode 與其他 AES-JSON 服務不同**：電子票證 CheckMacValue 的 URLEncode 為 PHP `urlencode()` 後接 `strtolower()`，**不做 .NET 字元替換**（`%21`→`!` 等）。與 AIO 金流的 `ecpayUrlEncode` 不同。詳見 [guides/09 §CheckMacValue 計算](./09-ecticket.md) 及 [guides/13 §電子票證 CMV](./13-checkmacvalue.md)。

**適用服務**：電子票證（價金保管-使用後核銷、價金保管-分期核銷、純發行-使用後核銷）、直播收款 Callback（請求為 AES-JSON，Callback 含 ECTicket 式 CheckMacValue）

電子票證採用 AES-JSON 結構，但額外要求 `CheckMacValue`（SHA256），屬於**四欄位結構**。

| 項目 | 規格 |
|------|------|
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證方式 | AES-128-CBC 加密 Data 欄位 + CheckMacValue（SHA256） |
| 正式環境 | `https://ecticket.ecpay.com.tw` |
| 測試環境 | `https://ecticket-stage.ecpay.com.tw` |

#### 請求結構（四欄位）

```json
{
  "MerchantID": "3085676",
  "RqHeader": { "Timestamp": 1709618400 },
  "Data": "Base64EncodedAESEncryptedString...",
  "CheckMacValue": "SHA256HexString..."
}
```

> ⚠️ 與其他 AES-JSON 服務不同，電子票證的 Request 和 Response 都包含 `CheckMacValue` 欄位（**必填**）。

#### CheckMacValue 計算公式

```
CheckMacValue = strtoupper(SHA256(toLowerCase(URLEncode(HashKey值 + Data明文JSON + HashIV值))))
```

> **重要**：此公式使用 **Data 明文 JSON**（加密前），直接串接 HashKey 值 + JSON + HashIV 值（無 `=` 或 `&` 分隔符），不是 AIO 金流的「所有欄位排序串接」方式。兩者不可混用。
> **⚠️ URLEncode 差異**：此處 URLEncode 為 PHP `urlencode()` 後接 `strtolower()`，**不做 .NET 字元替換**（%21→! 等）。與 AIO CheckMacValue 的 `ecpayUrlEncode` 不同。詳見 [guides/09 §CheckMacValue 計算](./09-ecticket.md)。

#### 三重錯誤檢查（重要）

電子票證比其他 AES-JSON 服務多一層 CheckMacValue 驗證：

1. **傳輸層**：`TransCode === 1`
2. **驗證層**：回應的 `CheckMacValue` 與自行計算結果一致
3. **業務層**：解密 Data 後 `RtnCode === 1`

---

### 2.5 CMV-MD5 — Form POST + CheckMacValue (MD5)

**適用服務**：國內物流

| 項目 | 規格 |
|------|------|
| HTTP 方法 | POST |
| Content-Type | `application/x-www-form-urlencoded` |
| 認證方式 | CheckMacValue 欄位（**MD5** 雜湊，非 SHA256） |
| 正式環境 | `https://logistics.ecpay.com.tw` |
| 測試環境 | `https://logistics-stage.ecpay.com.tw` |

> **關鍵差異**：國內物流使用 **MD5** 雜湊（非 SHA256）。CheckMacValue 計算流程與 CMV-SHA256 相同，但最後一步用 MD5 而非 SHA256。詳見 [guides/13-checkmacvalue.md](./13-checkmacvalue.md)。

#### 6 種回應格式的解析邏輯

國內物流的回應格式依端點不同，需要不同的解析邏輯：

**格式 1：Pipe-separated 成功回應**（建單用）

```
1|MerchantID=2000132&AllPayLogisticsID=1234567890&...
```

解析步驟：
1. 以 `|` 分割，取得 `["1", "MerchantID=2000132&AllPayLogisticsID=..."]`
2. 第一部分 `"1"` = 成功
3. 第二部分以 `&` 和 `=` 解析為 key-value（URL decode values）

**格式 2：Pipe-separated 錯誤回應**

```
0|ErrorMessage
```

解析步驟：
1. 以 `|` 分割
2. 第一部分 `"0"` = 失敗
3. 第二部分為錯誤訊息文字

**格式 3：逆物流回應**（Pipe-delimited 兩欄位）

```
RtnMerchantTradeNo|RtnOrderNo
```

解析步驟：以 `|` 分割，取得退貨單號

**格式 4：純文字確認**

```
1|OK
```

直接比對字串

**格式 5：JSON**（門市清單 GetStoreList）

標準 JSON parse

**格式 6：URL-encoded 字串**（查詢 QueryLogisticsTradeInfo）

```
AllPayLogisticsID=1234&MerchantTradeNo=Test123&...
```

以 `&` 和 `=` 解析為 key-value

#### 原始 HTTP 請求範例（物流建單）

```http
POST /Express/Create HTTP/1.1
Host: logistics-stage.ecpay.com.tw
Content-Type: application/x-www-form-urlencoded

MerchantID=2000132&MerchantTradeDate=2026%2f03%2f05+12%3a00%3a00&LogisticsType=CVS&LogisticsSubType=FAMI&GoodsAmount=100&...&CheckMacValue=ABCDEF...
```

#### Callback 處理（ServerReplyURL）

1. 綠界以 Form POST 發送物流狀態更新
2. 重新計算 CheckMacValue（**使用 MD5**）驗證
3. **必須回應 `1|OK`**
4. 重試機制：約每 2 小時重試，每日最多 4 次（與 CMV-SHA256 間隔不同）

---

### 2.6 HTTP Timeout 建議

| 操作類型 | 建議 Timeout | 說明 |
|---------|-------------|------|
| 一般 API 呼叫（建單、查詢） | 30 秒 | ECPay 正常回應時間 < 5 秒，30 秒為安全上限 |
| 對帳檔下載 | 60 秒 | 大量資料傳輸，視資料量而定 |
| Callback Handler 處理 | 10 秒內回應 | ECPay 期望快速回應，超時會視為失敗並重試 |
| AIO 查詢 Timestamp | 3 分鐘有效 | AIO `QueryTradeInfo` 的 `TimeStamp` 有效期僅 **3 分鐘**（非 10 分鐘），逾時需重新產生 |

> 各語言 HTTP Client 的 Timeout 設定方式見 [guides/24 §HTTP Client 推薦表](./24-multi-language-integration.md)。

---

## 3. 端點 URL 完整對照表

> ⚠️ **SNAPSHOT 2026-03** | 以下端點表均為快照，正式開發前請透過 `references/` 索引 web_fetch 官方最新規格確認。

### 全服務端點速查總表

> 一頁總覽所有 ECPay API 端點。各服務詳細端點列表見下方子章節。

| 服務 | 協議模式 | 測試 Domain | 正式 Domain | 端點數 | 詳細指南 |
|------|---------|------------|------------|-------|---------|
| **AIO 金流** | CMV-SHA256 | `payment-stage.ecpay.com.tw` | `payment.ecpay.com.tw` | 8 | [guides/01](./01-payment-aio.md) |
| AIO 對帳檔 | CMV-SHA256 | `vendor-stage.ecpay.com.tw` | `vendor.ecpay.com.tw` | 1 | [guides/01](./01-payment-aio.md) |
| **ECPG Token** | AES-JSON | `ecpg-stage.ecpay.com.tw` | `ecpg.ecpay.com.tw` | 8 | [guides/02](./02-payment-ecpg.md) |
| **ECPG 交易/查詢** | AES-JSON | `ecpayment-stage.ecpay.com.tw` | `ecpayment.ecpay.com.tw` | 7 | [guides/02](./02-payment-ecpg.md) |
| **幕後授權** | AES-JSON | `ecpayment-stage.ecpay.com.tw` | `ecpayment.ecpay.com.tw` | 8 | [guides/03](./03-payment-backend.md) |
| **非信用卡幕後取號** | AES-JSON | `ecpayment-stage.ecpay.com.tw` | `ecpayment.ecpay.com.tw` | 4 | [guides/03](./03-payment-backend.md) |
| **B2C 電子發票** | AES-JSON | `einvoice-stage.ecpay.com.tw` | `einvoice.ecpay.com.tw` | 26 | [guides/04](./04-invoice-b2c.md) |
| **B2B 發票（交換）** | AES-JSON* | `einvoice-stage.ecpay.com.tw` | `einvoice.ecpay.com.tw` | 30 | [guides/05](./05-invoice-b2b.md) |
| **B2B 發票（存證）** | AES-JSON* | `einvoice-stage.ecpay.com.tw` | `einvoice.ecpay.com.tw` | 17 | [guides/05](./05-invoice-b2b.md) |
| **離線電子發票** | AES-JSON | `einvoice-stage.ecpay.com.tw` | `einvoice.ecpay.com.tw` | 12 | [guides/19](./19-invoice-offline.md) |
| **國內物流** | CMV-MD5 | `logistics-stage.ecpay.com.tw` | `logistics.ecpay.com.tw` | 18 | [guides/06](./06-logistics-domestic.md) |
| **全方位物流 v2** | AES-JSON | `logistics-stage.ecpay.com.tw` | `logistics.ecpay.com.tw` | 14 | [guides/07](./07-logistics-allinone.md) |
| **跨境物流** | AES-JSON | `logistics-stage.ecpay.com.tw` | `logistics.ecpay.com.tw` | 5 | [guides/08](./08-logistics-crossborder.md) |
| **電子票證** | AES-JSON + CMV | `ecticket-stage.ecpay.com.tw` | `ecticket.ecpay.com.tw` | 14/6/12（3模式）| [guides/09](./09-ecticket.md) |
| **直播收款** | AES-JSON | `ecpayment-stage.ecpay.com.tw` | `ecpayment.ecpay.com.tw` | 5 | [guides/17 §直播](./17-hardware-services.md#直播收款指引) |

> ⚠️ **站內付 2.0 雙 Domain 陷阱**：站內付 2.0 Token 系列用 `ecpg` domain，交易/查詢系列用 `ecpayment` domain。混用會得到 404。
>
> 📊 **合計約 150+ 個 API 端點**，涵蓋 7 個 domain（各有 staging/production 對應）。
>
> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/` 各服務對應檔案 — 端點新增/異動以官方開發者文件為準
> AI 助手：使用前請透過 `references/` 索引讀取 `developers.ecpay.com.tw` 最新規格。
>
> **references/ 服務對應檔案速查**：
> | 服務 | 對應 references/ 檔案 |
> |------|---------------------|
> | AIO 金流 | `references/Payment/全方位金流介接技術文件.md` |
> | ECPG（站內付 2.0）| `references/Payment/站內付2.0API技術文件Web.md` |
> | 幕後授權 / 幕後取號 | `references/Payment/信用卡幕後授權介接技術文件.md` 等 |
> | B2C 電子發票 | `references/Invoice/B2C電子發票介接技術文件.md` |
> | B2B 電子發票 | `references/Invoice/B2B電子發票介接技術文件.md` |
> | 國內物流 | `references/Logistics/物流介接技術文件.md` |
> | 全方位 / 跨境物流 | `references/Logistics/全方位物流介接技術文件.md` 等 |
> | 電子票證 | `references/Ecticket/` 目錄下各模式文件 |
> | 直播收款 | `references/Payment/直播主收款網址串接技術文件.md` |

> ⚠️ **SNAPSHOT 2026-03** | 以下 §3.1-3.4 所有端點表均為快照，僅供端點查找導航
> 生成程式碼前，必須從對應的 `references/` 檔案 web_fetch 最新端點路徑與參數規格。

### 3.1 Payment（金流）端點

#### AIO 全方位金流（CMV-SHA256）

| 功能 | 端點路徑 | 回應格式 |
|------|---------|---------|
| 建立訂單 | `/Cashier/AioCheckOut/V5` | HTML（瀏覽器重導） |
| 查詢訂單 | `/Cashier/QueryTradeInfo/V5` | URL-encoded |
| 信用卡請退款 | `/CreditDetail/DoAction` | URL-encoded |
| 信用卡明細查詢 | `/CreditDetail/QueryTrade/V2` | JSON |
| 定期定額查詢 | `/Cashier/QueryCreditCardPeriodInfo` | JSON |
| 取號結果查詢 | `/Cashier/QueryPaymentInfo` | URL-encoded |
| 定期定額作業 | `/Cashier/CreditCardPeriodAction` | URL-encoded |
| 信用卡撥款對帳 | `/CreditDetail/FundingReconDetail` | text |

> Base URL（測試）：`https://payment-stage.ecpay.com.tw`
> Base URL（正式）：`https://payment.ecpay.com.tw`

#### 對帳檔下載（Domain 不同，注意！）

| 功能 | 端點路徑 | 回應格式 |
|------|---------|---------|
| 對帳檔下載 | `/PaymentMedia/TradeNoAio` | text |

> Base URL（測試）：`https://vendor-stage.ecpay.com.tw`
> Base URL（正式）：`https://vendor.ecpay.com.tw`
> **注意**：對帳檔下載的 domain 與其他 AIO 端點不同，使用 `vendor` 而非 `payment`。

#### 站內付 2.0（AES-JSON）

| 功能 | 端點路徑 | Base Domain |
|------|---------|------------|
| 以交易取 Token | `/Merchant/GetTokenbyTrade` | ecpg |
| 以會員取 Token | `/Merchant/GetTokenbyUser` | ecpg |
| 建立交易 | `/Merchant/CreatePayment` | ecpg |
| 綁定信用卡取 Token | `/Merchant/GetTokenbyBindingCard` | ecpg |
| 建立綁定信用卡 | `/Merchant/CreateBindCard` | ecpg |
| 以卡片 ID 建立交易 | `/Merchant/CreatePaymentWithCardID` | ecpg |
| 查詢會員綁定信用卡 | `/Merchant/GetMemberBindCard` | ecpg |
| 刪除會員綁定信用卡 | `/Merchant/DeleteMemberBindCard` | ecpg |
| 信用卡請退款 | `/1.0.0/Credit/DoAction` | ecpayment |
| 查詢訂單 | `/1.0.0/Cashier/QueryTrade` | ecpayment |
| 信用卡明細查詢 | `/1.0.0/CreditDetail/QueryTrade` | ecpayment |
| 定期定額查詢 | `/1.0.0/Cashier/QueryTrade`（同查詢訂單端點，以參數區分） | ecpayment |
| 定期定額作業 | `/1.0.0/Cashier/CreditCardPeriodAction` | ecpayment |
| 取號結果查詢 | `/1.0.0/Cashier/QueryPaymentInfo` | ecpayment |
| 撥款對帳下載 | `/1.0.0/Cashier/QueryTradeMedia` | ecpayment |

> ecpg Base URL（測試）：`https://ecpg-stage.ecpay.com.tw`
> ecpayment Base URL（測試）：`https://ecpayment-stage.ecpay.com.tw`

#### 信用卡幕後授權（AES-JSON）

| 功能 | 端點路徑 |
|------|---------|
| 信用卡卡號授權 | `/1.0.0/Cashier/BackAuth` |
| 信用卡請退款 | `/1.0.0/Credit/DoAction` |
| 查詢訂單 | `/1.0.0/Cashier/QueryTrade` |
| 查詢發卡行 | `/1.0.0/Cashier/QueryCardInfo` |
| 信用卡明細查詢 | `/1.0.0/CreditDetail/QueryTrade` |
| 定期定額查詢 | `/1.0.0/Cashier/QueryTrade` |
| 定期定額作業 | `/1.0.0/Cashier/CreditCardPeriodAction` |
| 撥款對帳下載 | `/1.0.0/Cashier/QueryTradeMedia` |

> Base URL（測試）：`https://ecpayment-stage.ecpay.com.tw`
> Base URL（正式）：`https://ecpayment.ecpay.com.tw`

#### 非信用卡幕後取號（AES-JSON）

| 功能 | 端點路徑 |
|------|---------|
| 產生繳費代碼 | `/1.0.0/Cashier/GenPaymentCode` |
| 查詢訂單 | `/1.0.0/Cashier/QueryTrade` |
| 取號結果查詢 | `/1.0.0/Cashier/QueryPaymentInfo` |
| 超商條碼查詢 | `/1.0.0/Cashier/QueryCVSBarcode` |

> Base URL 同信用卡幕後授權

### 3.2 Invoice（發票）端點

#### B2C 電子發票（AES-JSON，Revision 3.0.0）

| 功能 | 端點路徑 |
|------|---------|
| 查詢財政部配號 | `/B2CInvoice/GetGovInvoiceWordSetting` |
| 字軌與配號設定 | `/B2CInvoice/InvoiceWordSetting` |
| 設定字軌號碼狀態 | `/B2CInvoice/UpdateInvoiceWordStatus` |
| 查詢字軌 | `/B2CInvoice/GetInvoiceWordSetting` |
| 開立發票 | `/B2CInvoice/Issue` |
| 延遲開立 | `/B2CInvoice/DelayIssue` |
| 觸發開立 | `/B2CInvoice/TriggerIssue` |
| 編輯延遲開立 | `/B2CInvoice/EditDelayIssue` |
| 取消延遲 | `/B2CInvoice/CancelDelayIssue` |
| 一般折讓 | `/B2CInvoice/Allowance` |
| 線上折讓 | `/B2CInvoice/AllowanceByCollegiate` |
| 作廢發票 | `/B2CInvoice/Invalid` |
| 作廢折讓 | `/B2CInvoice/AllowanceInvalid` |
| 取消線上折讓 | `/B2CInvoice/CancelAllowance` |
| 註銷重開 | `/B2CInvoice/VoidWithReIssue` |
| 查詢發票明細 | `/B2CInvoice/GetIssue` |
| 查詢特定多筆發票 | `/B2CInvoice/GetIssueList` |
| 依關聯編號查詢 | `/B2CInvoice/GetIssueByRelateNo` |
| 查詢折讓明細 | `/B2CInvoice/GetAllowance` |
| 查詢作廢發票 | `/B2CInvoice/GetInvalid` |
| 查詢作廢折讓 | `/B2CInvoice/GetAllowanceInvalid` |
| 發送通知 | `/B2CInvoice/InvoiceNotify` |
| 發票列印 | `/B2CInvoice/InvoicePrint` |
| 統一編號驗證 | `/B2CInvoice/CheckCompanyIdentifier` |
| 手機條碼驗證 | `/B2CInvoice/CheckBarcode` |
| 捐贈碼驗證 | `/B2CInvoice/CheckLoveCode` |

> Base URL（測試）：`https://einvoice-stage.ecpay.com.tw`
> Base URL（正式）：`https://einvoice.ecpay.com.tw`

#### B2B 電子發票 — 交換模式（AES-JSON*，Revision 1.0.0）

| 功能 | 端點路徑 |
|------|---------|
| 開立發票 | `/B2BInvoice/Issue` |
| 折讓 | `/B2BInvoice/Allowance` |
| 作廢發票 | `/B2BInvoice/Invalid` |
| 作廢折讓 | `/B2BInvoice/AllowanceInvalid` |
| 查詢發票 | `/B2BInvoice/GetIssue` |
| 查詢折讓 | `/B2BInvoice/GetAllowance` |
| 查詢作廢發票 | `/B2BInvoice/GetInvalid` |
| 查詢作廢折讓 | `/B2BInvoice/GetAllowanceInvalid` |
| 確認發票 | `/B2BInvoice/IssueConfirm` |
| 確認折讓 | `/B2BInvoice/AllowanceConfirm` |
| 確認作廢 | `/B2BInvoice/InvalidConfirm` |
| 確認作廢折讓 | `/B2BInvoice/AllowanceInvalidConfirm` |
| 取消折讓 | `/B2BInvoice/CancelAllowance` |
| 確認取消折讓 | `/B2BInvoice/CancelAllowanceConfirm` |
| 退回發票 | `/B2BInvoice/Reject` |
| 確認退回 | `/B2BInvoice/RejectConfirm` |
| 發送通知 | `/B2BInvoice/Notify` |
| 交易對象維護 | `/B2BInvoice/MaintainMerchantCustomerData` |
| 查詢開立確認 | `/B2BInvoice/GetIssueConfirm` |
| 查詢作廢確認 | `/B2BInvoice/GetInvalidConfirm` |
| 查詢折讓確認 | `/B2BInvoice/GetAllowanceConfirm` |
| 查詢折讓作廢確認 | `/B2BInvoice/GetAllowanceInvalidConfirm` |
| 查詢退回 | `/B2BInvoice/GetReject` |
| 查詢退回確認 | `/B2BInvoice/GetRejectConfirm` |
| 查詢字軌設定 | `/B2BInvoice/GetInvoiceWordSetting` |
| 查詢財政部配號 | `/B2BInvoice/GetGovInvoiceWordSetting` |
| 字軌與配號設定 | `/B2BInvoice/InvoiceWordSetting` |
| 設定字軌號碼狀態 | `/B2BInvoice/UpdateInvoiceWordStatus` |
| 發票列印 | `/B2BInvoice/InvoicePrint` |
| 發票列印 PDF | `/B2BInvoice/InvoicePrintPDF` |

> Base URL 同 B2C 發票

#### B2B 電子發票 — 存證模式（AES-JSON*，Revision 1.0.0）

| 功能 | 端點路徑 |
|------|---------|
| 交易對象維護 | `/B2BInvoice/MaintainMerchantCustomerData` |
| 查詢財政部配號 | `/B2BInvoice/GetGovInvoiceWordSetting` |
| 字軌與配號設定 | `/B2BInvoice/InvoiceWordSetting` |
| 設定字軌號碼狀態 | `/B2BInvoice/UpdateInvoiceWordStatus` |
| 查詢字軌設定 | `/B2BInvoice/GetInvoiceWordSetting` |
| 開立發票 | `/B2BInvoice/Issue` |
| 折讓 | `/B2BInvoice/Allowance` |
| 作廢發票 | `/B2BInvoice/Invalid` |
| 作廢折讓 | `/B2BInvoice/AllowanceInvalid` |
| 註銷重開 | `/B2BInvoice/VoidWithReIssue` |
| 查詢發票 | `/B2BInvoice/GetIssue` |
| 查詢作廢發票 | `/B2BInvoice/GetInvalid` |
| 查詢折讓 | `/B2BInvoice/GetAllowance` |
| 查詢作廢折讓 | `/B2BInvoice/GetAllowanceInvalid` |
| 發送通知 | `/B2BInvoice/Notify` |
| 發票列印 | `/B2BInvoice/InvoicePrint` |
| 發票列印 PDF | `/B2BInvoice/InvoicePrintPDF` |

> 存證模式不含 Confirm/Reject 系列 API（約 17 個端點）

### 3.3 Logistics（物流）端點

#### 國內物流（CMV-MD5，MD5）

| 功能 | 端點路徑 | 回應格式 |
|------|---------|---------|
| 測試標籤產生 | `/Express/CreateTestData` | pipe-separated |
| 門市電子地圖 | `/Express/map` | HTML |
| 門市訂單建立 | `/Express/Create` | pipe-separated |
| 宅配訂單建立 | `/Express/Create` | pipe-separated |
| 列印 C2C 7-ELEVEN | `/Express/PrintUniMartC2COrderInfo` | HTML |
| 列印 C2C 全家 | `/Express/PrintFAMIC2COrderInfo` | HTML |
| 列印 C2C 萊爾富 | `/Express/PrintHILIFEC2COrderInfo` | HTML |
| 列印 C2C OK 超商 | `/Express/PrintOKMARTC2COrderInfo` | HTML |
| 列印 B2C / 測標 / 宅配 | `/helper/printTradeDocument` | HTML |
| 逆物流 B2C 7-ELEVEN | `/express/ReturnUniMartCVS` | pipe-separated |
| 逆物流 B2C 全家 | `/express/ReturnCVS` | pipe-separated |
| 逆物流 B2C 萊爾富 | `/express/ReturnHilifeCVS` | pipe-separated |
| 逆物流宅配 | `/Express/ReturnHome` | plain text `1|OK` |
| 異動 B2C | `/Helper/UpdateShipmentInfo` | plain text `1|OK` |
| 異動 C2C | `/Express/UpdateStoreInfo` | plain text `1|OK` |
| 取消 C2C 7-ELEVEN | `/Express/CancelC2COrder` | plain text `1|OK` |
| 查詢物流訂單 | `/Helper/QueryLogisticsTradeInfo/V2` | URL-encoded |
| 取得門市清單 | `/Helper/GetStoreList` | JSON |

> Base URL（測試）：`https://logistics-stage.ecpay.com.tw`
> Base URL（正式）：`https://logistics.ecpay.com.tw`

#### 全方位物流 v2（AES-JSON）

| 功能 | 端點路徑 |
|------|---------|
| 物流選擇頁面重導 | `/Express/v2/RedirectToLogisticsSelection` |
| 暫存訂單建立 | `/Express/v2/CreateTempTrade` |
| 更新暫存訂單 | `/Express/v2/UpdateTempTrade` |
| 成立訂單 | `/Express/v2/CreateByTempTrade` |
| 查詢訂單 | `/Express/v2/QueryLogisticsTradeInfo` |
| 列印物流單 | `/Express/v2/PrintTradeDocument` |
| B2C 全家退貨 | `/Express/v2/ReturnCVS` |
| B2C 統一超商退貨 | `/Express/v2/ReturnUniMartCVS` |
| B2C 萊爾富退貨 | `/Express/v2/ReturnHilifeCVS` |
| 宅配退貨 | `/Express/v2/ReturnHome` |
| B2C 更新出貨資訊 | `/Express/v2/UpdateShipmentInfo` |
| C2C 更新店到店資訊 | `/Express/v2/UpdateStoreInfo` |
| C2C 取消訂單 | `/Express/v2/CancelC2COrder` |
| 建立測試資料 | `/Express/v2/CreateTestData` |

> Base URL 同國內物流

#### 跨境物流（AES-JSON）

| 功能 | 端點路徑 |
|------|---------|
| 跨境建單（超商/宅配）| `/CrossBorder/Create` |
| 查詢跨境物流 | `/CrossBorder/QueryLogisticsTradeInfo` |
| 海外電子地圖 | `/CrossBorder/Map` |
| 列印 | `/CrossBorder/Print` |
| 建立測試資料 | `/CrossBorder/CreateTestData` |

> Base URL 同國內物流

### 3.4 Ecticket（電子票證）端點（AES-JSON + CMV）

| 功能類別 | 主要端點（依模式不同） |
|---------|---------------------|
| 票券發行 | 發行、查詢、作廢 |
| 票券核銷 | 核銷、查詢核銷紀錄 |
| 對帳 | 對帳查詢、對帳確認 |

> Base URL（測試）：`https://ecticket-stage.ecpay.com.tw`
> Base URL（正式）：`https://ecticket.ecpay.com.tw`
> 測試帳號：官方提供公開測試帳號（見 [guides/09 §測試帳號](./09-ecticket.md)）

### 3.5 其他服務端點

**直播收款**（AES-JSON）

| 功能 | 端點路徑 |
|------|---------|
| 建立收款網址 | `/1.0.0/Cashier/LiveStreamPayment` |
| 查詢收款網址清單 | 後台操作，無 API |
| 查詢單筆詳情 | 後台操作，無 API |
| 關閉收款網址 | 後台操作，無 API |
| 查詢付款紀錄 | 後台操作，無 API |

> Base URL（測試）：`https://ecpayment-stage.ecpay.com.tw`
> Base URL（正式）：`https://ecpayment.ecpay.com.tw`
> 詳細端點路徑請查閱 `references/Payment/直播主收款網址串接技術文件.md`。

**POS 刷卡機**：使用專用 POS 串接協定（TCP/IP 或 COM Port），非標準 HTTP API。詳見 [guides/17-hardware-services.md §POS 刷卡機串接指引](./17-hardware-services.md#pos-刷卡機串接指引)。

**Shopify 金流**：依 Shopify 平台規格整合，詳見 [guides/10-cart-plugins.md](./10-cart-plugins.md)。

---

## 4. Callback 處理通用模式

### 4.1 共通原則

大多數需要即時處理的 server-to-server Callback（`ReturnURL` / `ServerReplyURL` / `PeriodReturnURL`）都遵循以下原則；`OrderResultURL` 屬前端跳轉，不在此列：

1. **ECPay 主動 POST** 結果到你指定的 URL（server-to-server；`OrderResultURL` 為前端 Form POST）
2. **必須正確回應**（否則 ECPay 會重試；`OrderResultURL` 不重試）
3. **HTTP Status 必須為 200**：回傳 201、202、204 等非 200 狀態碼，綠界一律視為失敗並觸發重試，即使 body 正確（如 `1|OK`）也無效
4. **重試機制依服務不同**：
   - AIO 金流 / 電子票證 / 非信用卡幕後取號 / 直播收款：每 5-15 分鐘重送，每日最多 4 次
   - 站內付 2.0 / 信用卡幕後授權 / 物流：約每 2 小時重試
5. **冪等處理**：同一筆交易可能收到多次通知，業務邏輯需冪等

### 4.2 回應格式彙總

> 完整 Callback 總覽表（含所有服務、URL 欄位名、觸發時機、重試機制）見 [guides/22-webhook-events-reference.md](./22-webhook-events-reference.md) §Callback 總覽表。
> 以下為按協議模式分類的快速對照：

| 協議模式 | Callback 發送格式 | 商家回應格式 |
|----------|-----------------|------------|
| CMV-SHA256（AIO 金流，含 BNPL） | Form POST（`application/x-www-form-urlencoded`） | 純字串 `1|OK` |
| AES-JSON（非信用卡幕後取號） | JSON POST（三層結構，Data 需 AES 解密） | 純字串 `1|OK` |
| AES-JSON（站內付 2.0、信用卡幕後授權） | JSON POST（三層結構，Data 需 AES 解密） | `1\|OK` |
| AES-JSON（全方位/跨境物流 v2） | JSON POST（三層結構，Data 需 AES 解密） | AES 加密 JSON（三層結構，含 TransCode） |
| AES-JSON + CMV（電子票證） | JSON POST（四層結構，含 CheckMacValue） | **AES 加密 JSON + CheckMacValue**（Data 內 `{"RtnCode": 1, "RtnMsg": "成功"}`） |
| AES-JSON + CMV（直播收款） | JSON POST（四層結構，含 ECTicket 式 CheckMacValue SHA256） | `1\|OK`(純文字,驗證方式同電子票證但回應格式不同) |
| Form POST + CheckMacValue MD5（發票：線上折讓 ReturnURL） | Form POST + CheckMacValue（**MD5**） | 純字串 `1|OK` |
| AES-JSON（發票：其他 API） | API 主動查詢（非 callback） | — |
| CMV-MD5（國內物流） | Form POST（`application/x-www-form-urlencoded`） | 純字串 `1|OK` |

> ⚠️ **RtnCode 型別依協定不同**：CMV-SHA256（AIO）與 CMV-MD5（國內物流）的 Callback 回傳 Form POST，其中 `RtnCode` 為**字串** `"1"`；AES-JSON 服務（ECPG 線上金流、發票、物流 v2、電子票證）解密後 `RtnCode` 為**整數** `1`。型別判斷錯誤會導致比對失敗。

#### RtnCode / TransCode 型別映射

> 不同協議模式的 RtnCode / TransCode 資料型別不同，混用會導致比較失敗：

| 協議模式 | RtnCode 型別 | 成功值 | TransCode | 常見錯誤 |
|---------|:-----------:|:------:|:---------:|---------|
| CMV-SHA256（AIO 金流）| **字串** `'1'` | `'1'` | 無 | 用 `=== 1`（整數比較）永遠不符 |
| CMV-MD5（國內物流）| **字串** `'1'` | `'1'` | 無 | 同上 |
| AES-JSON（ECPG / 發票 / 物流 v2）| **整數** `1` | `1` | 整數 `1` | 用 `=== '1'`（字串比較）永遠不符 |
| AES-JSON + CMV（電子票證）| **整數** `1` | `1` | 整數 `1` | 同上 |

> **防禦性寫法**（推薦）：`Number(rtnCode) === 1`（JavaScript）、`int(rtn_code) == 1`（Python）、`Integer.parseInt(rtnCode) == 1`（Java）

> ⚠️ **`1|OK` 常見錯誤格式（每種都會觸發 ECPay 重試，最多 4 次）**
>
> | 錯誤寫法 | 問題 |
> |----------|------|
> | `"1|OK"` | 字串前後含引號（框架自動加的 JSON 引號） |
> | `1|ok` | `OK` 必須大寫 |
> | `1OK` | 缺少管道符 `|` 分隔 |
> | `1|OK\n` 或 `1|OK ` | 結尾含換行或空白字元 |
>
> 正確回應必須是精確的 ASCII 字串 `1|OK`，無引號、無換行、無尾隨空白。

### 4.3 驗證流程

**CMV-SHA256 / CMV-MD5 Callback 驗證**：
1. 收到 POST body 的所有參數
2. 取出 `CheckMacValue` 欄位暫存
3. 用其餘參數重新計算 CheckMacValue（CMV-SHA256 用 SHA256，CMV-MD5 用 MD5）
4. 比對一致才處理業務邏輯

**AES-JSON Callback 驗證**：
1. 收到 JSON body
2. 取出 `Data` 欄位
3. 使用 AES 解密取得業務資料
4. 檢查解密後的 `RtnCode`

---

## 5. 常見陷阱

### 5.1 協議混淆

| 陷阱 | 正確做法 |
|------|---------|
| 所有 API 都用 SHA256 | 國內物流用 **MD5**，其他金流用 SHA256 |
| 所有 API 都回傳 JSON | AIO 查詢回傳 URL-encoded 字串，建單回傳 HTML |
| ECPG 只用一個 domain | Token 用 `ecpg`，其他用 `ecpayment` |
| 全方位物流 v2 與國內物流相同 | 完全不同的協議：AES JSON vs Form+CMV MD5 |

### 5.2 端點版本

| 陷阱 | 正確做法 |
|------|---------|
| 物流查詢用 `/V5` | 正確版本是 `/Helper/QueryLogisticsTradeInfo/V2` |
| AIO 查詢 Timestamp 有效 10 分鐘 | AIO 查詢的 `TimeStamp` 有效期僅 **3 分鐘** |

### 5.3 回應處理

| 陷阱 | 正確做法 |
|------|---------|
| AioCheckOut 回傳 JSON | 回傳 HTML 頁面，用於瀏覽器重導 |
| AES-JSON 只檢查 TransCode | 需要**雙層檢查**：TransCode=1 且 RtnCode=1 |
| 站內付 2.0 Callback 回應 `{ "TransCode": 1 }` | 站內付 2.0 / 信用卡幕後授權 Callback 需回應 `1\|OK`（官方規格 9058.md / 45907.md） |

### 5.4 加密與認證

| 陷阱 | 正確做法 |
|------|---------|
| B2B 發票 RqHeader 同 B2C | B2B 多了 `RqID`，`Revision` 為 `1.0.0` |
| AES Key/IV 用完整 HashKey/HashIV | AES-128 需要 16 bytes，取 HashKey/HashIV 的**前 16 bytes** |
| URL encode 用標準庫 | ECPay 有專用 URL encode 規則（見 [guides/13](./13-checkmacvalue.md)） |

### 5.5 測試環境

| 陷阱 | 正確做法 |
|------|---------|
| 電子票證用金流測試帳號 | 電子票證有**獨立的** HashKey/HashIV，與金流不同（見 guides/09 §測試帳號） |
| 正式/測試 domain 只差前綴 | 是的，加 `-stage` 即可，但要確認每個服務的 domain 不同 |

---

## 6. SDK Service → HTTP 協議快速對照

本表幫助所有開發者理解各 API 底層的 HTTP 操作。PHP SDK 使用者可對照 Service 類別；非 PHP 開發者可確認需要自行實作的項目。

| PHP SDK Service | 協議模式 | HTTP 動作 | 非 PHP 需實作 |
|----------------|----------|----------|-------------|
| `AutoSubmitFormWithCmvService` | CMV-SHA256 | 產生 HTML `<form>` 包含 CMV，由瀏覽器提交 | 產生 HTML 表單 + CheckMacValue SHA256 |
| `PostWithCmvStrResponseService` | CMV-MD5 | POST form-encoded，回應為 pipe-separated 字串 | HTTP POST + CMV MD5 + pipe 解析 |
| `PostWithCmvEncodedStrResponseService` | CMV-SHA256 | POST form-encoded，回應為 URL-encoded 字串 | HTTP POST + CMV SHA256 + URL-decode 解析 |
| `PostWithCmvVerifiedEncodedStrResponseService` | CMV-SHA256 | POST form-encoded，驗證回應 CMV | HTTP POST + CMV SHA256 + 回應 CMV 驗證 |
| `PostWithCmvJsonResponseService` | CMV-SHA256 | POST form-encoded，回應為 JSON | HTTP POST + CMV SHA256 + JSON parse |
| `PostWithAesJsonResponseService` | AES-JSON | POST JSON（三層 AES），回應為 JSON（三層 AES） | HTTP POST + AES encrypt/decrypt + JSON |
| `PostWithAesStrResponseService` | AES-JSON | POST JSON（三層 AES），回應為 HTML | HTTP POST + AES encrypt + HTML 處理 |

詳細的 SDK Service 翻譯規則見 [guides/12-sdk-reference.md](./12-sdk-reference.md)。

---

## 7. cURL 快速測試範例

以下 cURL 範例使用測試環境憑證，可直接在終端機執行以驗證 API 連通性。

### CMV-SHA256（AIO 查詢訂單）

```bash
# 查詢 AIO 訂單狀態（需先計算 CheckMacValue）
# MerchantID: 3002607, HashKey: pwFHCqoQZGmho4w6, HashIV: EkRm7iFT261dpevs
curl -X POST https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5 \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "MerchantID=3002607&MerchantTradeNo=你的訂單編號&TimeStamp=$(date +%s)&CheckMacValue=計算後的值"
```

> CheckMacValue 計算方式見 [guides/13](./13-checkmacvalue.md)。手動計算不便時，建議先用 Python/Node.js 指令碼產生。

### AES-JSON（B2C 發票開立）

```bash
# AES-JSON 三層結構（Data 欄位需 AES 加密）
# MerchantID: 2000132, HashKey: ejCk326UnaZWKisg, HashIV: q9jcZX8Ib9LM8wYk
curl -X POST https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue \
  -H "Content-Type: application/json" \
  -d '{
    "MerchantID": "2000132",
    "RqHeader": { "Timestamp": 替換為當前Unix時間戳, "Revision": "3.0.0" },
    "Data": "AES加密後的Base64字串"
  }'
```

> Data 欄位的 AES 加密流程：JSON → URL encode → AES-128-CBC → Base64。
> 完整加密步驟見 [guides/14](./14-aes-encryption.md)。

### CMV-MD5（國內物流查詢）

```bash
# 國內物流使用 MD5（非 SHA256）
# MerchantID: 2000132, HashKey: 5294y06JbISpM5x9, HashIV: v77hoKGq4kWxNNIS
curl -X POST https://logistics-stage.ecpay.com.tw/Helper/QueryLogisticsTradeInfo/V2 \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "MerchantID=2000132&AllPayLogisticsID=物流訂單編號&TimeStamp=$(date +%s)&CheckMacValue=計算後的值"
```

### 站內付 2.0 Token 取得

```bash
# 站內付 2.0 使用 ecpg domain（非 ecpayment）
curl -X POST https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade \
  -H "Content-Type: application/json" \
  -d '{
    "MerchantID": "3002607",
    "RqHeader": { "Timestamp": 替換為當前Unix時間戳 },
    "Data": "AES加密後的Base64字串"
  }'
```

### 連通性驗證（不需加密）

```bash
# 檢查 ECPay 各服務 domain 是否可達
curl -sI https://payment-stage.ecpay.com.tw | head -1
curl -sI https://ecpg-stage.ecpay.com.tw | head -1
curl -sI https://einvoice-stage.ecpay.com.tw | head -1
curl -sI https://logistics-stage.ecpay.com.tw | head -1
curl -sI https://ecticket-stage.ecpay.com.tw | head -1
```

---

## 相關文件

### 指南
- [guides/01-payment-aio.md](./01-payment-aio.md) — AIO 金流完整指南
- [guides/02-payment-ecpg.md](./02-payment-ecpg.md) — 站內付 2.0 指南
- [guides/13-checkmacvalue.md](./13-checkmacvalue.md) — CheckMacValue 12 語言實作
- [guides/14-aes-encryption.md](./14-aes-encryption.md) — AES 加解密 12 語言實作
- [guides/22-webhook-events-reference.md](./22-webhook-events-reference.md) — Callback 統一參考

### 官方 API 文件索引

**Payment（金流）**
- [references/Payment/全方位金流API技術文件.md](../references/Payment/全方位金流API技術文件.md) — AIO 金流 API 規格
- [references/Payment/站內付2.0API技術文件Web.md](../references/Payment/站內付2.0API技術文件Web.md) — 站內付 2.0 Web API 規格
- [references/Payment/站內付2.0API技術文件App.md](../references/Payment/站內付2.0API技術文件App.md) — 站內付 2.0 App API 規格
- [references/Payment/信用卡幕後授權API技術文件.md](../references/Payment/信用卡幕後授權API技術文件.md) — 幕後授權 API 規格
- [references/Payment/非信用卡幕後取號API技術文件.md](../references/Payment/非信用卡幕後取號API技術文件.md) — 幕後取號 API 規格
- [references/Payment/直播主收款網址串接技術文件.md](../references/Payment/直播主收款網址串接技術文件.md) — 直播收款 API 規格
- [references/Payment/Shopify專用金流API技術文件.md](../references/Payment/Shopify專用金流API技術文件.md) — Shopify 金流 API 規格
- [references/Payment/刷卡機POS串接規格.md](../references/Payment/刷卡機POS串接規格.md) — POS 刷卡機串接規格

**Invoice（電子發票）**
- [references/Invoice/B2C電子發票介接技術文件.md](../references/Invoice/B2C電子發票介接技術文件.md) — B2C 發票 API 規格
- [references/Invoice/B2B電子發票API技術文件_交換模式.md](../references/Invoice/B2B電子發票API技術文件_交換模式.md) — B2B 發票（交換模式）API 規格
- [references/Invoice/B2B電子發票API技術文件_存證模式.md](../references/Invoice/B2B電子發票API技術文件_存證模式.md) — B2B 發票（存證模式）API 規格
- [references/Invoice/離線電子發票API技術文件.md](../references/Invoice/離線電子發票API技術文件.md) — 離線電子發票 API 規格

**Logistics（物流）**
- [references/Logistics/物流整合API技術文件.md](../references/Logistics/物流整合API技術文件.md) — 國內物流 API 規格
- [references/Logistics/全方位物流服務API技術文件.md](../references/Logistics/全方位物流服務API技術文件.md) — 全方位物流 API 規格
- [references/Logistics/綠界科技跨境物流API技術文件.md](../references/Logistics/綠界科技跨境物流API技術文件.md) — 跨境物流 API 規格

**Ecticket（電子票證）**
- [references/Ecticket/價金保管-使用後核銷API技術文件.md](../references/Ecticket/價金保管-使用後核銷API技術文件.md) — 價金保管-使用後核銷 API 規格
- [references/Ecticket/價金保管-分期核銷API技術文件.md](../references/Ecticket/價金保管-分期核銷API技術文件.md) — 價金保管-分期核銷 API 規格
- [references/Ecticket/純發行-使用後核銷API技術文件.md](../references/Ecticket/純發行-使用後核銷API技術文件.md) — 純發行-使用後核銷 API 規格

**Cart（購物車）**
- [references/Cart/購物車設定說明.md](../references/Cart/購物車設定說明.md) — 購物車設定說明
