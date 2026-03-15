> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 除錯指南 + 錯誤碼 + 常見陷阱

> 若需確認最新 API 錯誤碼定義或參數規格，可從 `references/` 對應檔案 web_fetch 取得最新官方文件。

> 💡 **知道錯誤碼數字？** 直接前往 [guides/21 — 全服務錯誤碼集中參考](./21-error-codes-reference.md) 查找。本頁以**症狀**為導向排除問題。

## 症狀速查表

> 不知道錯誤碼？從你看到的**症狀**開始找：

| 你遇到的症狀 | 最可能原因 | 前往 |
|-------------|-----------|------|
| CheckMacValue 驗證失敗 | HashKey/HashIV 錯誤、Hash 方法搞混（SHA256 vs MD5） | [§1](#1-checkmacvalue-驗證失敗) |
| ReturnURL 收不到通知 | URL 格式、防火牆、未回應 `1\|OK` | [§2](#2-returnurl-收不到通知) |
| HTTP 403 Forbidden | API 速率限制，需等 30 分鐘 | [§3](#3-http-403-forbidden) |
| 付款頁面空白 | 使用了 iframe（AIO 不支援） | [§6](#6-iframe-交易失敗) |
| LINE/FB 內無法交易 | WebView 安全限制 | [§4](#4-ios-linefacebook-無法交易) |
| ✅ RtnCode=2 | **正常業務狀態** — ATM 取號成功（消費者尚未繳費，需等待） | [§9](#9-atm-取號-rtncode2-不是錯誤) |
| ✅ RtnCode=10100073 | **正常業務狀態** — CVS/BARCODE 取號成功（消費者尚未繳費，需等待） | [§10](#10-cvsbarcode-取號-rtncode10100073-不是錯誤) |
| HTTP 404 Not Found | 站內付 2.0 雙 Domain 搞混（ecpg vs ecpayment） | [§14](#14-站內付-20-404-雙-domain-錯誤) |
| 站內付2.0 TransCode ≠ 1 | AES 加密問題（Key/IV 錯誤、URL encode 錯、JSON 格式） | [§15](#15-站內付20-transcode-1-診斷流程) |
| 3D Secure 後交易逾時失敗 | CreatePayment 後未判斷並跳轉 ThreeDURL | [§16](#16-站內付20-3d-secure-處理遺漏) |
| OrderResultURL 資料解析失敗 | 直接 AES 解密 ResultData（跳過 JSON 解析步驟） | [§17](#17-站內付20-callback-格式混淆) |
| 站內付2.0 全部流程不通（不知從哪排查）| 環境/加密設定/Token 生命週期/步驟之間的依賴問題 | [§18](#18-站內付20-首次串接系統性重置診斷) |
| 站內付2.0 ATM/CVS CreatePayment 後不知下一步，或 ReturnURL 遲遲未收到 | ATM/CVS Callback 非同步，消費者繳款後才觸發；Data 含付款指示需自行顯示 | [§30](#30-站內付20-atm--cvsbarcode-callback-非同步時序) |
| AES 解密失敗 / TransCode≠1 | Key/IV 長度非 16 bytes、URL encode 順序錯 | [§13](#13-aes-解密失敗) |
| MerchantTradeNo 重複 | 訂單編號已存在 | [§12](#12-merchanttradeno-重複) |
| ItemName 亂碼或截斷 | 超過 400 bytes | [§5](#5-itemname-亂碼或被截斷) |
| BNPL 被拒 | 金額 < 3,000 元 | [§7](#7-bnpl-被拒) |
| 定期定額停止扣款 | 連續 6 次授權失敗 | [§8](#8-定期定額停止扣款) |
| AIO CheckMacValue 怎麼算 | 參數排序 + ecpayUrlEncode + SHA256 流程 | [§26](#26-aio-checkmacvalue-診斷流程) |
| AIO ReturnURL 通知沒收到 | localhost、HTTP、未回應 `1\|OK`、防火牆 | [§27](#27-aio-returnurl-沒收到通知) |
| B2C 發票 TransCode ≠ 1 | 發票 Key/IV、Revision 缺少、MerchantID 雙層 | [§28](#28-b2c-電子發票-transcode--1-診斷) |
| 物流建單失敗 | 物流用 MD5 非 SHA256；ReceiverStoreID 來源錯誤 | [§29](#29-物流建單失敗診斷) |

---

## 快速排查決策樹

> **錯誤碼查找**：如果你知道具體的 RtnCode 或 TransCode 數字，直接查 [guides/21-error-codes-reference.md](./21-error-codes-reference.md)。
> 本指南聚焦於**排查流程**（不知道問題在哪時怎麼找），guides/21 聚焦於**錯誤碼對照**（知道錯誤碼要查含義）。

```
API 回傳錯誤？
├── CheckMacValue 錯誤 → 見第 1 節
├── HTTP 403 → 速率限制，等 30 分鐘
├── RtnCode 不是 1
│   ├── ATM: RtnCode=2 是正常的（取號成功）
│   ├── CVS/BARCODE: RtnCode=10100073 是正常的
│   ├── 金額相關: 10200050/10200105 → 查 [guides/21](./21-error-codes-reference.md)
│   ├── 訂單重複: 10200047 → 見第 12 節
│   └── 其他: 查 guides/21-error-codes-reference.md
├── AES 解密失敗 → 見第 13 節
├── 收不到通知 → 見第 2 節
├── 站內付2.0 TransCode ≠ 1 → 見第 15 節
├── 站內付2.0 ThreeDURL 未處理（交易逾時） → 見第 16 節
├── 站內付2.0 OrderResultURL 解析失敗 → 見第 17 節
├── 站內付2.0 全部流程不通（不知從哪排查） → 見第 18 節
├── ItemName 被截斷 → 見第 5 節
└── 網路層問題
    ├── DNS 解析失敗 → 確認 FQDN 正確、DNS 伺服器可用
    ├── TLS 握手失敗 → 確認 TLS 1.2+ 啟用、憑證未過期
    ├── 連線逾時 → 檢查防火牆規則、API endpoint 可達性
    └── 回應逾時 → 設定合理的 timeout（建議 30 秒）

前端問題？
├── 付款頁面空白 → 不可用 iframe
├── LINE/FB 無法付款 → WebView 限制
├── Apple Pay 看不到 → 非 Safari
└── WebATM 無法使用 → 手機不支援
```

---

## 1. CheckMacValue 驗證失敗

**最常見的問題**。按以下順序逐步排查：

### 四步驟排查法

**Step 1：確認 HashKey / HashIV 來源正確**
```
測試環境金流：HashKey=pwFHCqoQZGmho4w6  HashIV=EkRm7iFT261dpevs
測試環境物流：HashKey=5294y06JbISpM5x9  HashIV=v77hoKGq4kWxNNIS
```
✓ 檢查你的 config 中的值是否與上方完全一致（區分大小寫）。最常見原因就是複製貼上時多了空格。

**Step 2：確認 Hash 方法是否正確**
| 服務 | 必須使用 |
|------|---------|
| AIO 金流 | **SHA256** |
| 國內物流 | **MD5**（最常搞混） |
| B2C 發票線上折讓（AllowanceByCollegiate 回呼）| **MD5**（發票中唯一有 CheckMacValue 的 Callback） |
| ECPG / 發票（其他 API）/ 全方位物流 | **AES**（不用 CheckMacValue） |

**Step 3：用測試向量驗證你的實作**

複製 [guides/13-checkmacvalue.md](./13-checkmacvalue.md) 中對應語言的測試向量，確認你的函式能產生正確的 CheckMacValue。如果測試向量通過但實際呼叫失敗，問題出在參數值（進入 Step 4）。

**Step 4：檢查參數值細節**
1. 排序方式：Key 不區分大小寫（case-insensitive）
2. URL encode 行為：空格必須是 `+`（Node.js 的 `encodeURIComponent` 是 `%20`，需替換）
3. 轉小寫和 .NET 特殊字元替換
4. 最終結果必須是**大寫**

詳細的語言特定陷阱見 [guides/13-checkmacvalue.md](./13-checkmacvalue.md)。

**CheckMacValue 錯誤時的實際 HTTP 回應**：

AIO 建單失敗時，回傳 HTML 頁面中會包含錯誤訊息（非 JSON）：
```
HTTP/1.1 200 OK
Content-Type: text/html

<html>...CheckMacValue驗證失敗...RtnCode=10200073...</html>
```

AIO 查詢 API 回傳：
```
TradeStatus=10200073&RtnMsg=CheckMacValue verify fail
```

AES-JSON 服務回傳：
```json
{ "MerchantID": "2000132", "TransCode": 999, "TransMsg": "CheckMacValue Error", "Data": "" }
```

> 看到這些回應，優先排查 HashKey/HashIV 和 Hash 方法（SHA256 vs MD5）是否正確。

## 2. ReturnURL 收不到通知

排查步驟（依優先度排序，先排查最常見原因）：

**高優先度（最常見失敗原因）：**
1. **回應格式**：必須回應純字串 `1|OK`（不可有 HTML 標籤、BOM、換行、HTTP header 之外的內容）
2. **URL 格式**：必須是完整的 `https://` URL（不可是 http://，不可是 localhost）
3. **超時**：ReturnURL 必須在 **10 秒內**回應 `1|OK`；耗時邏輯需放入非同步佇列（見 [guides/23](./23-performance-scaling.md)）

**中優先度：**
4. **埠號**：僅支援 80/443（不可用 8080、3000 等非標準埠）
5. **SSL**：必須 TLS 1.2，自簽憑證會被拒
6. **防火牆**：確認你的伺服器允許綠界 IP 存取（ECPay 不公開 IP 白名單，建議開放全部 IP）

**低優先度（邊界情況）：**
7. **CDN**：不可放在 CDN 後面（可能改變 request 格式）
8. **編碼**：非 ASCII 域名需用 punycode
9. **特殊字元**：URL 中不可含分號 `;`、管道 `|`、反引號 `` ` ``

> **回應超時值**：綠界等待 ReturnURL 回應約 **10 秒**。超時會被視為失敗並觸發重送。
> **最佳實踐**：ReturnURL 只做狀態更新（驗證 + upsert + 回應 `1|OK`），
> 耗時操作（開發票、建物流單、發通知信）放入非同步佇列。
> 詳見 [guides/23-performance-scaling.md](./23-performance-scaling.md) §Webhook 佇列架構。

**重送機制**：如果沒收到 `1|OK`，綠界會每 5-15 分鐘重送，每天最多 4 次。

## 3. HTTP 403 Forbidden

**原因**：API 速率限制（Rate Limiting）。

**解決**：
- 等待 30 分鐘後再試
- 避免在短時間內大量呼叫 API
- 檢查是否有迴圈或重試邏輯不當

> **速率限制詳情**：ECPay 未公開具體的 QPS（每秒請求數）限制。已知行為：
> - 觸發條件：短時間大量 API 呼叫（基於 IP + MerchantID）
> - 恢復時間：約 30 分鐘
> - 建議間隔：至少 200ms（每秒最多 5 次呼叫）
> - 批次操作：使用佇列機制，見 [guides/23](./23-performance-scaling.md) §排隊機制

## 4. iOS LINE/Facebook 無法交易

**原因**：LINE/Facebook 的內建瀏覽器（WebView）有安全限制。

**解決**：
- 引導使用者在外部瀏覽器開啟
- 使用站內付2.0（嵌入式）可能有更好的相容性

## 5. ItemName 亂碼或被截斷

**原因**：
- `ItemName` 最長 400 字元（byte），中文字 UTF-8 佔 3 bytes
- 超過會被截斷

**解決**：
- 控制商品名稱長度
- 多商品用 `#` 分隔：`商品A 100 TWD x 1#商品B 200 TWD x 2`

**多商品 ItemName 格式範例**：
```
商品A 100 TWD x 1#商品B 200 TWD x 2#運費 60 TWD x 1
```
> 每個品項用 `#` 分隔。品項格式為自由文字，但建議包含價格和數量方便消費者辨識。
> 總字元（bytes）不得超過 400。中文一字 = 3 bytes (UTF-8)。

## 6. iframe 交易失敗

**原因**：ECPay 付款頁面不支援 iframe。

**解決**：
- 使用新視窗或頁面導向
- 或改用站內付2.0（設計上就是嵌入式）

## 7. BNPL 被拒

**原因**：BNPL（先買後付）最低金額為 **3,000 元**。

**解決**：確認 `TotalAmount >= 3000`。

## 8. 定期定額停止扣款

**原因**：連續 **6 次**授權失敗會自動取消。

**解決**：
- 在失敗時通知使用者更新信用卡
- 使用 `CreditCardPeriodAction` 的 `ReAuth` 重新授權
- 監控 `PeriodReturnURL` 的每期通知

## 9. ATM 取號 RtnCode=2 不是錯誤

ATM 取號成功的 `RtnCode` 是 `2`（不是 `1`）。

| 情境 | RtnCode | 意義 |
|------|---------|------|
| 信用卡付款成功 | 1 | 交易成功 |
| ATM 取號成功 | **2** | 取號成功（消費者尚未繳費） |
| ATM 繳費成功 | 1 | 繳費完成 |

## 10. CVS/BARCODE 取號 RtnCode=10100073 不是錯誤

超商代碼/條碼取號成功的 `RtnCode` 是 `10100073`。

| 情境 | RtnCode | 意義 |
|------|---------|------|
| CVS/BARCODE 取號成功 | **10100073** | 取號成功 |
| CVS/BARCODE 繳費成功 | 1 | 繳費完成 |

## 11. 測試 vs 正式環境混用

**常見錯誤**：用測試帳號打正式環境，或反過來。

**排查**：
- 測試環境 URL 含 `-stage`
- 正式環境 URL 不含 `-stage`
- MerchantID / HashKey / HashIV 是配對的，不可混用

## 12. MerchantTradeNo 重複

**原因**：同一個 MerchantID 下，`MerchantTradeNo` 不可重複。

**解決**：
- 使用時間戳 + 隨機數：`'ORD' . time() . rand(100, 999)`
- 最長 20 字元，僅英數字

## 13. AES 解密失敗

排查步驟：
1. **Key/IV 長度**：必須取前 16 bytes
2. **加解密順序**：加密前先 URL encode，解密後才 URL decode（ECPay 獨有）
3. **Padding**：PKCS7
4. **Base64**：確認沒有多餘的換行或空格
5. **URL encode 函式**：AES 使用**純 urlencode**（不做 toLowerCase、不做 .NET 字元替換），與 CheckMacValue 的 `ecpayUrlEncode` **完全不同**。若誤用 CMV 的 URL encode 邏輯，解密將永遠失敗

詳見：[guides/14-aes-encryption.md §AES vs CMV URL Encode 對比表](./14-aes-encryption.md#aes-vs-cmv-url-encode-對比表)

## 14. 站內付 2.0 404 雙 Domain 錯誤

**症狀**：呼叫站內付 2.0 API 回傳 HTTP 404 Not Found。

**原因**：站內付 2.0（以及綁卡 Token API）使用**兩個不同的 Domain**，API 打錯 Domain 就會得到 404。注意：幕後授權 / 幕後取號只走 `ecpayment` domain，不受此問題影響：

| API | 正確 Domain |
|-----|-----------|
| GetTokenbyTrade / GetTokenbyUser / CreatePayment / 所有綁卡 API | `ecpg-stage.ecpay.com.tw` |
| QueryTrade / DoAction / CreditCardPeriodAction / QueryPaymentInfo / QueryTradeMedia | `ecpayment-stage.ecpay.com.tw` |

**解決**：對照 [guides/02 §端點 URL 一覽](./02-payment-ecpg.md) 確認每個 API 的正確 Domain。

---

## 15. 站內付2.0 TransCode ≠ 1 診斷流程

**症狀**：呼叫 GetTokenbyTrade 或 CreatePayment，回應外層 `TransCode` 不等於 1，`TransMsg` 通常為 "Fail" 或錯誤描述。

**含義**：`TransCode` 是**傳輸層**狀態碼。≠ 1 代表 ECPay 無法解密你傳送的 `Data` 欄位，**此時 Data 不應被解密**。

**排查順序**：

**Step 1：確認 Key 和 IV 正確**
```
測試環境：HashKey=pwFHCqoQZGmho4w6（16 bytes）  HashIV=EkRm7iFT261dpevs（16 bytes）
```
檢查是否有多餘空格、換行，或 Key/IV 與 AIO 金流的 Key/IV 混用。

**Step 2：確認 URL encode 函式正確**

站內付2.0 使用 **`aesUrlEncode`**（只做 `urlencode`），**絕對不能用** AIO 金流的 `ecpayUrlEncode`（有 lowercase + .NET 字元替換）：

| 服務 | URL encode 函式 | 特點 |
|------|----------------|------|
| 站內付2.0（本節） | **`aesUrlEncode`** | 只做 `urlencode`，無 lowercase |
| AIO 金流 | `ecpayUrlEncode` | `urlencode` + `strtolower` + .NET 替換 |

詳見 [guides/14-aes-encryption.md §AES vs CMV URL Encode 對比表](./14-aes-encryption.md)。

**Step 3：確認 JSON → urlencode → AES → base64 順序**

加密流程必須完全按照此順序（缺一步或順序錯都會導致 TransCode ≠ 1）：
```
① json_encode（Data 物件）→ JSON 字串
② urlencode（AES 版本，不做 lowercase）→ URL 編碼字串
③ AES-128-CBC 加密（PKCS7 padding）→ 二進位密文
④ base64_encode → Base64 字串（放入外層 Data 欄位）
```

**Step 4：確認 RqHeader 只有 Timestamp**

站內付2.0 的 `RqHeader` **只有 `Timestamp` 一個欄位**（整數，Unix 秒）。不要加 `Revision`（那是電子發票/物流才有的欄位）。

```json
"RqHeader": { "Timestamp": 1741830960 }  // ✅ 正確
"RqHeader": { "Timestamp": 1741830960, "Revision": "1.0" }  // ❌ 錯誤
```

**Step 5：確認 Timestamp 是秒不是毫秒**
```
正確：Math.floor(Date.now() / 1000)  →  1741830960
錯誤：Date.now()                     →  1741830960000
```

---

## 16. 站內付2.0 3D Secure 處理遺漏

**症狀**：CreatePayment 成功建立，但消費者等待很久後交易逾時失敗，或交易狀態永遠停在待處理。

**原因**：CreatePayment 回應含有非空 `ThreeDURL` 欄位，但後端未將此 URL 傳給前端，或前端未執行跳轉。

> **重要背景**：自 2025/8 起 3D Secure 2.0 強制啟用，幾乎所有信用卡交易的 CreatePayment 回應都會包含 `ThreeDURL`。

**正確處理邏輯**：

```javascript
// 後端解密 CreatePayment 回應後，回傳給前端
const result = await fetch('/your-backend/create-payment', { ... }).then(r => r.json());

// ⚠️ 必須先判斷 ThreeDURL，再判斷 RtnCode
if (result.threeDUrl && result.threeDUrl !== '') {
    // 導向 3D 驗證頁面（此時 RtnCode 通常不是 1，但這是正常的）
    window.location.href = result.threeDUrl;
} else if (result.rtnCode == '1') {
    // 不需 3D 驗證，直接成功
    showSuccess();
} else {
    showError(result.rtnMsg);
}
```

**後端注意事項**：
- `ThreeDURL` 為空字串（`""`）或不存在時，才視為無需 3D 驗證
- `ThreeDURL` 非空時，`RtnCode` 通常為 `0`，這是**正常行為**，不應視為失敗
- 3D 驗證完成後，綠界會分別 POST 到 ReturnURL（S2S）和 OrderResultURL（瀏覽器跳轉）

**測試環境 3D 驗證碼**：固定為 `1234`

---

## 17. 站內付2.0 Callback 格式混淆

**症狀**：ReturnURL 或 OrderResultURL 接收到資料後，解析失敗、AES 解密失敗，或 PHP 的 `json_decode` 回傳 null。

**原因**：站內付2.0 有**兩種格式完全不同**的 Callback，必須用不同方式讀取：

| Callback | Content-Type | 資料位置 | 讀取方式（PHP） | 必要回應 |
|---------|-------------|---------|--------------|---------|
| **ReturnURL** | `application/json` | JSON body | `file_get_contents('php://input')` | `echo '1\|OK'` |
| **OrderResultURL** | `application/x-www-form-urlencoded` | 表單欄位 `ResultData`（**JSON 字串**） | `$_POST['ResultData']` → `json_decode` → `decrypt($outer['Data'])` | 無（顯示頁面） |

**各語言讀取對照**：

| Callback | PHP | Python（Flask） | Node.js（Express） |
|---------|-----|--------------|------------------|
| ReturnURL（JSON） | `json_decode(file_get_contents('php://input'))` | `request.get_json()` | `req.body`（需 express.json() middleware） |
| OrderResultURL（Form） | `json_decode($_POST['ResultData'], true)` → `decrypt($outer['Data'])` | `json.loads(request.form['ResultData'])` → `aes_decrypt(outer['Data'])` | `JSON.parse(req.body.ResultData)` → `aesDecrypt(outer.Data)` |

**常見錯誤**：
1. **把 OrderResultURL 當 JSON 讀**：用 `file_get_contents('php://input')` 讀 Form POST → 得到 `MerchantID=3002607&ResultData=...` 的 raw 字串，再 `json_decode` → null
2. **把 ReturnURL 當 Form 讀**：用 `$_POST` 讀 JSON POST → `$_POST` 為空陣列
3. **直接對 ResultData AES 解密**：`ResultData` 是 JSON 字串（含 `TransCode` 和 AES-encrypted `Data`），必須先 `json_decode` 取出 `Data` 欄位，才能 AES 解密
4. **ReturnURL 忘記回應 `1|OK`**：綠界每隔一段時間重試，直到收到 `1|OK` 為止，不回應會造成重複處理

**正確流程圖**：
```
ReturnURL 收到 POST
  → 讀 php://input → json_decode → 取 TransCode（需=1）
  → aesService->decrypt($body['Data'])
  → 取 RtnCode（=1 表示付款成功）
  → echo '1|OK'（必須）

OrderResultURL 收到 POST
  → 讀 $_POST['ResultData']（JSON 字串，非直接 AES 加密）
  → json_decode($resultDataStr, true)       ← Step 1：解析外層 {TransCode, Data}
  → 確認 $outer['TransCode'] == 1
  → aesService->decrypt($outer['Data'])     ← Step 2：AES 解密業務資料
  → 取 RtnCode → 顯示結果頁面
  → 不需 echo '1|OK'
```

---

## 18. 站內付2.0 首次串接系統性重置診斷

> **使用時機**：完成 5 步驟中的任一步驟後遇到不明錯誤、或多次嘗試仍無法成功時，使用本節進行系統性重置。

站內付 2.0 失敗的根本原因幾乎都是以下五類之一，按發生頻率排列：

| 頻率 | 類型 | 前往 |
|:----:|------|------|
| ① | AES 加密設定錯誤（非 PHP）| [§15](#15-站內付20-transcode-1-診斷流程) |
| ② | Domain 打錯（ecpg vs ecpayment）| [§14](#14-站內付-20-404-雙-domain-錯誤) |
| ③ | Token 過期或 MerchantTradeNo 不一致 | 本節 §步驟三 |
| ④ | ThreeDURL 未處理 | [§16](#16-站內付20-3d-secure-處理遺漏) |
| ⑤ | Callback 格式混淆 | [§17](#17-站內付20-callback-格式混淆) |

### 五步驟系統性重置清單

**步驟一：確認 AES 加密環境（非 PHP 必做）**

執行 [guides/02 §步驟 0](./02-payment-ecpg.md) 的 Python 環境預檢腳本。

- ✅ 看到 `TransCode: 1` → 加密正常，繼續步驟二
- ❌ `TransCode: 0` → 修正 AES 加密後再繼續（見 [§15](#15-站內付20-transcode-1-診斷流程)）

**步驟二：確認 Domain 路由**

| API | 正確 Domain |
|-----|-----------|
| GetTokenbyTrade / GetTokenbyUser / CreatePayment / 所有綁卡 API | `ecpg-stage.ecpay.com.tw` |
| QueryTrade / DoAction / CreditCardPeriodAction / QueryPaymentInfo / QueryTradeMedia | `ecpayment-stage.ecpay.com.tw` |

- ✅ 每個 API 打對 Domain → 繼續步驟三
- ❌ 收到 HTTP 404 → 對照上表修正 Domain

**步驟三：清除 Token 狀態，全部重來**

Token 10 分鐘到期，調試中斷後必須清除所有狀態：

```
□ 產生新的 MerchantTradeNo（含時間戳，確保唯一）
□ 重新呼叫 GetTokenbyTrade（步驟 1）
□ 確認收到 TransCode=1, RtnCode=1, Token（非空字串）
□ 在 10 分鐘內完成步驟 2-4
□ 步驟 4（CreatePayment）使用的 MerchantTradeNo 與步驟 1 完全一致
```

**步驟四：確認 ThreeDURL 處理**

```
□ CreatePayment 回應解密後，檢查 ThreeDURL 欄位是否存在且非空
□ 若 ThreeDURL 非空 → 前端必須執行 window.location.href = threeDUrl
□ 在測試環境，3D 驗證碼固定輸入 1234
□ 3D 驗證完成後，等待 ReturnURL 和 OrderResultURL 的 Callback
```

**步驟五：確認 Callback 端點格式**

```
□ ReturnURL 端點：用 request.get_json()（JSON body）讀取，回應純文字 '1|OK'
□ OrderResultURL 端點：用 request.form['ResultData']（表單欄位）讀取，顯示頁面
□ 兩個端點都有公開可訪問的 URL（不是 localhost:3000）
□ 兩個端點回應 HTTP 200（不是 201、204 等）
□ ReturnURL 回應格式：純文字 1|OK，不含引號、換行、JSON
```

**所有步驟都確認後仍然無法成功？** 聯繫綠界技術支援：sysanalydep.sa@ecpay.com.tw，提供：MerchantTradeNo、呼叫時間、完整的 request/response JSON（注意遮蔽 HashKey/HashIV）。

---

## 19. Apple Pay 限制

- 僅在 **Safari** 瀏覽器可見（2025/4/1 起同步顯示在其他瀏覽器）
- 需要向綠界申請啟用

## 20. WebATM 限制

- **手機瀏覽器不支援**（需要讀卡機）
- 僅支援桌面瀏覽器

## 21. 微信支付 / TWQR 限制

- 需要向綠界另外申請啟用
- 微信支付需要微信商戶號

## 22. URL 含特殊編碼

如果 API 回傳的 URL 含 `%26`（&）、`%3C`（<）等：
- 需要 `urldecode()` 處理後再使用
- 不要直接拿 URL-encoded 的值做業務邏輯

## 23. 僅限新台幣

ECPay 所有服務僅支援 **新台幣 (TWD)**，不支援多幣別。

## 24. 3D Secure 2.0

- **2025/8/1 起強制啟用** 3D Secure 2.0
- 測試環境 SMS 驗證碼固定為 `1234`

## 25. ChoosePayment=ALL 排除特定付款方式

如果用 `ChoosePayment=ALL` 但想排除某些付款方式，使用 `IgnorePayment` 參數：

```php
'IgnorePayment' => 'ATM#CVS#BARCODE',  // 用 # 分隔
```

---

## 26. AIO CheckMacValue 診斷流程

> **症狀**：AIO 建單時，綠界返回「CheckMacValue 驗證失敗」或直接拒絕請求。
> 
> 📌 本節針對 **AIO 金流（Form POST / CMV-SHA256 協議）**。B2C 發票/幕後授權使用 AES-JSON 協議，無需 CheckMacValue；通用計算流程見 [§1](./15-troubleshooting.md#1-checkmacvalue-驗證失敗)。

**診斷清單（依序確認）**：

| 步驟 | 確認項目 | 常見錯誤 |
|------|---------|---------|
| 1 | HashKey/HashIV 是否用 **AIO 金流** 組（3002607）| 誤用發票或物流組 |
| 2 | 參數排序是否**字母升冪（a-z）且大小寫不敏感** | 未排序、用區分大小寫的字典序、或用錯其他順序 |
| 3 | URL encode 是否用 **ecpayUrlEncode**（小寫 + .NET 字符替換）| 誤用標準 urlencode |
| 4 | HashKey 前置 + HashIV 後置後，是否再做一次 `SHA256` | 誤用 MD5 或漏做一次 hash |

```
格式：HashKey={hashkey}&{排序後參數}&HashIV={hashiv}
→ ecpayUrlEncode（小寫 + .NET 替換）
→ SHA256
```

> 詳細計算流程見 [guides/13-checkmacvalue.md](./13-checkmacvalue.md)。物流 API 使用 **MD5**，見 §29。

---

## 27. AIO ReturnURL 沒收到通知

> **症狀**：測試付款成功但 ReturnURL 端點沒有被呼叫。

**排查流程**：

| 可能原因 | 確認方式 | 修正方式 |
|---------|---------|---------|
| URL 用 localhost | 檢查建單時 ReturnURL 值 | 改用 ngrok 或公開 URL |
| ReturnURL 使用 HTTP 而非 HTTPS | 確認 ReturnURL 開頭是否為 `https://` | 改成 HTTPS URL，綠界不接受 HTTP |
| 路由不接受 POST | 用 curl 手動 POST 測試 | 確保路由允許 POST 方法 |
| 端點返回非 `1\|OK` | 查你的伺服器 log | 端點必須明確 echo '1\|OK' |
| 防火牆封鎖綠界 IP | 開啟 server log 看有無請求進來 | 開放綠界 IP 段 |

> ⚠️ ReturnURL 是 **Server-to-Server Form POST**（非 JSON、非 GET）。綠界在 ReturnURL 不回應 `1|OK` 時，每隔 5-15 分鐘重試，每天最多 4 次。

---

## 28. B2C 電子發票 TransCode ≠ 1 診斷

> **症狀**：呼叫發票 API，外層 TransCode 回傳非 1（AES 格式層錯誤，Data 無法解密）。

**診斷清單**：

| 問題 | 確認方式 |
|------|---------|
| HashKey/HashIV 是否用**發票組**（2000132 / ejCk326UnaZWKisg / q9jcZX8Ib9LM8wYk） | 確認 Factory 初始化時的 Key/IV |
| RqHeader.Revision 是否填 `"3.0.0"` | 缺少或值為空/null/undefined → TransCode ≠ 1 |
| MerchantID 是否在外層 JSON 和 Data 兩層都填 | **兩層都必須填寫**，缺少任何一層**都會**導致驗證失敗 |
| AES 加密方式是否 AES-128-CBC（不是 AES-256） | 見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |

> 若 TransCode=1 但 RtnCode ≠ 1，是業務參數錯誤（如 Items 金額加總不等於 SalesAmount），查 [guides/21](./21-error-codes-reference.md)。

---

## 29. 物流建單失敗診斷

> **症狀**：呼叫 Express/Create，RtnCode ≠ 1 或 CheckMacValue 驗證失敗。

**診斷清單**：

| 問題 | 說明 |
|------|------|
| CheckMacValue 用 SHA256 | 物流 API 必須用 **MD5**（與 AIO 的 SHA256 不同；ECPG 系列不使用 CheckMacValue，改用 AES）|
| HashKey/HashIV 用 AIO 組 | 物流有獨立的 Key/IV（5294y06JbISpM5x9 / v77hoKGq4kWxNNIS）|
| ReceiverStoreID 非電子地圖取得 | 超商取貨必須先呼叫 `Express/map`，從 Callback POST 取得 `CVSStoreID`，不可手填（見 [guides/06 §步驟1](./06-logistics-domestic.md)）|
| MerchantTradeNo 重複 | 同組帳號下訂單編號不可重複 |

> 物流狀態碼列表見 `scripts/SDK_PHP/example/Logistics/logistics_status.xlsx`。物流 MD5 計算流程見 [guides/13 §MD5 版本](./13-checkmacvalue.md)。

---

## HTTP 層除錯

當 API 回傳異常時，用 curl 手動發送請求隔離問題：

```bash
# AIO 測試（CheckMacValue 需手動計算）
curl -X POST https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5 \
  -d "MerchantID=3002607&MerchantTradeNo=你的訂單編號&TimeStamp=$(date +%s)&CheckMacValue=計算後的值"

# 站內付 2.0 測試（AES 加密後的 JSON）
curl -X POST https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade \
  -H "Content-Type: application/json" \
  -d '{"MerchantID":"3002607","RqHeader":{"Timestamp":1234567890},"Data":"加密後字串"}'
```

若 curl 可以成功但程式碼失敗，問題在你的 HTTP client 設定（如 Content-Type、TLS、timeout）。

## 網路層除錯

### DNS 檢查
```bash
nslookup payment.ecpay.com.tw
# 或
dig payment.ecpay.com.tw
```

### TLS 檢查
```bash
openssl s_client -connect payment.ecpay.com.tw:443 -tls1_2
# 確認 TLS 版本和憑證資訊
```

### 連線可達性
```bash
curl -v --connect-timeout 10 https://payment.ecpay.com.tw
# 觀察 TCP 連線、TLS 握手、HTTP 回應各階段耗時
```

## 日誌記錄建議

### 該記錄什麼
- 完整的請求參數（遮蔽 HashKey、HashIV、CheckMacValue）
- API 回應的 HTTP 狀態碼和 body
- ReturnURL/ServerReplyURL 收到的完整 POST 資料
- 加解密的中間步驟（排查時開啟，正式環境關閉）

### 遮蔽敏感資料範例

```php
function maskSensitiveData($data) {
    $masked = $data;
    $sensitiveKeys = ['HashKey', 'HashIV', 'CheckMacValue', 'CardNo', 'CardValidMM', 'CardValidYY', 'CardCVV2', 'Token'];
    foreach ($sensitiveKeys as $key) {
        if (isset($masked[$key])) {
            $masked[$key] = substr($masked[$key], 0, 4) . '****';
        }
    }
    return $masked;
}
```

```javascript
// Node.js 版本
function maskSensitiveData(data) {
  const sensitiveKeys = ['HashKey', 'HashIV', 'CheckMacValue', 'CardNo', 'CardValidMM', 'CardValidYY', 'CardCVV2', 'Token'];
  return Object.fromEntries(
    Object.entries(data).map(([key, value]) =>
      sensitiveKeys.includes(key) && typeof value === 'string'
        ? [key, value.slice(0, 4) + '****']
        : [key, value]
    )
  );
}
```

```python
# Python 版本
def mask_sensitive_data(data: dict) -> dict:
    sensitive_keys = {'HashKey', 'HashIV', 'CheckMacValue', 'CardNo', 'CardValidMM', 'CardValidYY', 'CardCVV2', 'Token'}
    return {
        k: (v[:4] + '****' if k in sensitive_keys and isinstance(v, str) else v)
        for k, v in data.items()
    }
```

## 回報綠界技術支援

遇到無法自行解決的問題時，聯絡綠界技術支援需附上：

1. **MerchantID**（特店編號）
2. **MerchantTradeNo**（交易編號）
3. **發生時間**（精確到秒）
4. **完整的 API 請求和回應**（遮蔽 HashKey/HashIV）
5. **錯誤訊息或錯誤碼**
6. **使用環境**（測試/正式、語言/框架版本）

聯絡方式：
- 技術支援信箱：techsupport@ecpay.com.tw
- 開發者文件：https://developers.ecpay.com.tw
- 特店後台：可在後台提交技術問題單

## 跨服務 Top 5 錯誤碼速查

> 以下為各服務最常見的錯誤情境。完整錯誤碼清單見 [guides/21-error-codes-reference.md](./21-error-codes-reference.md)。

### 站內付 2.0（AES-JSON）

站內付2.0 使用**雙層錯誤結構**：先檢查外層 `TransCode`，再檢查內層 `RtnCode`。

| 錯誤情境 | 檢查點 | 常見原因 | 解決方式 |
|---------|--------|---------|---------|
| TransCode ≠ 1 | 外層 JSON | AES 加密錯誤、JSON 格式錯誤、Key/IV 長度非 16 bytes | 檢查 AES 加密流程，確認 URL encode/decode 順序 |
| RtnCode ≠ 1（解密 Data 後） | 內層業務 | 參數錯誤、Token 過期、MerchantTradeNo 重複 | 檢查 RtnMsg 取得詳細錯誤 |
| 10200043 | RtnCode | 3D Secure 驗證失敗 | 請消費者重新進行 3D 驗證 |
| 10200058 | RtnCode | 信用卡授權失敗（額度不足、發卡行拒絕） | 請消費者確認卡片資訊或換卡 |
| 10200115 | RtnCode | 信用卡授權逾時 | 請消費者重新付款，檢查 timeout 設定 |

> **TransCode 常見值**：`1` = API 層成功（需進一步檢查 RtnCode）；`非 1` = API 層失敗（AES/格式問題，無需解密 Data）。

### B2C 電子發票（AES-JSON）

| 錯誤情境 | 常見原因 | 解決方式 |
|---------|---------|---------|
| 稅額與金額不符 | `SalesAmount ≠ TaxAmount + 各項 ItemAmount 總和` | 重新計算稅額，確保加總一致 |
| 統一編號格式錯誤 | 統一編號非 8 位數字 | 使用 `/B2CInvoice/CheckCompanyIdentifier` 驗證 |
| RelateNumber 重複 | 同一關聯號碼重複開立 | 使用新的 RelateNumber（如 `'Inv' + timestamp`） |
| 載具格式錯誤 | 手機條碼未以 `/` 開頭、自然人憑證長度不符 | 手機條碼：`/B2CInvoice/CheckBarcode` 驗證；自然人憑證：2 碼英文 + 14 碼字元 |
| 發票作廢失敗 | 超過作廢期限、發票已折讓 | 確認發票狀態，已折讓的發票需先作廢折讓 |

### 國內物流（CMV-MD5）

| 錯誤情境 | 常見原因 | 解決方式 |
|---------|---------|---------|
| CheckMacValue 驗證失敗 | 用 SHA256 而非 **MD5**、排序或 URL encode 錯誤 | 確認國內物流用 **MD5**（不是 SHA256） |
| RtnCode = 0（通用失敗） | 格式：`0\|ErrorMessage`，多種原因 | 檢查 ErrorMessage 取得具體原因 |
| 門市代碼無效 | ReceiverStoreID 已過期或不存在 | 重新呼叫電子地圖（`/Express/map`）取得最新門市代碼 |
| 物流訂單過期 | 超商寄貨編號逾時 | 重新建立物流訂單 |
| 超商退貨缺 AllPayLogisticsID | 超商退貨設計不回傳此欄位 | 改用 `RtnMerchantTradeNo` 追蹤退貨狀態 |

---

## §30 站內付2.0 ATM / CVS/Barcode Callback 非同步時序

### 症狀

- `CreatePayment` 呼叫成功（`TransCode === 1`），但之後等不到 ReturnURL
- 不確定 `CreatePayment` 回應的 `Data` 裡要取哪些欄位、要做什麼
- 消費者不知道如何繳費（沒有收到虛擬帳號/超商代碼）

### 根本原因

信用卡付款的 ReturnURL 在 3D 驗證後**立即**觸發；ATM 和 CVS/Barcode 的 ReturnURL 是**非同步**的，必須等消費者實際到 ATM/超商完成繳費後，綠界才發送。這不是錯誤，而是預期行為。

### ATM 流程正確順序

```
[你的後端] CreatePayment
    ↓
[綠界] 回傳 Data（含 BankCode, vAccount, ExpireDate）
    ↓
[你的後端] AES 解密 Data，儲存虛擬帳號資訊
    ↓
[你的頁面] 顯示付款指示給消費者
    ↓
[消費者] 到 ATM / 網銀轉帳（可能數分鐘到數天後）
    ↓
[綠界] ReturnURL 非同步送達（JSON POST，需 AES 解密）
```

### CVS / Barcode 流程正確順序

```
[你的後端] CreatePayment
    ↓
[綠界] 回傳 Data（CVS: PaymentNo + ExpireDate；Barcode: Barcode1~3 + ExpireDate）
    ↓
[你的後端] AES 解密 Data，儲存超商代碼
    ↓
[你的頁面] 顯示付款代碼給消費者
    ↓
[消費者] 到便利超商繳費
    ↓
[綠界] ReturnURL 非同步送達
```

### 正確的 CreatePayment 後處理範例

```php
$response = $sdk->post([...], 'https://ecpg-stage.ecpay.com.tw/...');

if ($response['TransCode'] != 1) {
    // 傳輸層失敗，記錄 TransMsg 並顯示錯誤
    throw new Exception($response['TransMsg']);
}

$data = $sdk->decryptData($response['Data']); // AES 解密

// ✅ 信用卡：有 ThreeDURL 需導向
if (!empty($data['ThreeDURL'])) {
    return redirect($data['ThreeDURL']);
}

// ✅ ATM：有 vAccount 需顯示
if (!empty($data['vAccount'])) {
    $order->atm_bank_code = $data['BankCode'];
    $order->atm_account   = $data['vAccount'];
    $order->expire_date   = $data['ExpireDate'];
    $order->save();
    return view('payment.atm', compact('order'));
}

// ✅ CVS：有 PaymentNo 需顯示
if (!empty($data['PaymentNo'])) {
    $order->payment_no  = $data['PaymentNo'];
    $order->expire_date = $data['ExpireDate'];
    $order->save();
    return view('payment.cvs', compact('order'));
}
```

### 排查清單

| 問題 | 確認方式 |
|------|---------|
| 測試環境的 ATM/CVS ReturnURL 要怎麼觸發？ | 在綠界**測試後台**進行模擬付款（不需要真正繳費），會自動送出 ReturnURL |
| ReturnURL 仍然沒來？ | 確認 URL 是 HTTPS + 公開存取，用 ngrok/Cloudflare Tunnel |
| 想重新查詢付款資訊？ | 呼叫 `ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryPaymentInfo`（`ecpayment` domain） |
| ReturnURL callback 收到後怎麼回應？ | 純文字 `1\|OK`（與信用卡相同） |

---

## §31 站內付2.0 全流程可執行自查清單

> **目標**：在不開瀏覽器的情況下，用命令列逐層驗證站內付2.0 的後端邏輯是否正確。  
> JS SDK（步驟 2-3）需要瀏覽器，但步驟 0、1、4、5 可以完全用腳本驗證。

### 自查腳本（Python）

```python
#!/usr/bin/env python3
"""
站內付 2.0 後端自查腳本
執行：pip install pycryptodome requests && python ecpay-selfcheck.py
"""
import time, base64, urllib.parse, hashlib, json, sys
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad
import requests as req

MERCHANT_ID = '3002607'
HASH_KEY    = 'pwFHCqoQZGmho4w6'
HASH_IV     = 'EkRm7iFT261dpevs'
ECPG_URL    = 'https://ecpg-stage.ecpay.com.tw'

def aes_encrypt(data: dict) -> str:
    # json_encode → quote_plus（aesUrlEncode：無 lowercase，無 .NET 替換）→ AES-128-CBC → base64
    json_str  = json.dumps(data, separators=(',', ':'), ensure_ascii=False)
    plaintext = urllib.parse.quote_plus(json_str).replace('~', '%7E')
    cipher = AES.new(HASH_KEY.encode()[:16], AES.MODE_CBC, HASH_IV.encode()[:16])
    return base64.b64encode(cipher.encrypt(pad(plaintext.encode('utf-8'), 16))).decode()

def aes_decrypt(b64: str) -> dict:
    ct     = base64.b64decode(b64)
    cipher = AES.new(HASH_KEY.encode()[:16], AES.MODE_CBC, HASH_IV.encode()[:16])
    plain  = unpad(cipher.decrypt(ct), 16).decode('utf-8')
    return json.loads(urllib.parse.unquote_plus(plain))

def post_ecpay(url: str, data: dict):
    body = {'MerchantID': MERCHANT_ID, 'RqHeader': {'Timestamp': int(time.time())}, 'Data': aes_encrypt(data)}
    r = req.post(url, json=body, timeout=10)
    return r.json()

ok = True

# ── 檢查 0：AES 加密正確性（對照測試向量）───────────────────────
print('\n[Check 0] AES 加密驗證')
test_payload = {'MerchantID': '3002607', 'Test': 'abc'}
encrypted = aes_encrypt(test_payload)
decrypted = aes_decrypt(encrypted)
if decrypted == {'MerchantID': '3002607', 'Test': 'abc'}:
    print(f'  ✅ AES 加/解密正確（密文長度={len(encrypted)}）')
else:
    print(f'  ❌ AES 解密後值與原始不符: {decrypted}')
    ok = False

# 使用 test-vectors 的已知密文驗證（來自 test-vectors/aes-encryption.json）
KNOWN_PLAIN  = '%7B%22MerchantID%22%3A%222000132%22%2C%22BarCode%22%3A%22%2F1234567%22%7D'  # test-vectors/aes-encryption.json Vector 1
KNOWN_CIPHER = 'XeEOdHpTRvxKEqs/JD9RSd16s7VtpyWVCN6AV44pKTW3DVa6yI7vKmjBRp2eulDhXoru/qBqFDBH3fEqlkMn3bbJfJBfGAq+v+SvttutYnc='
# 若想完整驗證所有向量，執行：python test-vectors/verify.py

# ── 檢查 1：GetTokenbyTrade 連線測試 ────────────────────────────
print('\n[Check 1] GetTokenbyTrade')
trade_no = 'SC' + str(int(time.time()))
res = post_ecpay(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {
    'MerchantID': MERCHANT_ID,
    'RememberCard': 1,
    'PaymentUIType': 2,
    'ChoosePaymentList': '1',
    'OrderInfo': {
        'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
        'MerchantTradeNo': trade_no,
        'TotalAmount': 100,
        'ReturnURL': 'https://example.com',
        'TradeDesc': 'selfcheck',
        'ItemName': 'test item',
    },
    'CardInfo': {
        'Redeem': 0,
        'OrderResultURL': 'https://example.com',
    },
})
trans_code = res.get('TransCode')
if trans_code == 1:
    data = aes_decrypt(res['Data'])
    token = data.get('Token', '')
    if token:
        print(f'  ✅ GetTokenbyTrade 成功！Token={token[:20]}… (長度={len(token)})')
        print(f'     ⏱️ 此 Token 在 10 分鐘內有效，MerchantTradeNo={trade_no}')
    else:
        rtn_code = data.get('RtnCode', '?')
        print(f'  ❌ TransCode=1 但 Token 空白，RtnCode={rtn_code} RtnMsg={data.get("RtnMsg")}')
        ok = False
else:
    print(f'  ❌ TransCode={trans_code} TransMsg={res.get("TransMsg")} → AES/格式問題')
    ok = False

# ── 檢查 2：域名確認 ────────────────────────────────────────────
print('\n[Check 2] 端點域名自查')
endpoints = [
    ('GetTokenbyTrade / CreatePayment', ECPG_URL, '正確：ecpg-stage'),
    ('QueryTrade / DoAction',           'https://ecpayment-stage.ecpay.com.tw', '正確：ecpayment-stage'),
]
for name, url, note in endpoints:
    domain = urllib.parse.urlparse(url).netloc
    print(f'  ℹ️  {name} → {domain}（{note}）')
print('  ⚠️  常見錯誤：把 QueryTrade 送到 ecpg-stage → 404 Not Found')

# ── 檢查 3：CreatePayment 模擬（需真實 PayToken，此處僅驗證錯誤回應格式）──
print('\n[Check 3] CreatePayment 錯誤回應格式驗證')
if ok:
    dummy_res = post_ecpay(f'{ECPG_URL}/Merchant/CreatePayment', {
        'MerchantID':      MERCHANT_ID,
        'MerchantTradeNo': trade_no,
        'PayToken':        'DUMMY_PAYTOKEN_FOR_FORMAT_CHECK',
    })
    if dummy_res.get('TransCode') == 1:
        d = aes_decrypt(dummy_res['Data'])
        print(f'  ℹ️  回應格式正確（TransCode=1），RtnCode={d.get("RtnCode")} RtnMsg={d.get("RtnMsg")}')
        print('  ⚠️  使用假 PayToken 預期失敗，這是正常的。真實流程需從 JS SDK getPayToken 取得 PayToken。')
    elif dummy_res.get('TransCode') is not None:
        print(f'  ℹ️  TransCode={dummy_res.get("TransCode")} TransMsg={dummy_res.get("TransMsg")}（格式正確，Token 無效為預期）')

# ── 結果 ─────────────────────────────────────────────────────────
print('\n' + '='*60)
if ok:
    print('✅ 後端 AES 加密 + GetTokenbyTrade 均正常')
    print('   下一步：打開瀏覽器完成步驟 2-3（JS SDK getPayToken），取得 PayToken 後呼叫 CreatePayment')
else:
    print('❌ 發現問題，請根據上方錯誤訊息排查')
    sys.exit(1)
```

**執行方式**

```bash
pip install pycryptodome requests
python ecpay-selfcheck.py
```

**預期輸出**

```
[Check 0] AES 加密驗證
  ✅ AES 加/解密正確（密文長度=88）

[Check 1] GetTokenbyTrade
  ✅ GetTokenbyTrade 成功！Token=eyJhbGciOiJIUzI1NiIsIn… (長度=520)
     ⏱️ 此 Token 在 10 分鐘內有效，MerchantTradeNo=SC1710288000

[Check 2] 端點域名自查
  ℹ️  GetTokenbyTrade / CreatePayment → ecpg-stage.ecpay.com.tw（正確：ecpg-stage）
  ℹ️  QueryTrade / DoAction → ecpayment-stage.ecpay.com.tw（正確：ecpayment-stage）
  ⚠️  常見錯誤：把 QueryTrade 送到 ecpg-stage → 404 Not Found

[Check 3] CreatePayment 錯誤回應格式驗證
  ℹ️  TransCode=1 TransMsg=...（格式正確，Token 無效為預期）

============================================================
✅ 後端 AES 加密 + GetTokenbyTrade 均正常
   下一步：打開瀏覽器完成步驟 2-3（JS SDK getPayToken），取得 PayToken 後呼叫 CreatePayment
```

### ReturnURL Callback 模擬測試（curl）

模擬綠界發送 ReturnURL 回呼到本地伺服器，驗證你的 handler 邏輯：

```bash
# 先確認本地伺服器在 localhost:5000 運行
# 模擬綠界送出的 ReturnURL JSON POST（Data 欄位需是真實 AES 加密值）
curl -X POST http://localhost:5000/ecpay/callback \
  -H "Content-Type: application/json" \
  -d '{
    "MerchantID": "3002607",
    "RqHeader": {"Timestamp": 1710288000},
    "Data": "【執行下方 Python 取得真實加密值】"
  }'

# 產生模擬 ReturnURL Data（AES 加密）
python3 -c "
import base64, urllib.parse
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad
KEY = 'pwFHCqoQZGmho4w6'; IV = 'EkRm7iFT261dpevs'
data = {
    'MerchantID': '3002607',
    'MerchantTradeNo': 'SC' + '1710288000',
    'RtnCode': 1,
    'RtnMsg': '付款成功',
    'PaymentType': 'Credit_CreditCard',
}
plain = urllib.parse.quote_plus(json.dumps(data, separators=(',', ':')))
c = AES.new(KEY.encode(), AES.MODE_CBC, IV.encode())
print(base64.b64encode(c.encrypt(pad(plain.encode(), 16))).decode())
"
# 將輸出填入上方 curl 的 Data 欄位，然後執行 curl
# 預期：你的 handler 印出「✅ 付款成功」並回應純文字 1|OK
```

---

## §32 正式環境上線後的高頻問題（站內付 2.0）

> 以下問題僅在正式環境流量出現後才浮現，測試階段通常不會遇到。

### §32.1 ReturnURL 重複處理（重複出貨/重複發點）

**症狀**：同一筆訂單觸發兩次出貨或兩次發點數。

**原因**：ECPay 在未收到 `1|OK` 確認前最多重試 4 次（跨數小時）。若你的 ReturnURL handler 未做冪等性保護，高峰期或伺服器短暫逾時時會被多次執行業務邏輯。

**解法**：在資料庫層用 `MerchantTradeNo` 的唯一約束做原子操作，詳細實作見 [guides/02-payment-ecpg.md §正式環境實作注意事項](./02-payment-ecpg.md#正式環境實作注意事項)。

### §32.2 Token 過期（消費者填卡超過 10 分鐘）

**症狀**：消費者在填寫信用卡資訊時離開頁面或分心，回來時 JS SDK 付款表單消失或 `getPayToken` 靜默失敗。

**原因**：Token 有效期僅 10 分鐘，過期後步驟 2 的 JS SDK 表單失效。

**解法**：前端倒計時提示 + Token 自動刷新策略，詳細實作見 [guides/02-payment-ecpg.md §正式環境實作注意事項](./02-payment-ecpg.md#正式環境實作注意事項)。

### §32.3 ATM/CVS 完整可執行範例

需要 ATM 虛擬帳號或超商代碼付款的完整 Python Flask 單一檔案範例（GetToken → CreatePayment → 顯示付款指示 → 接收非同步 ReturnURL），請見 [guides/02-payment-ecpg.md §ATM/CVS 完整可執行範例](./02-payment-ecpg.md#-atm--cvs-完整可執行範例python-flask)。

---

## 相關文件

- CheckMacValue：[guides/13-checkmacvalue.md](./13-checkmacvalue.md)
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
- POS 刷卡機：[guides/17-pos-integration.md](./17-pos-integration.md)
- 直播收款：[guides/18-livestream-payment.md](./18-livestream-payment.md)
- 離線發票：[guides/19-invoice-offline.md](./19-invoice-offline.md)
- 錯誤碼集中參考：見 [guides/21-error-codes-reference.md](./21-error-codes-reference.md)
- Callback 處理：見 [guides/22-webhook-events-reference.md](./22-webhook-events-reference.md)
- 效能與擴展：見 [guides/23-performance-scaling.md](./23-performance-scaling.md)
