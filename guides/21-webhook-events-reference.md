> 對應 ECPay API 版本 | 最後更新：2026-03

# 統一 Callback/Webhook 參考

> **何時讀本文件**：當你需要了解各服務 callback 的回應格式、重試機制、冪等性處理時。
> - 排查 callback 收不到 → [guides/15](./15-troubleshooting.md) §2
> - 跨服務 callback 時序 → [guides/11](./11-cross-service-scenarios.md) §Callback 時序
> - 各服務的端點 URL → [guides/19](./19-http-protocol-reference.md)

本文件彙整所有 ECPay 服務的 Callback（Webhook）機制，提供統一的欄位定義和安全處理指引。

> **⚠️ 認證方式依服務而異**：金流 AIO → SHA256，國內物流 → **MD5**，B2C 發票線上折讓 → **MD5**，ECPG / 幕後授權 / 幕後取號 / 發票（其他 API）/ 物流 v2 → AES 解密（無 CheckMacValue），票證 → AES 解密 + CheckMacValue (SHA256)，**直播收款 → AES 解密 + ECTicket 式 CheckMacValue (SHA256)，但回應 `1|OK`（與票證不同）**。
> 錯用演算法（如把國內物流當 SHA256 計算）會導致所有 callback 驗證永遠失敗。

## ⚠️ RtnCode 型別依協議而異（靜默失敗常見根因）

> **必讀**：不同服務的 Callback 回傳的 `RtnCode` 型別不同，用錯比較方式會導致判斷永遠失敗。
>
> | 服務類別 | 協議 | RtnCode 型別 | 正確比較 | 錯誤寫法 |
> |---------|------|-------------|---------|---------|
> | AIO 金流、國內物流 | CMV（Form POST） | **字串** `"1"` | `=== '1'` | `=== 1`（永遠 false） |
> | ECPG 線上金流、發票、全方位物流 v2、電子票證 | AES-JSON | **整數** `1` | `=== 1` | `=== '1'`（永遠 false） |
>
> 防禦性寫法：`Number(rtnCode) === 1`（JavaScript）/ `int(rtn_code) == 1`（Python），但建議按服務使用正確型別。

## ⚡ Callback 回應格式速查（跨服務整合必讀）

> **各服務要求不同的回應格式，回應錯誤會導致綠界持續重送。** 確認你的回應 HTTP Status 為 **200**，否則 ECPay 視為失敗。

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/` 各服務對應檔案

| 服務 | 你的 Callback URL | 必須回應的格式 | 商家回應 Content-Type | 錯誤後果 |
|------|-----------------|--------------|---------------------|---------|
| AIO 金流（ReturnURL / PaymentInfoURL / PeriodReturnURL） | ReturnURL | `1\|OK`（純文字） | text/plain | 每 5-15 分鐘重送，每日最多 4 次（持續天數有上限，重試停止後需手動補查） |
| 站內付 2.0 | ReturnURL | `1\|OK`（純文字） | text/plain | 約每 2 小時重試 |
| 信用卡幕後授權 | ReturnURL | `1\|OK`（純文字） | text/plain | 約每 2 小時重試 |
| 非信用卡幕後取號 | ReturnURL | `1\|OK`（純文字） | text/plain | 每 5-15 分鐘重送，每日最多 4 次 |
| 國內物流 | ServerReplyURL | `1\|OK`（純文字） | text/plain | 約每 2 小時重試 |
| 全方位 / 跨境物流 | ServerReplyURL | AES 加密 JSON 三層結構 | application/json | 約每 2 小時重試 |
| **B2C 發票（線上折讓）** | ReturnURL | `1\|OK`（純文字） | text/plain | 未公開（CheckMacValue MD5，發票中唯一帶 CMV 的 callback） |
| 電子票證 | UseStatusNotifyURL | AES 加密 JSON + **CheckMacValue**（Data 內 `RtnCode=1`）| application/json | 每 5-15 分鐘重送，每日最多 4 次 |
| **直播收款** | ReturnURL | `1\|OK`(純文字) | text/plain | 每 5-15 分鐘重送,每日最多 4 次 |

> 📋 **直播收款 Callback 驗證流程(4 步驟)** — 請求格式同電子票證,回應格式同 AIO:
> 1. **接收**:綠界以 JSON POST 送達 `{MerchantID, RqHeader, Data, CheckMacValue}`
> 2. **AES 解密** `Data` 欄位 → 得到明文 JSON(含 RtnCode, RtnMsg, MerchantTradeNo 等業務欄位)
> 3. **驗證 CheckMacValue**:使用 **ECTicket 式 CMV 公式**(不是 AIO 式)
>    - 公式:`strtoupper(SHA256(toLowerCase(URLEncode(HashKey + Data 明文 JSON + HashIV))))`
>    - **不做 .NET 字元替換**(與 AIO `ecpayUrlEncode` 不同)— 詳見 [guides/13](./13-checkmacvalue.md) §電子票證 CMV 公式
>    - 必須用 timing-safe compare(`hash_equals` / `hmac.compare_digest` 等)
> 4. **回應**:純文字 `1|OK`(**與電子票證唯一差異** — 電子票證需回 AES 加密 JSON)
>
> ⚠️ 若將直播收款套用 AIO 的 `ecpayUrlEncode`(含 .NET 字元替換)會永遠驗簽失敗。

> **跨服務整合注意**：如果你同時使用金流 + 發票 + 物流，建議為各服務使用**不同的 callback URL**，
> 各自回應對應的正確格式。在同一 URL 判斷服務類型雖可行但容易出錯。

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

### 實作 Callback 的安全處理檢查清單

收到通知後，在業務邏輯前，**依序執行以下全部步驟**：

- [ ] **① 驗簽**：CheckMacValue（CMV 協議）或 AES 解密（AES-JSON 協議）必須通過
- [ ] **② RtnCode 型別**：確認比較方式正確（CMV 協議為字串 `=== '1'`；AES-JSON 為整數 `=== 1`）
- [ ] **③ 業務狀態**：RtnCode 是否在預期值範圍（AIO: `1`=成功, `2`=ATM取號成功, `10100073`=CVS取號成功）
- [ ] **④ 冪等檢查**：此 MerchantTradeNo 是否**已處理過**（防止重複入帳）；用 `upsert` 或先查後寫，不可用 `insert`
- [ ] **⑤ 立即回應**：依服務回應**精確格式**（`1|OK` 純文字 / AES 加密 JSON，見上方速查表），**10 秒內必須回應**，否則觸發重送
- [ ] **⑥ 非同步後處理**：回應後再處理業務邏輯（發信、開發票、更新庫存），避免阻塞導致超時重送

> **⚠️ 何時需要佇列（Queue）**
>
> | 情境 | 建議 |
> |------|------|
> | 日交易量 < 1,000 筆，業務邏輯簡單（< 1 秒） | **不需要**：直接在 Callback handler 中同步處理即可 |
> | 日交易量 > 1,000 筆，或業務邏輯耗時（開發票、發 Email）| **建議**：Callback 只做驗簽 + 冪等落庫，用佇列（MQ/Redis/DB task）非同步處理後續 |
> | 高並發（短時間大量交易）或有外部 API 呼叫（速率限制） | **必須**：避免 Callback handler 被外部瓶頸阻塞導致超時重送，見 [guides/22 §佇列與高並發](./22-performance-scaling.md) |
>
> **核心原則**：Callback handler 的職責只有「驗簽 + 落庫 + 回應 `1|OK`」，業務邏輯必須在回應後才處理。

### Callback 驗證程式碼範例

以下提供 CMV-SHA256（AIO）和 AES-JSON（ECPG）兩種協議的最小驗證片段。完整 CheckMacValue 實作見 [guides/13](./13-checkmacvalue.md)，AES 加解密見 [guides/14](./14-aes-encryption.md)。

#### PHP — AIO Callback（CMV-SHA256）

```php
// 接收 AIO Callback（Form POST, application/x-www-form-urlencoded）
$params = $_POST;
$receivedCmv = $params['CheckMacValue'];
unset($params['CheckMacValue']);

// 計算 CheckMacValue（完整實作見 guides/13）
$expectedCmv = generateCheckMacValue($params, $hashKey, $hashIv, 'sha256');

// timing-safe 比較，防止時序攻擊
if (!hash_equals($expectedCmv, $receivedCmv)) {
    echo '0|CheckMacValue Error';
    exit;
}

// ⚠️ RtnCode 為字串 "1"（Form POST 所有值皆為字串）
if ($params['RtnCode'] === '1') {
    // 付款成功，處理訂單（冪等 upsert）
}

echo '1|OK';  // 必須回應，否則 ECPay 持續重送
```

#### Python — AIO Callback（CMV-SHA256）

```python
# 接收 AIO Callback（Form POST）
params = dict(request.form)
received_cmv = params.pop('CheckMacValue', '')

# 計算 CheckMacValue（完整實作見 guides/13）
expected_cmv = generate_check_mac_value(params, hash_key, hash_iv, 'sha256')

# timing-safe 比較
if not hmac.compare_digest(expected_cmv, received_cmv):
    return '0|CheckMacValue Error'

# ⚠️ RtnCode 為字串 "1"（Form POST 所有值皆為字串）
if params['RtnCode'] == '1':
    # 付款成功，處理訂單（冪等 upsert）
    pass

return '1|OK'  # 必須回應
```

#### Node.js — ECPG Callback（AES-JSON）

```javascript
// 接收 ECPG Callback（JSON POST）
const body = req.body;

// 第一層：檢查傳輸狀態
if (body.TransCode !== 1) {
    return res.send('0|Fail');
}

// 第二層：AES 解密取得交易資料（完整實作見 guides/14）
const data = JSON.parse(aesDecrypt(body.Data, hashKey, hashIv));

// ⚠️ AES-JSON 解密後 RtnCode 為整數 1（非字串 "1"）
if (data.RtnCode === 1) {
    // 付款成功，處理訂單（冪等 upsert）
}

res.send('1|OK');  // 必須回應
```

#### Python — ECPG Callback（AES-JSON）

```python
# 接收 ECPG Callback（JSON POST）
body = request.get_json(force=True)

# 第一層：檢查傳輸狀態
if body.get('TransCode') != 1:
    return '0|Fail'

# 第二層：AES 解密取得交易資料（完整實作見 guides/14）
data = json.loads(aes_decrypt(body['Data'], hash_key, hash_iv))

# ⚠️ AES-JSON 解密後 RtnCode 為整數 1（非字串 "1"）
if data['RtnCode'] == 1:
    # 付款成功，處理訂單（冪等 upsert）
    pass

return '1|OK'  # 必須回應
```

> **RtnCode 型別陷阱**
>
> | 協議 | RtnCode 型別 | 正確比較 | 錯誤比較 |
> |------|-------------|---------|---------|
> | CMV-SHA256 (AIO) | 字串 `"1"` | `rtnCode === "1"` | `rtnCode === 1` |
> | CMV-MD5 (國內物流) | 字串 `"1"` | `rtnCode === "1"` | `rtnCode === 1` |
> | AES-JSON (ECPG/發票/物流v2/票證) | 整數 `1` | `rtnCode === 1` | `rtnCode === "1"` |
>
> **原因**：Form POST（CMV 系列）的所有值都是 URL-encoded 字串；AES-JSON 解密後經 `JSON.parse` / `json.loads` 還原為原始型別（整數）。使用嚴格比較（`===` / `is`）時型別不符會導致判斷永遠失敗。

## Callback 總覽表

| 服務 | URL 欄位名 | 觸發時機 | 認證方式 | 必須回應 | 重試機制 |
|------|-----------|---------|---------|---------|---------|
| AIO 金流 | ReturnURL | 付款完成 | CheckMacValue (**SHA256**) | `1\|OK` | 每 5-15 分鐘重送，每日最多 4 次（持續天數有上限，重試停止後需手動補查） |
| AIO 金流 | PaymentInfoURL | ATM/CVS/BARCODE 取號完成 | CheckMacValue (SHA256) | `1\|OK` | 同上 |
| AIO 金流 | PeriodReturnURL | 定期定額每期扣款 | CheckMacValue (SHA256) | `1\|OK` | 同上 |
| AIO 金流 | ReturnURL | BNPL 無卡分期申請結果 | CheckMacValue (SHA256) | `1\|OK` | 同上 |
| AIO 金流 | OrderResultURL | 前端跳轉（非 server-to-server） | CheckMacValue (SHA256) | HTML 頁面 | 不重試 |
| 站內付 2.0 | ReturnURL | 付款完成 | AES 解密 Data | `1\|OK` | 約每 2 小時重試（次數未公開）|
| 站內付 2.0 | OrderResultURL | 前端跳轉（非 server-to-server） | JSON 解析 `ResultData` → AES 解密 `Data` | HTML 頁面 | 不重試（一次性） |
| 信用卡幕後授權 | ReturnURL | 授權結果 | AES 解密 Data | `1\|OK` | 約每 2 小時重試（次數未公開）|
| 非信用卡幕後取號 | ReturnURL | ATM/CVS/BARCODE 付款完成 | AES 解密 Data | `1\|OK` | 每 5-15 分鐘重送，每日最多 4 次 |
| 國內物流 | ServerReplyURL | 物流狀態變更 | CheckMacValue (**MD5**) | `1\|OK` | 約每 2 小時重試（次數未公開）|
| 國內物流（逆物流） | ServerReplyURL | 逆物流狀態變更 | CheckMacValue (**MD5**) | `1\|OK` | 約每 2 小時重試（次數未公開）|
| 國內物流 | ClientReplyURL | 消費者選店結果（前端跳轉） | CheckMacValue (MD5) | HTML 頁面 | 不重試 |
| 全方位物流 | ServerReplyURL | 物流狀態變更 | AES 解密 | AES 加密 JSON | 約每 2 小時重試（次數未公開）|
| 跨境物流 | ServerReplyURL | 物流狀態變更 | AES 解密 | AES 加密 JSON（與全方位物流相同） | 約每 2 小時重試（次數未公開）|
| 電子票證 | UseStatusNotifyURL | 退款/核退通知 | AES 解密 Data + CheckMacValue (SHA256) | AES 加密 JSON + **CheckMacValue**（Data 內 `RtnCode=1`） | 每 5-15 分鐘重送，每日最多 4 次 |
| **直播收款** | ReturnURL | 付款通知 | AES 解密 Data + **ECTicket 式** CheckMacValue (SHA256)(格式與電子票證相同) | `1\|OK`(純文字,與電子票證 AES-JSON 回應不同) | 每 5-15 分鐘重送,每日最多 4 次 |
| B2C 發票（線上折讓） | ReturnURL | 消費者同意折讓 | CheckMacValue (**MD5**) | `1\|OK` | 未公開 |
| 電子發票（其他 API） | — | 通常由 API 主動查詢 | AES 解密 | JSON | — |

> **重試觸發條件**：HTTP 超時、回應非 200 狀態碼、或回應格式不符（如應回 `1|OK` 但回了其他內容）時觸發重試。AIO 的重試次數有上限（每日 4 次），其他服務的重試上限未公開，建議實作冪等處理（見下方 §冪等性處理建議）。

> **到達順序不保證**：ECPay 不保證跨服務 callback 的到達順序（例如金流 callback 可能晚於發票 callback），同一服務的重試也可能亂序到達。你的處理邏輯必須依賴冪等鍵（MerchantTradeNo / AllPayLogisticsID）而非到達順序來判斷狀態。

## 各服務 Callback 重試規則對照表

> **設計原則**：每個 callback handler 必須能正確處理同一 MerchantTradeNo 的多次重試（冪等），回應必須在 **10 秒內**送出（超時觸發重試）。

| 服務 | 重試間隔 | 最大次數 | 觸發條件 | 重試停止後 |
|------|---------|---------|---------|----------|
| **AIO 金流**（ReturnURL / PaymentInfoURL / PeriodReturnURL） | 每 5-15 分鐘 | **每日 4 次**（持續天數有上限）| HTTP 非 200 / 逾時 / 回應非精確 `1\|OK` | 需主動呼叫 QueryTradeInfo 補查狀態 |
| **站內付 2.0**（ReturnURL） | 約每 2 小時 | 未公開 | HTTP 非 200 / 逾時 / 回應非 `1\|OK` | 聯繫綠界客服或主動查詢 |
| **信用卡幕後授權**（ReturnURL） | 約每 2 小時 | 未公開 | 同上 | 同上 |
| **非信用卡幕後取號**（ReturnURL） | 每 5-15 分鐘 | **每日 4 次** | 同上 | 同上 |
| **國內物流**（ServerReplyURL） | 約每 2 小時 | 未公開 | HTTP 非 200 / 逾時 / 回應非 `1\|OK` | 主動查詢物流狀態 |
| **全方位/跨境物流**（ServerReplyURL） | 約每 2 小時 | 未公開 | HTTP 非 200 / 逾時 / 回應非 AES-JSON | 主動查詢物流狀態 |
| **電子票證**（UseStatusNotifyURL） | 每 5-15 分鐘 | **每日 4 次** | HTTP 非 200 / 逾時 / 回應格式錯誤 | 主動查詢票證狀態 |
| **直播收款**（ReturnURL） | 每 5-15 分鐘 | **每日 4 次** | HTTP 非 200 / 逾時 / 回應非 `1\|OK` | 同上 |
| **B2C 發票（線上折讓）** | 未公開 | 未公開 | 未公開 | — |
| **OrderResultURL**（所有服務） | — | **不重試** | 前端跳轉，一次性，不重試 | — |

> **重試停止後應對策略**：AIO 金流重試上限到達後，ECPay 不再主動通知，應設定每日排程呼叫 `QueryTradeInfo` 比對對帳檔，補查遺漏訂單。其他服務重試上限未公開，建議每日對帳（見 [guides/22 §對帳最佳實踐](./22-performance-scaling.md)）。

## Callback 認證方式速查

收到 Callback 時，用以下速查判斷該用哪種驗證方式：

| 你收到的格式 | 有什麼欄位 | 該用哪種驗證 | 參考 |
|-------------|-----------|------------|------|
| Form POST (URL-encoded) | 含 `CheckMacValue` | SHA256（金流）或 MD5（物流） | [guides/13](./13-checkmacvalue.md) |
| JSON POST | 含 `Data`（Base64 字串），**無** `CheckMacValue` | AES 解密 | [guides/14](./14-aes-encryption.md) |
| JSON POST | 含 `Data`（Base64 字串）**且**含 `CheckMacValue` | 先驗 ECTicket 式 CMV（見 [guides/09](./09-ecticket.md) §CheckMacValue 計算），再 AES 解密 | **電子票證**：回應 **AES 加密 JSON + CMV**（Data 內 `{"RtnCode": 1, "RtnMsg": "成功"}`） |
| JSON POST | 含 `Data`（Base64 字串）**且**含 `CheckMacValue` | 同上（ECTicket 式 CMV + AES 解密） | **直播收款**：驗證方式同電子票證，但回應為**純文字 `1\|OK`**（非 AES 加密 JSON） |

> **最常見錯誤**：國內物流的 CheckMacValue 使用 **MD5**（不是 SHA256）。用錯雜湊演算法會導致驗證永遠失敗。
> - 金流 AIO → SHA256
> - 國內物流 → MD5
> - B2C 發票線上折讓（AllowanceByCollegiate）→ **MD5**（發票中唯一帶 CheckMacValue 的 Callback，詳見 [guides/04](./04-invoice-b2c.md)）
> - ECPG / 信用卡幕後授權 / 非信用卡幕後取號 / 發票（其他 API）/ 全方位物流 / 跨境物流 → AES 解密（無 CheckMacValue）
> - 票證 → AES 解密 + CheckMacValue (SHA256)；直播收款 → 同票證驗證方式，但回應 `1|OK`

## AIO ReturnURL — 付款成功通知

**觸發時機**：消費者完成付款後，ECPay 主動 POST 到你的 Server。

**HTTP 方法**：POST（application/x-www-form-urlencoded）

**回傳欄位**：

| 欄位 | 說明 |
|------|------|
| MerchantID | 特店編號 |
| MerchantTradeNo | 特店交易編號 |
| RtnCode | 交易狀態碼（**1=成功**） |
| RtnMsg | 交易訊息 |
| TradeNo | 綠界交易編號 |
| TradeAmt | 交易金額 |
| PaymentDate | 付款時間 |
| PaymentType | 付款方式 |
| PaymentTypeChargeFee | 手續費 |
| TradeDate | 交易日期 |
| SimulatePaid | 是否為模擬付款（0=否, 1=是） |
| CheckMacValue | 檢查碼 |

**處理流程**：

1. 解析 POST 參數
2. 驗證 CheckMacValue（見 [guides/13-checkmacvalue.md](./13-checkmacvalue.md)）
3. 確認 RtnCode=1（付款成功）
4. 確認 SimulatePaid=0（非模擬付款）
5. 更新訂單狀態（使用 upsert 確保冪等性）
6. 回應純字串 `1|OK`

```php
$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',  // ⚠️ 測試帳號；正式環境改用 getenv('ECPAY_HASH_KEY')
    'hashIv'  => 'EkRm7iFT261dpevs',  // ⚠️ 測試帳號；正式環境改用 getenv('ECPAY_HASH_IV')
]);
$checkoutResponse = $factory->create(VerifiedArrayResponse::class);
$result = $checkoutResponse->get($_POST);  // 自動驗證 CheckMacValue

if ($result['RtnCode'] === '1') {
    if ($result['SimulatePaid'] === '0') {
        // 真實付款，處理訂單
    }
}
echo '1|OK';  // 必須回應
```

**ReturnURL 重要限制**：

- 必須回應純字串 `1|OK`
- 不可放在 CDN 後面
- 僅支援 80/443 埠
- 非 ASCII 域名需用 punycode
- TLS 1.2 必須
- 不可含特殊字元（分號、管道、反引號）

## AIO PaymentInfoURL — 取號通知（ATM/CVS/BARCODE）

**觸發時機**：ATM 虛擬帳號、超商代碼、條碼產生後通知。

**HTTP 方法**：POST（application/x-www-form-urlencoded）

> **重要**：取號成功的 RtnCode **不是 1**。

**取號成功 RtnCode 對應**：

| 付款方式 | 取號成功 RtnCode |
|---------|-----------------|
| ATM | `2` |
| CVS | `10100073` |
| BARCODE | `10100073` |

**各付款方式額外欄位**：

| 付款方式 | 額外欄位 |
|---------|---------|
| ATM | BankCode（銀行代碼）, vAccount（虛擬帳號）, ExpireDate（繳費期限） |
| CVS | PaymentNo（繳費代碼）, ExpireDate |
| BARCODE | Barcode1, Barcode2, Barcode3, ExpireDate |

**共用欄位**（與 ReturnURL 相同的基礎欄位）：

| 欄位 | 說明 |
|------|------|
| MerchantID | 特店編號 |
| MerchantTradeNo | 特店交易編號 |
| RtnCode | 取號狀態碼（見上表） |
| RtnMsg | 回應訊息 |
| TradeNo | 綠界交易編號 |
| TradeAmt | 交易金額 |
| TradeDate | 交易日期 |
| PaymentType | 付款方式 |
| CheckMacValue | 檢查碼 |

**處理流程**：

1. 解析 POST 參數
2. 驗證 CheckMacValue
3. 根據付款方式檢查 RtnCode（ATM=2, CVS/BARCODE=10100073）
4. 儲存繳費資訊（帳號/代碼/條碼）
5. 通知消費者繳費資訊（Email/推播等）
6. 回應純字串 `1|OK`

> **PaymentInfoURL vs ReturnURL**：ATM/CVS/BARCODE 是非同步付款流程。`PaymentInfoURL` 接收取號結果，`ReturnURL` 接收實際付款結果（RtnCode=1）。兩者都會被呼叫。

## AIO PeriodReturnURL — 定期定額通知

**觸發時機**：每期自動扣款完成後通知。

**HTTP 方法**：POST（application/x-www-form-urlencoded）

**回傳欄位**：

| 欄位 | 說明 |
|------|------|
| MerchantID | 特店編號 |
| MerchantTradeNo | 特店交易編號 |
| RtnCode | 交易狀態碼（1=成功） |
| RtnMsg | 交易訊息 |
| Amount | 本次授權金額 |
| Gwsr | 授權交易單號 |
| AuthCode | 授權碼 |
| ProcessDate | 處理時間（yyyy/MM/dd HH:mm:ss） |
| PeriodType | 週期類型（D=天, M=月, Y=年） |
| Frequency | 執行頻率 |
| ExecTimes | 總執行次數 |
| FirstAuthAmount | 初次授權金額 |
| TotalSuccessTimes | 已成功扣款次數 |
| SimulatePaid | 是否為模擬付款（0=否, 1=是） |
| CheckMacValue | 檢查碼 |

> 完整回傳欄位見 references/Payment/全方位金流API技術文件.md §定期定額付款結果通知。

**處理流程**：

1. 解析 POST 參數
2. 驗證 CheckMacValue
3. 確認 RtnCode=1（扣款成功）
4. 更新訂閱狀態與已扣款次數
5. 判斷 TotalSuccessTimes 是否等於 ExecTimes（訂閱結束）
6. 回應純字串 `1|OK`

**建立定期定額訂單的關鍵參數**：

| 參數 | 說明 |
|------|------|
| PeriodAmount | 每期金額 |
| PeriodType | 週期類型（D=天, M=月, Y=年） |
| Frequency | 每 N 個週期執行一次 |
| ExecTimes | 共執行幾次 |
| PeriodReturnURL | 每期扣款通知 URL |

## AIO BNPL 無卡分期申請結果通知

**觸發時機**：消費者完成 BNPL（裕富/中租）無卡分期審核後，綠界 POST 通知。

**HTTP 方法**：POST（application/x-www-form-urlencoded）

**回傳格式**：與一般 AIO Callback 相同（URL-encoded POST），需驗證 CheckMacValue。

**關鍵欄位**：

| 欄位 | 說明 |
|------|------|
| RtnCode | 交易狀態碼（**2=申請中**，非 1） |
| BNPLTradeNo | 無卡分期申請交易編號 |
| BNPLInstallment | 分期期數 |
| CheckMacValue | 檢查碼 |

> **注意**：BNPL 申請結果的 `RtnCode=2` 代表「申請中」，與 ATM 取號的 `RtnCode=2` 含義不同。
> 完整回傳欄位見 references/Payment/全方位金流API技術文件.md §無卡分期申請結果通知。

**處理流程**：

1. 解析 POST 參數
2. 驗證 CheckMacValue
3. 確認 RtnCode=2（申請中）
4. 記錄 BNPLTradeNo 與分期期數
5. 回應純字串 `1|OK`

## 站內付 2.0 ReturnURL — 付款結果通知（Server-to-Server）

**觸發時機**：站內付交易完成後，綠界以 Server POST 方式通知特店。

**HTTP 方法**：POST（application/json）

> ⚠️ **ReturnURL 與 OrderResultURL 是不同的 Callback**（官方規格 9058.md / 15076.md）：
> - **ReturnURL**：Server-to-Server POST（`application/json`），JSON body 直接包含三層結構，商家回應 `1|OK`，未正確回應會觸發重試。
> - **OrderResultURL**：瀏覽器端 Form POST（`application/x-www-form-urlencoded`），資料放在 `ResultData` 表單欄位（**JSON 字串**，含 `{TransCode, Data(AES)}`），一次性跳轉，不重試。
> - 兩者沒有固定先後順序。

**ReturnURL 外層 JSON 結構**：

```json
{
    "MerchantID": "3002607",
    "RpHeader": { "Timestamp": 1234567890 },
    "TransCode": 1,
    "TransMsg": "Success",
    "Data": "AES加密後的Base64字串"
}
```

**外層欄位**：

| 欄位 | 說明 |
|------|------|
| MerchantID | 特店編號 |
| RpHeader.Timestamp | 回應時間戳 |
| TransCode | 傳輸狀態碼（1=成功） |
| TransMsg | 傳輸訊息 |
| Data | AES 加密的交易資料（Base64 字串） |

**Data 解密後欄位**：

| 欄位 | 說明 |
|------|------|
| RtnCode | 交易狀態碼（1=成功） |
| RtnMsg | 交易訊息 |
| MerchantID | 特店編號 |
| MerchantTradeNo | 特店交易編號 |
| TradeNo | 綠界交易編號 |
| TradeAmt | 交易金額 |
| PaymentDate | 付款時間 |
| PaymentType | 付款方式 |
| Token | 付款 Token |
| TokenExpireDate | Token 到期日 |

**處理流程**：

1. 解析 JSON body（`json_decode(file_get_contents('php://input'))`）
2. 檢查外層 TransCode（1=傳輸成功）
3. AES 解密 Data 欄位（見 [guides/14-aes-encryption.md](./14-aes-encryption.md)）
4. 檢查內層 RtnCode（1=交易成功）
5. 更新訂單狀態
6. 回應純字串 `1|OK`

```php
$aesService = $factory->create(AesService::class);

// ReturnURL 是 JSON POST，需從 php://input 讀取
$jsonBody = json_decode(file_get_contents('php://input'), true);

// 先檢查 TransCode 確認 API 是否成功
$transCode = $jsonBody['TransCode'] ?? null;
if ($transCode != 1) {
    error_log('ECPay TransCode Error: ' . ($jsonBody['TransMsg'] ?? 'unknown'));
}

// 解密 Data 取得交易細節
$decryptedData = $aesService->decrypt($jsonBody['Data']);
// $decryptedData 包含：RtnCode, RtnMsg, MerchantID, Token, TokenExpireDate 等

// 業務邏輯處理...

// 回應 1|OK（官方規格 9058.md）
echo '1|OK';
```

> **兩層檢查**：站內付 2.0 需要檢查兩層狀態碼。TransCode 代表「傳輸是否成功」，RtnCode 代表「交易是否成功」。兩者都為 1 才算完全成功。

### OrderResultURL — 前端跳轉（非 Server-to-Server）

**HTTP 方法**：POST（application/x-www-form-urlencoded）

OrderResultURL 是瀏覽器端的一次性跳轉，綠界將 `ResultData`（**JSON 字串**，內含外層 `{TransCode, Data}`，其中 `Data` 為 AES 加密）放在表單欄位中 POST 到特店頁面。特店需先 JSON 解析取外層結構，確認 `TransCode == 1`，再 AES 解密 `Data` 欄位，最後顯示付款結果頁面。

```php
// ⚠️ OrderResultURL 是 Form POST，ResultData 是 JSON 字串（含外層 {TransCode, Data}）
$resultDataStr = $_POST['ResultData'] ?? '';
$outer = json_decode($resultDataStr, true);               // Step 1：JSON 解析取外層 {TransCode, Data}
if (($outer['TransCode'] ?? 0) != 1) {
    echo '資料傳輸錯誤'; exit;
}
$innerJson = $aesService->decrypt($outer['Data']);        // Step 2：AES 解密 Data 欄位
// 顯示付款結果頁面給消費者
```

### 站內付 2.0 雙 Callback 完整範例（Python / Go）

以下提供非 PHP 語言的完整雙 Callback 接收範例。

#### Python（Flask）

```python
# pip install flask pycryptodome requests
import json, hmac
from flask import Flask, request
from Crypto.Cipher import AES
from Crypto.Util.Padding import unpad
import base64, urllib.parse

app = Flask(__name__)

HASH_KEY = 'pwFHCqoQZGmho4w6'  # ⚠️ 測試帳號；正式環境改用 os.getenv('ECPAY_HASH_KEY')
HASH_IV  = 'EkRm7iFT261dpevs'  # ⚠️ 測試帳號；正式環境改用 os.getenv('ECPAY_HASH_IV')

def aes_decrypt(data: str) -> dict:
    raw = base64.b64decode(data)
    cipher = AES.new(HASH_KEY.encode(), AES.MODE_CBC, HASH_IV.encode())
    plain = unpad(cipher.decrypt(raw), 16).decode('utf-8')
    return json.loads(urllib.parse.unquote_plus(plain))

@app.route('/ecpg/return', methods=['POST'])
def ecpg_return_url():
    """ReturnURL — Server-to-Server JSON POST"""
    body = request.get_json(force=True)

    # 第一層：傳輸狀態
    if body.get('TransCode') != 1:
        return '0|Fail', 200

    # 第二層：業務狀態
    inner = aes_decrypt(body['Data'])
    if inner.get('RtnCode') == 1:  # 整數比較（AES-JSON 解密後為 int）
        trade_no = inner['MerchantTradeNo']
        # 冪等處理：upsert 訂單狀態
        # db.upsert('orders', {'trade_no': trade_no, 'status': 'paid'})
        pass

    return '1|OK', 200  # 純文字，不含引號或換行

@app.route('/ecpg/result', methods=['POST'])
def ecpg_order_result_url():
    """OrderResultURL — 前端 Form POST，讀 ResultData 表單欄位"""
    result_data = request.form.get('ResultData', '')
    if not result_data:
        return '<h1>資料缺失</h1>', 400

    # ⚠️ ResultData 是 JSON 字串，需先 json.loads 取外層，再 AES 解密 Data 欄位
    outer = json.loads(result_data)      # ← Step 1：JSON 解析外層 {TransCode, Data}
    if outer.get('TransCode') != 1:
        return '<h1>資料傳輸錯誤</h1>', 200
    inner = aes_decrypt(outer['Data'])   # ← Step 2：AES 解密 Data 欄位（含 RtnCode 等）
    success = inner.get('RtnCode') == 1  # 整數比較

    # 顯示結果頁面（瀏覽器端跳轉，不需回應 1|OK）
    return f'<h1>付款{"成功" if success else "失敗"}</h1>', 200
```

#### Go（net/http）

```go
package main

import (
    "crypto/aes"
    "crypto/cipher"
    "encoding/base64"
    "encoding/json"
    "fmt"
    "io"
    "net/http"
    "net/url"
)

const (
    hashKey = "pwFHCqoQZGmho4w6" // ⚠️ 測試帳號；正式環境改用 os.Getenv("ECPAY_HASH_KEY")
    hashIV  = "EkRm7iFT261dpevs" // ⚠️ 測試帳號；正式環境改用 os.Getenv("ECPAY_HASH_IV")
)

func aesDecrypt(data string) (map[string]interface{}, error) {
    raw, err := base64.StdEncoding.DecodeString(data)
    if err != nil {
        return nil, err
    }
    block, _ := aes.NewCipher([]byte(hashKey)[:16])
    mode := cipher.NewCBCDecrypter(block, []byte(hashIV)[:16])
    mode.CryptBlocks(raw, raw)
    // 移除 PKCS7 padding（嚴謹驗證，避免誤移除合法資料）
    pad := int(raw[len(raw)-1])
    if pad == 0 || pad > aes.BlockSize {
        return nil, fmt.Errorf("invalid padding")
    }
    plain, _ := url.QueryUnescape(string(raw[:len(raw)-pad]))
    var result map[string]interface{}
    json.Unmarshal([]byte(plain), &result)
    return result, nil
}

func ecpgReturnURL(w http.ResponseWriter, r *http.Request) {
    // ReturnURL — application/json POST
    body, _ := io.ReadAll(r.Body)
    var outer map[string]interface{}
    json.Unmarshal(body, &outer)

    transCode, _ := outer["TransCode"].(float64)
    if transCode != 1 {
        w.WriteHeader(http.StatusOK)
        w.Write([]byte("0|Fail"))
        return
    }

    inner, _ := aesDecrypt(outer["Data"].(string))
    if rtnCode, ok := inner["RtnCode"].(float64); ok && rtnCode == 1 {
        tradeNo := inner["MerchantTradeNo"].(string)
        _ = tradeNo // 冪等處理：upsert 訂單狀態
    }

    w.Header().Set("Content-Type", "text/plain")
    w.WriteHeader(http.StatusOK)
    w.Write([]byte("1|OK"))
}

func ecpgOrderResultURL(w http.ResponseWriter, r *http.Request) {
    // OrderResultURL — application/x-www-form-urlencoded，讀 ResultData
    r.ParseForm()
    resultData := r.FormValue("ResultData")
    if resultData == "" {
        http.Error(w, "missing ResultData", http.StatusBadRequest)
        return
    }

    // ⚠️ ResultData 是 JSON 字串，需先 JSON 解析取外層，再 AES 解密 Data 欄位
    var outer map[string]interface{}
    if err := json.Unmarshal([]byte(resultData), &outer); err != nil {  // Step 1：JSON 解析
        w.Write([]byte("<h1>資料解析錯誤</h1>"))
        return
    }
    transCode, _ := outer["TransCode"].(float64)
    if transCode != 1 {
        w.Write([]byte("<h1>資料傳輸錯誤</h1>"))
        return
    }
    inner, _ := aesDecrypt(outer["Data"].(string))  // Step 2：AES 解密 Data 欄位

    success := false
    if rtnCode, ok := inner["RtnCode"].(float64); ok && rtnCode == 1 {
        success = true
    }
    if success {
        w.Write([]byte("<h1>付款成功</h1>"))
    } else {
        w.Write([]byte("<h1>付款失敗</h1>"))
    }
}

> **兩個端點的關鍵差異**：
> - `ReturnURL`：讀 `request.get_json()` / `io.ReadAll(r.Body)` → 回應純文字 `1|OK`
> - `OrderResultURL`：讀 `request.form['ResultData']` / `r.FormValue("ResultData")` → 回應 HTML 頁面（不回應 `1|OK`）
>
> 完整 5 步驟串接流程（含步驟 0 環境預檢）見 [guides/02](./02-payment-ecpg.md)。

## 物流 ServerReplyURL — 物流狀態變更

**觸發時機**：物流狀態每次變更時（建單、出貨、配達、退貨等）。

### 國內物流（CMV-MD5 — CheckMacValue MD5）

**HTTP 方法**：POST（application/x-www-form-urlencoded）

**回傳欄位**：

| 欄位 | 說明 |
|------|------|
| AllPayLogisticsID | 綠界物流交易編號 |
| MerchantTradeNo | 特店交易編號 |
| RtnCode | 物流狀態碼（1=成功） |
| RtnMsg | 狀態訊息 |
| LogisticsType | 物流類型（CVS=超商, HOME=宅配） |
| LogisticsSubType | 物流子類型（FAMI/UNIMART/HILIFE/OKMART/TCAT/POST） |
| CheckMacValue | 檢查碼（MD5） |

```php
use Ecpay\Sdk\Response\VerifiedArrayResponse;
$factory = new Factory([
    'hashKey'    => '5294y06JbISpM5x9',  // ⚠️ 測試帳號；正式環境改用 getenv('ECPAY_HASH_KEY')
    'hashIv'     => 'v77hoKGq4kWxNNIS',  // ⚠️ 測試帳號；正式環境改用 getenv('ECPAY_HASH_IV')
    'hashMethod' => 'md5',  // 重要：國內物流用 MD5
]);
$verifiedResponse = $factory->create(VerifiedArrayResponse::class);
$result = $verifiedResponse->get($_POST);
// $result 包含：AllPayLogisticsID, MerchantTradeNo, RtnCode, RtnMsg, LogisticsType, LogisticsSubType 等
echo '1|OK';
```

> **注意**：國內物流的 CheckMacValue 使用 **MD5**（不是 SHA256）。與 AIO 金流的加密方式不同！

### 全方位物流（AES-JSON — AES 加密 JSON）

**HTTP 方法**：POST（application/json）

全方位物流的通知是 AES 加密的 JSON（不是 Form POST）。回應也需要 AES 加密。

**接收與回應範例**：

```php
use Ecpay\Sdk\Response\AesJsonResponse as AesParser;
use Ecpay\Sdk\Request\AesRequest as AesGenerater;

// 接收通知
$aesParser = $factory->create(AesParser::class);
$parsedRequest = $aesParser->get(file_get_contents('php://input'));

// 回應（也需要 AES 加密）
$aesGenerater = $factory->create(AesGenerater::class);
$data = [
    'RtnCode' => '1',
    'RtnMsg'  => '',
];
$responseData = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time()],
    'TransCode'  => '1',
    'TransMsg'   => '',
    'Data'       => $data,
];
$response = $aesGenerater->get($responseData);
echo $response;
```

> **關鍵差異**：全方位物流的 callback 回應也需要 AES 加密成 JSON 格式，而非純字串 `1|OK`。

### 全方位物流 / 跨境物流 Callback 回應格式

收到全方位物流或跨境物流的 callback 時，需要 **AES 解密**後處理，並回應 **AES 加密的 JSON**：

**收到的 callback body**（JSON POST）：
```json
{
  "MerchantID": "2000132",
  "RpHeader": { "Timestamp": 1234567890 },
  "TransCode": 1,
  "TransMsg": "Success",
  "Data": "AES加密的Base64字串（解密後為物流狀態資料）"
}
```

**你必須回應的格式**（AES 加密 JSON）：
```json
{
  "MerchantID": "2000132",
  "RqHeader": { "Timestamp": 1234567890 },
  "TransCode": "1",
  "TransMsg": "",
  "Data": "AES加密({"RtnCode": 1, "RtnMsg": "OK"})"
}
```

> **重要**：全方位/跨境物流的 callback 回應**不是** `1|OK`，而是 AES 加密的 JSON 三層結構。
> 這與國內物流（回 `1|OK`）和站內付 2.0（回 `1|OK`）都不同。
> AES 加解密實作見 [guides/14](./14-aes-encryption.md)。

**物流狀態碼參考**：`scripts/SDK_PHP/example/Logistics/logistics_status.xlsx` 和 `logistics_history.xlsx`

## 逆物流 ServerReplyURL — 逆物流狀態通知

**觸發時機**：退貨物流狀態變更時，綠界 POST 通知到逆物流建單時設定的 `ServerReplyURL`。

**HTTP 方法**：POST（application/x-www-form-urlencoded）

**回傳欄位**：

| 欄位 | 說明 |
|------|------|
| MerchantID | 特店編號 |
| RtnMerchantTradeNo | 特店逆物流交易編號 |
| RtnCode | 物流狀態碼 |
| RtnMsg | 物流狀態說明 |
| AllPayLogisticsID | 綠界物流交易編號 |
| GoodsAmount | 商品金額（用於遺失賠償） |
| UpdateStatusDate | 狀態更新時間 |
| BookingNote | 托運單號（僅宅配） |
| CheckMacValue | 檢查碼（MD5） |

> **注意**：逆物流的 `LogisticsStatus` 為逆物流專用狀態碼，與正物流不同。
> 完整欄位與狀態碼見 references/Logistics/物流整合API技術文件.md §逆物流狀態通知。

**處理流程**：

1. 解析 POST 參數
2. 驗證 CheckMacValue（MD5）
3. 根據 RtnCode 更新退貨物流狀態
4. 回應純字串 `1|OK`

## 物流 ClientReplyURL — 消費者選店結果

**觸發時機**：消費者在 ECPay 地圖選擇超商門市後，前端跳轉回來。

**注意**：這是前端跳轉，非 server-to-server callback。

### 國內物流（電子地圖選店）

```php
use Ecpay\Sdk\Response\ArrayResponse;
$arrayResponse = $factory->create(ArrayResponse::class);
$result = $arrayResponse->get($_POST);
// $result 包含：CVSStoreID, CVSStoreName, CVSAddress, CVSTelephone 等
```

**回傳欄位**：

| 欄位 | 說明 |
|------|------|
| CVSStoreID | 門市代碼 |
| CVSStoreName | 門市名稱 |
| CVSAddress | 門市地址 |
| CVSTelephone | 門市電話 |
| MerchantTradeNo | 特店交易編號 |

### 全方位物流（RWD 物流選擇頁）

消費者選擇完物流後，ClientReplyURL 收到 AES 加密的結果：

```php
use Ecpay\Sdk\Response\AesJsonResponse;
$aesJsonResponse = $factory->create(AesJsonResponse::class);
$result = $aesJsonResponse->get($_POST['ResultData']);
// $result 包含 TempLogisticsID
```

**回傳欄位**：

| 欄位 | 說明 |
|------|------|
| TempLogisticsID | 暫存物流 ID（用於後續 UpdateTempTrade / CreateByTempTrade） |

## 多語言 Webhook Handler 範例

### Node.js — 生產等級 ReturnURL Handler

```javascript
const express = require('express');
const crypto = require('crypto');
const app = express();
app.use(express.urlencoded({ extended: true }));

// CheckMacValue 計算（完整實作見 guides/13）
function ecpayUrlEncode(source) {
  let encoded = encodeURIComponent(source).replace(/%20/g, '+').replace(/~/g, '%7e').replace(/'/g, '%27');
  encoded = encoded.toLowerCase();
  const replacements = { '%2d': '-', '%5f': '_', '%2e': '.', '%21': '!', '%2a': '*', '%28': '(', '%29': ')' };
  for (const [old, char] of Object.entries(replacements)) {
    encoded = encoded.split(old).join(char);
  }
  return encoded;
}

function generateCheckMacValue(params, hashKey, hashIv) {
  const filtered = Object.entries(params).filter(([k]) => k !== 'CheckMacValue');
  const sorted = filtered.sort((a, b) => a[0].toLowerCase().localeCompare(b[0].toLowerCase()));
  const paramStr = sorted.map(([k, v]) => `${k}=${v}`).join('&');
  const raw = `HashKey=${hashKey}&${paramStr}&HashIV=${hashIv}`;
  return crypto.createHash('sha256').update(ecpayUrlEncode(raw), 'utf8').digest('hex').toUpperCase();
}

app.post('/ecpay/notify', (req, res) => {
  const HASH_KEY = process.env.ECPAY_HASH_KEY;
  const HASH_IV = process.env.ECPAY_HASH_IV;
  const MY_MERCHANT_ID = process.env.ECPAY_MERCHANT_ID;

  // 1. 驗證 CheckMacValue
  const cmv = generateCheckMacValue(req.body, HASH_KEY, HASH_IV);
  const received = Buffer.from(req.body.CheckMacValue || '');
  const expected = Buffer.from(cmv);
  if (received.length !== expected.length || !crypto.timingSafeEqual(expected, received)) {
    console.error('CheckMacValue 驗證失敗');
    return res.send('0|CheckMacValue Error');
  }

  // 2. 驗證 MerchantID
  if (req.body.MerchantID !== MY_MERCHANT_ID) {
    console.error('MerchantID 不符');
    return res.send('0|MerchantID Error');
  }

  // 3. 檢查 SimulatePaid（正式環境拒絕模擬付款）
  if (process.env.NODE_ENV === 'production' && req.body.SimulatePaid === '1') {
    console.warn('正式環境收到模擬付款，忽略');
    return res.send('1|OK');
  }

  // 4. 冪等性處理（upsert）
  if (req.body.RtnCode === '1') {
    // INSERT ... ON CONFLICT DO NOTHING（見上方冪等性 SQL）
    // 比對金額與本地訂單記錄
    console.log(`付款成功: ${req.body.MerchantTradeNo}, 金額: ${req.body.TradeAmt}`);
  }

  // 5. 必須回應
  res.send('1|OK');
});
```

### Python — 生產等級 ReturnURL Handler

```python
from fastapi import FastAPI, Request
from fastapi.responses import PlainTextResponse
import hashlib, urllib.parse, hmac, os

app = FastAPI()

HASH_KEY = os.environ['ECPAY_HASH_KEY']
HASH_IV = os.environ['ECPAY_HASH_IV']
MY_MERCHANT_ID = os.environ['ECPAY_MERCHANT_ID']

def ecpay_url_encode(source: str) -> str:
    encoded = urllib.parse.quote_plus(source).replace('~', '%7e').lower()
    for old, new in {'%2d': '-', '%5f': '_', '%2e': '.', '%21': '!', '%2a': '*', '%28': '(', '%29': ')'}.items():
        encoded = encoded.replace(old, new)
    return encoded

def generate_cmv(params: dict) -> str:
    filtered = {k: v for k, v in params.items() if k != 'CheckMacValue'}
    sorted_params = sorted(filtered.items(), key=lambda x: x[0].lower())
    param_str = '&'.join(f'{k}={v}' for k, v in sorted_params)
    raw = f"HashKey={HASH_KEY}&{param_str}&HashIV={HASH_IV}"
    return hashlib.sha256(ecpay_url_encode(raw).encode('utf-8')).hexdigest().upper()

@app.post('/ecpay/notify')
async def notify(request: Request):
    form = dict(await request.form())

    # 1. 驗證 CheckMacValue（timing-safe）
    expected = generate_cmv(form)
    if not hmac.compare_digest(expected, form.get('CheckMacValue', '')):
        return PlainTextResponse('0|CheckMacValue Error')

    # 2. 驗證 MerchantID
    if form.get('MerchantID') != MY_MERCHANT_ID:
        return PlainTextResponse('0|MerchantID Error')

    # 3. 檢查 SimulatePaid（正式環境拒絕模擬付款）
    if os.environ.get('ENV') == 'production' and form.get('SimulatePaid') == '1':
        return PlainTextResponse('1|OK')

    # 4. 冪等性處理 + 金額比對
    if form.get('RtnCode') == '1':
        # INSERT ... ON CONFLICT DO NOTHING
        # 比對 TradeAmt 與本地訂單金額
        pass

    return PlainTextResponse('1|OK')
```

> **安全清單**（上方範例已包含）：
> 1. CheckMacValue 驗證（timing-safe 比較）
> 2. MerchantID 驗證（確認是自己的訂單）
> 3. SimulatePaid 檢查（正式環境拒絕模擬付款）
> 4. 冪等性（upsert 防重複處理）
> 5. 金額比對（防止金額被竄改）
> 6. HashKey/HashIV 從環境變數讀取（禁止硬編碼）

## Callback 安全必做清單

### 1. 驗證來源

| 認證模式 | 驗證方式 | 適用服務 |
|---------|---------|---------|
| CMV-SHA256 | 計算 CheckMacValue（SHA256）並以 **timing-safe** 函式比對（見 [guides/13](./13-checkmacvalue.md)）| AIO 金流 |
| AES-JSON | AES 解密成功即可視為來自 ECPay | 站內付 2.0、全方位物流、電子發票（其他 API） |
| CMV-MD5 | 計算 CheckMacValue（MD5）並以 **timing-safe** 函式比對（見 [guides/13](./13-checkmacvalue.md)）| 國內物流、B2C 發票線上折讓（AllowanceByCollegiate） |

### 2. HTTPS 必須

Callback URL 必須使用 HTTPS（TLS 1.2+）。

### 3. IP 白名單（選用）

ECPay 的 callback 來源 IP 範圍可至特店後台查詢，可作為額外的安全防線。

### 4. 防重放攻擊

記錄已處理的 MerchantTradeNo / AllPayLogisticsID，避免重複處理。

> **時間窗口建議**：對 AES-JSON 服務（ECPG/發票/物流 v2），可驗證解密後的 `RpHeader.Timestamp` 與伺服器時間差距在 ±5 分鐘內，超出則拒絕。對 CMV-SHA256（AIO），檢查 `MerchantTradeDate` 時間差距作為輔助驗證。

### 5. 超時控制

Callback handler 必須在 10 秒內回應，否則 ECPay 視為失敗。

### 6. 物流 ServerReplyURL 安全清單

> 來源：guides/06-logistics-domestic.md 及 guides/07-logistics-allinone.md

1. 驗證 MerchantID 為自己的
2. 比對物流單號與訂單記錄
3. 防重複處理（記錄已處理的 AllPayLogisticsID）
4. 異常時仍回應 `1|OK`（避免重送風暴）
5. 記錄完整日誌（遮蔽 HashKey/HashIV）

## 冪等性實作建議

Callback 可能因 ECPay 重試而重複到達。你的處理邏輯必須具備冪等性——重複處理同一筆通知不應產生副作用。

### 冪等鍵設計

使用 `MerchantTradeNo`（金流）或 `AllPayLogisticsID`（物流）作為冪等鍵。

### SQL Upsert 範例

```sql
-- 金流 Callback 冪等處理
INSERT INTO payment_notifications (merchant_trade_no, rtn_code, trade_amt, payment_date, raw_data)
VALUES ($1, $2, $3, $4, $5)
ON CONFLICT (merchant_trade_no) DO UPDATE SET
  rtn_code = EXCLUDED.rtn_code,
  updated_at = NOW()
WHERE payment_notifications.rtn_code != '1';  -- 已成功的不覆蓋
```

```sql
-- 物流 callback 冪等性
-- 物流狀態會多次變更，用 (allpay_logistics_id, rtn_code) 組合做 PRIMARY KEY
INSERT INTO logistics_callbacks (allpay_logistics_id, rtn_code, merchant_trade_no, logistics_type, logistics_sub_type, raw_payload)
VALUES ($1, $2, $3, $4, $5, $6)
ON CONFLICT (allpay_logistics_id, rtn_code) DO NOTHING;
```

> 上方範例為 PostgreSQL 語法（`$1` 佔位符 + `ON CONFLICT`）。其他資料庫等價寫法：

#### MySQL 等價寫法

```sql
INSERT INTO payment_notifications (merchant_trade_no, status, received_at)
VALUES ('MN20240301001', 'paid', NOW())
ON DUPLICATE KEY UPDATE status = VALUES(status);
```

#### SQLite 等價寫法

```sql
INSERT OR IGNORE INTO payment_notifications (merchant_trade_no, status, received_at)
VALUES ('MN20240301001', 'paid', datetime('now'));
```

### Node.js 冪等 Callback Handler

```javascript
app.post('/ecpay/notify', async (req, res) => {
  // 1. 驗證 CheckMacValue
  if (!verifyCheckMacValue(req.body)) {
    return res.status(400).send('Invalid CheckMacValue');
  }

  // 2. 冪等 Upsert（防重複）
  const result = await db.query(
    `INSERT INTO notifications (trade_no, status) VALUES ($1, $2)
     ON CONFLICT (trade_no) DO NOTHING RETURNING *`,
    [req.body.MerchantTradeNo, req.body.RtnCode]
  );

  // 3. 僅新插入時處理業務邏輯
  if (result.rowCount > 0) {
    await processOrder(req.body);
  }

  // 4. 立即回應（無論是否已處理過）
  res.send('1|OK');
});
```

### 設計原則

1. **先存後處理**：收到 Callback 立即存入 DB，再做業務邏輯
2. **Upsert 而非 Insert**：用 `ON CONFLICT` 防止重複插入
3. **已成功不覆蓋**：已標記為成功的交易不應被後續 Callback 覆蓋
4. **永遠回應**：無論是否已處理過，都回應 `1|OK`，否則 ECPay 會持續重送

> 💡 **資料庫併發控制**：Callback 可能與使用者的 OrderResultURL 導轉同時到達。建議使用資料庫層級的 upsert（INSERT ... ON CONFLICT UPDATE）而非應用層 check-then-update，避免 race condition 導致訂單狀態不一致。

## 重試機制說明

ECPay 的 callback 重試行為：

| 服務 | 重試頻率 | 每日次數 | 持續天數 |
|------|---------|---------|---------|
| AIO 金流 | 每 5-15 分鐘 | 最多 4 次 | 持續數天 |
| 站內付 2.0（ReturnURL） | 約每 2 小時 | 次數未公開 | 持續數天 |
| 信用卡幕後授權 | 約每 2 小時 | 次數未公開 | 持續數天 |
| 非信用卡幕後取號 | 每 5-15 分鐘 | 最多 4 次 | 持續數天 |
| 國內物流 | 約每 2 小時 | 次數未公開 | 持續數天 |
| 全方位物流 | 約每 2 小時 | 次數未公開 | 持續數天 |
| 跨境物流 | 約每 2 小時 | 次數未公開 | 持續數天 |
| 電子票證 | 每 5-15 分鐘 | 最多 4 次 | 持續數天 |
| 直播收款 | 每 5-15 分鐘 | 最多 4 次 | 持續數天 |
| B2C 發票（AllowanceByCollegiate） | 未公開 | 未公開 | 未公開 |

**重試觸發條件**：

- 你的 server 未回應正確格式（`1|OK` 或對應 JSON）
- HTTP 回應碼非 200
- 連線逾時（超過 10 秒）

**建議**：同時實作主動查詢（QueryTradeInfo）作為補充機制，不要完全依賴 callback。

---

## Callback 冪等性實作範例

> 綠界 Callback 可能因網路異常重送最多 4 次。以下模式確保相同交易不被重複處理。

### 核心原則

以 `MerchantTradeNo` 為主鍵，使用 upsert（而非 insert），確保同一交易多次 callback 只處理一次。

### PHP + MySQL

```php
// ⚠️ 先驗證 CheckMacValue / TransCode，再進行資料庫操作
// 假設 $data 為 AES 解密後的內層 JSON

$merchantTradeNo = $data['MerchantTradeNo'] ?? '';
$rtnCode = $data['RtnCode'] ?? 0;

if (empty($merchantTradeNo)) {
    echo '1|OK'; exit;
}

// 冪等 upsert：已存在則更新，不存在則插入
$stmt = $pdo->prepare("
    INSERT INTO orders (merchant_trade_no, status, ecpay_trade_no, updated_at)
    VALUES (:trade_no, :status, :ecpay_no, NOW())
    ON DUPLICATE KEY UPDATE
        status = VALUES(status),
        ecpay_trade_no = VALUES(ecpay_trade_no),
        updated_at = NOW()
");
$stmt->execute([
    ':trade_no' => $merchantTradeNo,
    ':status'   => ($rtnCode === 1) ? 'paid' : 'failed',
    ':ecpay_no' => $data['TradeNo'] ?? '',
]);
echo '1|OK';
```

### Node.js + PostgreSQL

```typescript
// Express handler（已通過 TransCode/RtnCode 驗證）
app.post('/ecpay/notify', express.json(), async (req, res) => {
    const body = req.body;
    if (body.TransCode !== 1) return res.type('text').send('1|OK');

    const data = aesDecrypt(body.Data);
    const tradeNo = data.OrderInfo?.MerchantTradeNo;
    if (!tradeNo) return res.type('text').send('1|OK');

    // PostgreSQL upsert（ON CONFLICT DO UPDATE）
    await pool.query(`
        INSERT INTO orders (merchant_trade_no, status, ecpay_trade_no, updated_at)
        VALUES ($1, $2, $3, NOW())
        ON CONFLICT (merchant_trade_no)
        DO UPDATE SET
            status = EXCLUDED.status,
            ecpay_trade_no = EXCLUDED.ecpay_trade_no,
            updated_at = NOW()
        WHERE orders.status != 'paid'  -- 已付款不降級
    `, [tradeNo, data.RtnCode === 1 ? 'paid' : 'failed', data.OrderInfo?.TradeNo || '']);

    res.status(200).type('text').send('1|OK');
});
```

### Python + SQLAlchemy

```python
from sqlalchemy.dialects.postgresql import insert as pg_insert

@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    body = request.get_json()
    if body.get('TransCode') != 1:
        return '1|OK', 200

    data = aes_decrypt(body['Data'])
    trade_no = data.get('OrderInfo', {}).get('MerchantTradeNo')
    if not trade_no:
        return '1|OK', 200

    # PostgreSQL upsert
    stmt = pg_insert(Order).values(
        merchant_trade_no=trade_no,
        status='paid' if data.get('RtnCode') == 1 else 'failed',
        ecpay_trade_no=data.get('OrderInfo', {}).get('TradeNo', ''),
    )
    stmt = stmt.on_conflict_do_update(
        index_elements=['merchant_trade_no'],
        set_={'status': stmt.excluded.status,
              'ecpay_trade_no': stmt.excluded.ecpay_trade_no}
    )
    db.session.execute(stmt)
    db.session.commit()
    return '1|OK', 200
```

> **注意事項**
> - MySQL 使用 `ON DUPLICATE KEY UPDATE`，PostgreSQL 使用 `ON CONFLICT DO UPDATE`
> - 建議在 `merchant_trade_no` 欄位加上 UNIQUE INDEX
> - 若使用 MongoDB，用 `updateOne({ merchant_trade_no: tradeNo }, { $set: ... }, { upsert: true })`

## 失敗恢復策略

當你的 server 錯過 callback 時：

### 1. 主動查詢

使用對應的 QueryTrade API 主動查詢訂單狀態：

| 服務 | 查詢端點 |
|------|---------|
| AIO 金流 | `/Cashier/QueryTradeInfo/V5` |
| AIO 取號結果 | `/Cashier/QueryPaymentInfo` |
| 站內付2.0 | `/1.0.0/Cashier/QueryTrade` |
| 國內物流 | `/Helper/QueryLogisticsTradeInfo/V2` |
| 全方位物流 | `/Express/v2/QueryLogisticsTradeInfo` |

### 2. 對帳檔

每日下載對帳檔比對（見 [guides/01-payment-aio.md](./01-payment-aio.md) 對帳區塊）：

| 功能 | 端點 |
|------|------|
| 交易對帳檔下載 | `/PaymentMedia/TradeNoAio` |
| 信用卡撥款對帳 | `/CreditDetail/FundingReconDetail` |

### 3. 監控警示

設定 callback 接收頻率監控，異常時觸發警示。建議監控項目：

- Callback 接收頻率驟降
- RtnCode 非成功的比例異常
- CheckMacValue / AES 解密驗證失敗率上升
- 回應時間接近 10 秒上限

### 4. 程式化失敗恢復

當排程掃描發現未確認訂單時，主動查詢 ECPay API 確認實際狀態。

**恢復策略**：

| 步驟 | 動作 | 注意事項 |
|------|------|---------|
| 1 | 查詢超過 5 分鐘未確認的訂單 | `LIMIT 100` 避免 API 限流 |
| 2 | 呼叫 QueryTradeInfo/V5（AIO）或解密 callback | 使用 [guides/13](./13-checkmacvalue.md) 的 CMV 函式 |
| 3 | 比對 `TradeStatus=1` 更新訂單狀態 | 間隔 200ms + jitter 避免限流 |
| 4 | 每 5 分鐘排程掃描 | Python: `schedule`，Node.js: `setInterval`，Java: `@Scheduled` |

```sql
-- 待確認訂單查詢 SQL
SELECT merchant_trade_no FROM orders
WHERE status = 'pending'
  AND created_at < NOW() - INTERVAL '5 minutes'
ORDER BY created_at ASC
LIMIT 100;  -- 每次最多處理 100 筆，避免 API 限流
```

## 消費爭議（Dispute / Chargeback）處理

### 處理流程

消費爭議通常由信用卡持卡人向發卡銀行提出，綠界會通知特店進行舉證。

| 步驟 | 動作 | 時限 |
|------|------|------|
| 1 | 收到綠界消費爭議通知（email/電話） | — |
| 2 | 準備交易證據（訂單記錄、出貨證明、物流簽收記錄、客服對話記錄） | 通常 7-14 天 |
| 3 | 透過綠界特店後台或客服回覆舉證資料 | 依通知時限 |
| 4 | 綠界轉交發卡銀行審理 | 約 30-90 天 |
| 5 | 結果通知（勝訴維持交易 / 敗訴退款） | — |

### 預防措施

- 保留所有交易記錄和物流配送證明至少 180 天
- 商品描述與實際出貨一致，避免消費者因「貨不對版」提出爭議
- 大額交易建議使用 3D Secure 驗證（已強制實施）
- 退款申請儘速處理，避免消費者直接向銀行申請 chargeback

### 程式化建議

```sql
-- 交易證據保留表
CREATE TABLE transaction_evidence (
  merchant_trade_no VARCHAR(20) PRIMARY KEY,
  order_details JSONB,          -- 訂單明細
  shipping_proof TEXT,           -- 物流追蹤號/簽收記錄
  customer_communication TEXT,   -- 客服對話摘要
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  retained_until TIMESTAMP DEFAULT CURRENT_TIMESTAMP + INTERVAL '180 days'
);
```

> **注意**：消費爭議的具體通知格式和流程依綠界與各銀行的合約而異。建議向綠界客服 (02-2655-1775) 確認最新的爭議處理規範。

## 相關文件

- [guides/01-payment-aio.md](./01-payment-aio.md) — AIO 金流完整指南
- [guides/02-payment-ecpg.md](./02-payment-ecpg.md) — 站內付 2.0 指南
- [guides/06-logistics-domestic.md](./06-logistics-domestic.md) — 國內物流指南
- [guides/07-logistics-allinone.md](./07-logistics-allinone.md) — 全方位物流指南
- [guides/13-checkmacvalue.md](./13-checkmacvalue.md) — CheckMacValue 驗證
- [guides/14-aes-encryption.md](./14-aes-encryption.md) — AES 加解密
- [guides/20-error-codes-reference.md](./20-error-codes-reference.md) — 錯誤碼參考
