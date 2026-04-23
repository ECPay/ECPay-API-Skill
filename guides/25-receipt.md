> 對應 ECPay API 版本 | 電子收據 API | 最後更新：2026-04

# 電子收據完整指南

> **讀對指南了嗎？** 需要開發票給消費者 → [guides/04 B2C 發票](./04-invoice-b2c.md)；企業對企業發票 → [guides/05 B2B 發票](./05-invoice-b2b.md)；無網路環境發票 → [guides/18 離線發票](./18-invoice-offline.md)。需要串金流 → [guides/01 AIO](./01-payment-aio.md) 或 [guides/02 ECPG](./02-payment-ecpg.md)。

## 概述

電子收據 API 用於開立**非交易憑證類**的收據，主要三種情境：

| 收據類型（`ReceiptType`）| 用途 | 特別限制 |
|:---:|------|----------|
| `1` = 一般收據 | 記帳、定金、押金、雜支等非發票用途 | — |
| `2` = 公益收據 | 捐贈給公益團體（社福、公益） | **僅可 1 項商品**；DonorType 只能 1 或 2（不可帶 3/4/5）|
| `4` = 政治獻金 | 捐贈給政黨、政治團體、擬參選人 | DonorType=5（匿名）金額 ≤ 10,000；PaymentMethod=3（現金）金額 ≤ 100,000 |

**與電子發票的差異**：

| 項目 | 電子發票（guides/04/05） | 電子收據（本指南） |
|------|------------------------|------------------|
| 法規依據 | 財政部統一發票使用辦法 | 一般商業收據 / 政治獻金法 / 公益勸募條例 |
| 租稅效力 | 買方可申報進項稅 / 中獎 | 無稅務抵免（捐贈收據可抵稅為例外）|
| 需要字軌 | 是（配號、字軌設定）| 否 |
| API 端點 | `/B2CInvoice/*` / `/B2BInvoice/*` | `/Receipt/*` |
| RqHeader.Revision | B2C=`"3.0.0"` / B2B=`"1.0.0"` | **無** Revision 欄位 |
| 金額限制 | 依發票類型 | ReceiptType=4 有匿名上限 1 萬 / 現金上限 10 萬 |

使用 **AES 加密 + JSON 格式**，支援 AES-CBC（預設）與 **AES-GCM**（新模式，見 §AES-GCM 模式）。與電子發票共用 domain `einvoice(-stage).ecpay.com.tw`。

### ⚠️ AES-JSON 開發者必讀：雙層錯誤檢查

電子收據（以及所有 AES-JSON 服務）的回應為**三層 JSON** 結構。**必須做兩次檢查**：

1. 檢查外層 `TransCode === 1`（否則 AES 加密/格式有問題，無需解密 Data）
2. 解密 Data 後，檢查內層 `RtnCode === 1`（**整數** `1`，非字串 `'1'`）（業務邏輯問題）

只檢查其中一層會導致錯誤漏檢。完整錯誤碼參考見 [guides/20](./20-error-codes-reference.md)。各服務 Callback 格式對照見 [SKILL.md Callback 格式速查表](../SKILL.md)。

> ⚠️ **RqHeader 跨服務差異**：電子收據的 RqHeader **只需 `Timestamp`，不需要 Revision**。對照其他 AES-JSON 服務:
> - **B2C 發票**:需 `Revision: "3.0.0"`
> - **B2B 發票**:需 `Revision: "1.0.0"` + `RqID`(UUID v4)
> - **離線電子發票**:需 `Revision: "1.0.0"`
> - **全方位 / 跨境物流 v2**:需 `Revision: "1.0.0"`
> - **站內付 2.0 / 幕後授權 / 幕後取號**:只需 `Timestamp`
> - **電子票證**:只需 `Timestamp`
>
> 誤加 Revision 不會導致錯誤（綠界忽略不認識的欄位），但漏加於發票類 API 會造成 TransCode ≠ 1。

## 前置需求

- **MerchantID / HashKey / HashIV**（兩組測試帳號，擇一使用）：

| 用途 | MerchantID | HashKey | HashIV |
|------|-----------|---------|--------|
| 一般收據 + 公益收據（ReceiptType=1 或 2）| `2000132` | `ejCk326UnaZWKisg` | `q9jcZX8Ib9LM8wYk` |
| 政治獻金收據（ReceiptType=4）| `3002607` | `pwFHCqoQZGmho4w6` | `EkRm7iFT261dpevs` |

> ⚠️ 公益／政治收據需聯繫綠界業務團隊申請開通權限，未申請直接呼叫會回錯誤。
> ⚠️ 一般/公益帳號 `2000132` 與 **B2C/B2B 發票共用**，但端點不同。收據走 `/Receipt/*`、發票走 `/B2CInvoice/*` 或 `/B2BInvoice/*`，不可混用。

- **加密模式**：AES-CBC（預設）或 AES-GCM（新模式，需於後台設定啟用，見 §AES-GCM 模式）
- **基礎端點**：`https://einvoice-stage.ecpay.com.tw/Receipt/`（測試環境）
- **PHP SDK**：綠界官方 SDK 目前**未內建電子收據 Service**（發票走 `PostWithAesJsonResponseService`），需自行組裝 AES-JSON 請求，參考 [guides/14](./14-aes-encryption.md) 的加解密函式。

## 🚀 首次串接：最快成功路徑

> 第一次串接電子收據？從這裡開始，目標是開立**一張一般收據**（ReceiptType=1）。

### 前置確認清單

- [ ] 測試帳號擇一：`2000132`（一般/公益）或 `3002607`（政治獻金）
- [ ] AES-128-CBC 加密已實作（見 [guides/14](./14-aes-encryption.md)），或先使用 PHP 的 openssl 函式快速驗證
- [ ] 了解三層 JSON 結構（外層 TransCode → 解密 Data → 內層 RtnCode），必須做**兩次**錯誤檢查
- [ ] RqHeader **不需要** `Revision` 欄位（與 B2C/B2B 發票不同）
- [ ] 伺服器時間已校正（驗證時間區間 10 分鐘，時差過大會被拒絕）

### Issue（開立收據）必填欄位速查

> ⚠️ **SNAPSHOT 2026-04** | 來源：`references/Receipt/電子收據API技術串接文件.md`
> 📋 以下為 Issue API 外層 + Data 內層欄位一覽。詳細規格請 web_fetch [64254.md](https://developers.ecpay.com.tw/64254.md)。

**外層參數**：

| 欄位 | 類型 | 必填？ | 說明 |
|------|------|:------:|------|
| `MerchantID` | String(10) | ✅ | 特店編號 |
| `RqHeader.Timestamp` | Number | ✅ | Unix Timestamp（UTC+8），10 分鐘內有效 |
| `Data` | String | ✅ | Data 物件 JSON 字串先 urlencode → AES 加密 → Base64 |

**Data 內層參數**（加密前）：

| 欄位 | 類型 | 必填？ | 說明 / 陷阱 |
|------|------|:------:|-------------|
| `MerchantID` | String(10) | ✅ | 需與外層相同，**兩處都要填** |
| `Amount` | Number | ✅ | 收據金額（可為 0；需等於 `Items[].ItemAmount` 加總） |
| `Name` | String(60) | ✅ | 收據抬頭（持有人姓名或公司名） |
| `ReceiptType` | Int | ✅ | `1`=一般 / `2`=公益 / `4`=政治 |
| `DonorType` | Int | ⚠️ ReceiptType=2 或 4 時必填 | `1`=自然人 / `2`=公司法人 / `3`=人民團體 / `4`=政黨 / `5`=匿名；**ReceiptType=2 不可帶 3/4/5** |
| `RetrievalMethod` | Int | ✅ | `1`=紙本 / `2`=電子 / `3`=自行處理 |
| `ReceiptDate` | String(20) | ✅ | 開立日期 `yyyy-MM-dd HH:mm:ss` 或 `yyyy/MM/dd HH:mm:ss` |
| `RelateNumber` | String(64) | ✅ | 特店自訂編號，**唯一不可重複**，勿用特殊符號，大小寫視為相同（`abc123` = `ABC123`）|
| `Identifier` | String(50) | ⚠️ 依 DonorType | DonorType=1 帶證號；=2 帶統編；=3 帶人民團體登記字號；=4 帶政黨登記字號 |
| `Email` | String(200) | ⚠️ RetrievalMethod=2 時必填 | 電子郵件 |
| `Phone` | String(15) | ⚠️ ReceiptType=2 & DonorType=2 時必填 | 連絡電話 |
| `CellPhone` | String(10) | ⚠️ ReceiptType=2 & DonorType=1 時必填 | 手機號碼 |
| `CompanyAddress` | String(200) | 選填 | 營業登記地址 |
| `DeliveryAddress` | String(200) | ⚠️ RetrievalMethod=1 時必填 | 紙本寄送地址 |
| `Note` | String(200) | 選填 | 收據備註 |
| `Items` | Array<Object> | ⚠️ ReceiptType=1 或 2 時必填 | 商品明細，**ReceiptType=2 僅可帶 1 項** |
| `Items[].ItemSeq` | Int | ✅ | 明細排列序號（1~999）|
| `Items[].ItemName` | String(100) | ✅ | 商品名稱 |
| `Items[].ItemCount` | Int | ✅ | 商品數量 |
| `Items[].ItemPrice` | Number | ✅ | 單價 |
| `Items[].ItemAmount` | Number | ✅ | 單項合計（= `ItemCount × ItemPrice`）|
| `PaymentMethod` | Int | ⚠️ ReceiptType=4 時必填 | `1`=匯款 / `2`=票據 / `3`=現金；ReceiptType=1 或 2 時系統忽略 |
| `CheckInfo` | Object | ⚠️ PaymentMethod=2 時必填 | 票據資料（CheckNumber / Drawer / IssueDate）|
| `DonationInfo` | Object | ⚠️ ReceiptType=4 時必填 | 捐贈資料 |
| `DonationInfo.IsBequest` | Int | 選填 | 遺囑捐贈 `0`=否（預設）/ `1`=是 |
| `DonationInfo.DonationDate` | String(20) | ✅ | 捐贈日 |
| `DonationInfo.DepositDate` | String(20) | 選填 | 存入專戶日期；未帶可能無法上傳監察院 |
| `DonationInfo.DepositTradeNo` | String(20) | 選填 | 存入綠界交易編號；帶了會自動補 `RemittingBank` 為「永豐商業銀行」|
| `DonationInfo.RemittingBank` | String(100) | 選填 | 匯款金融機構 |

### 步驟 1：建立 AES-JSON 請求並開立收據

> ⚠️ **SNAPSHOT 2026-04** | 來源：`references/Receipt/電子收據API技術串接文件.md`

```php
<?php
// ECPay 電子收據開立範例（一般收據 ReceiptType=1）
// 資料來源：SNAPSHOT 2026-04 based on web_fetch https://developers.ecpay.com.tw/64254.md
// 注意：綠界 PHP SDK 目前無內建 Receipt Service，需自行組裝 AES-JSON

$merchantId = '2000132';
$hashKey    = 'ejCk326UnaZWKisg';
$hashIv     = 'q9jcZX8Ib9LM8wYk';
$endpoint   = 'https://einvoice-stage.ecpay.com.tw/Receipt/Issue';

$data = [
    'MerchantID'      => $merchantId,
    'Amount'          => 100,
    'Name'            => '王小明',
    'ReceiptType'     => 1,                          // 1=一般
    'RetrievalMethod' => 2,                          // 2=電子（Email 寄送）
    'ReceiptDate'     => date('Y/m/d H:i:s'),        // UTC+8
    'RelateNumber'    => 'RCPT' . time(),            // 唯一編號
    'Email'           => 'test@example.com',
    'Items' => [[
        'ItemSeq'    => 1,
        'ItemName'   => '測試商品',
        'ItemCount'  => 1,
        'ItemPrice'  => 100,
        'ItemAmount' => 100,
    ]],
];

// 1. Data 內容 → JSON → urlencode → AES-128-CBC 加密 → Base64
$jsonData    = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$urlEncoded  = urlencode($jsonData);
$encryptData = base64_encode(openssl_encrypt($urlEncoded, 'AES-128-CBC', $hashKey, OPENSSL_RAW_DATA, $hashIv));

// 2. 組 Request Body
$body = [
    'MerchantID' => $merchantId,
    'RqHeader'   => ['Timestamp' => time()],         // ⚠️ 電子收據 RqHeader 不需 Revision
    'Data'       => $encryptData,
];

// 3. POST（使用 stream_context，亦可改用 Guzzle / curl）
$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => json_encode($body),
        'timeout' => 10,
    ],
]);
$rawResponse = file_get_contents($endpoint, false, $context);
$response    = json_decode($rawResponse, true);

// 4. 外層錯誤檢查（TransCode）
if ((int)$response['TransCode'] !== 1) {
    throw new RuntimeException('傳輸失敗：' . $response['TransMsg']);
}

// 5. 解密 Data → Base64 解碼 → AES 解密 → urldecode → JSON
$decrypted = openssl_decrypt(base64_decode($response['Data']), 'AES-128-CBC', $hashKey, OPENSSL_RAW_DATA, $hashIv);
$result    = json_decode(urldecode($decrypted), true);

// 6. 內層錯誤檢查（RtnCode 是整數，非字串）
if ((int)$result['RtnCode'] !== 1) {
    throw new RuntimeException('業務失敗：' . $result['RtnMsg']);
}

// 7. 成功，取得綠界收據編號
echo "開立成功：ReceiptNo = {$result['ReceiptNo']}\n";
// 範例回傳：ReceiptNo = Sale2026040800000448
```

### 步驟 2：等待通知（選用）

若特店需要主動發送收據通知郵件給消費者或特店自己，可呼叫 Notification API。
見下方 §Notification 章節。

### 步驟 3：查詢收據

使用 `ReceiptNo`（綠界收據編號）或 `RelateNumber`（特店自訂編號）擇一查詢，見下方 §GetReceipt 章節。

---

## API 端點一覽

| 作業 | 測試環境端點 | 正式環境端點 | 參考 URL |
|------|-------------|-------------|---------|
| 開立（Issue） | `https://einvoice-stage.ecpay.com.tw/Receipt/Issue` | `https://einvoice.ecpay.com.tw/Receipt/Issue` | [64254.md](https://developers.ecpay.com.tw/64254.md) |
| 修改（UpdateIssue） | `https://einvoice-stage.ecpay.com.tw/Receipt/UpdateIssue` | `https://einvoice.ecpay.com.tw/Receipt/UpdateIssue` | [64336.md](https://developers.ecpay.com.tw/64336.md) |
| 作廢（Invalid） | `https://einvoice-stage.ecpay.com.tw/Receipt/Invalid` | `https://einvoice.ecpay.com.tw/Receipt/Invalid` | [64513.md](https://developers.ecpay.com.tw/64513.md) |
| 通知（Notification）| 商家→綠界發送通知的 API | 同左 | [64624.md](https://developers.ecpay.com.tw/64624.md) |
| 單筆查詢（GetReceipt）| `https://einvoice-stage.ecpay.com.tw/Receipt/GetReceipt` | `https://einvoice.ecpay.com.tw/Receipt/GetReceipt` | [64544.md](https://developers.ecpay.com.tw/64544.md) |

所有端點共用：
- **HTTP Method**：`POST`
- **Content-Type**：`application/json`
- **加密**：AES-CBC（預設）或 AES-GCM
- **Timestamp 驗證**：10 分鐘

---

## UpdateIssue（修改收據）

> ⚠️ **SNAPSHOT 2026-04** | 來源：`references/Receipt/電子收據API技術串接文件.md`
> 📋 詳細規格請 web_fetch [64336.md](https://developers.ecpay.com.tw/64336.md)。

### 請求 Data（加密前）

| 欄位 | 類型 | 必填？ | 說明 |
|------|------|:------:|------|
| `MerchantID` | String(10) | ✅ | 特店編號 |
| `ReceiptNo` | String(20) | ✅ | 綠界收據編號（原開立後取得）|
| `Reason` | String(200) | ✅ | 異動原因 |
| `IssueModel` | Object | ✅ | 修改後的完整收據資料（結構同 Issue 的 Data）|

`IssueModel` 欄位與 Issue 的 Data 內層一致（Amount / Name / ReceiptType / DonorType / RetrievalMethod / Identifier / Email / Phone / CellPhone / DeliveryAddress / Items / ReceiptDate / PaymentMethod / CheckInfo / DonationInfo）。

### 回應 Data（解密後）

| 欄位 | 類型 | 說明 |
|------|------|------|
| `RtnCode` | Int | `1`=成功，其他=失敗 |
| `RtnMsg` | String(200) | 回應訊息 |

### 範例（PHP）

```php
// 修改收據金額或收據抬頭
$data = [
    'MerchantID' => '2000132',
    'ReceiptNo'  => 'Sale2026040800000448',
    'Reason'     => '客戶要求更正抬頭',
    'IssueModel' => [
        'Amount'          => 150,                   // 金額從 100 改為 150
        'Name'            => '王大明',               // 抬頭從 王小明 改為 王大明
        'ReceiptType'     => 1,
        'RetrievalMethod' => 2,
        'ReceiptDate'     => date('Y/m/d H:i:s'),
        'Email'           => 'test@example.com',
        'Items' => [[
            'ItemSeq'    => 1,
            'ItemName'   => '測試商品',
            'ItemCount'  => 1,
            'ItemPrice'  => 150,
            'ItemAmount' => 150,
        ]],
    ],
];
// 後續 AES 加密、POST、錯誤檢查流程與 Issue 相同，端點改為 /Receipt/UpdateIssue
```

> ⚠️ **可修改範圍**：`IssueModel` 幾乎可更新所有欄位（金額、抬頭、身分、商品明細、開立日期等）。官方文件未明列「不可修改欄位」，若修改後 RtnCode ≠ 1，視 RtnMsg 判斷（常見為該欄位鎖定於作廢後，或金額違反類型上限）。實際修改前建議聯繫綠界業務確認個別案例限制。

---

## Invalid（作廢收據）

> ⚠️ **SNAPSHOT 2026-04** | 來源：`references/Receipt/電子收據API技術串接文件.md`
> 📋 詳細規格請 web_fetch [64513.md](https://developers.ecpay.com.tw/64513.md)。

### 請求 Data（加密前）

| 欄位 | 類型 | 必填？ | 說明 |
|------|------|:------:|------|
| `MerchantID` | String(10) | ✅ | 特店編號 |
| `ReceiptNo` | String(20) | ✅ | 綠界收據編號 |
| `Reason` | String(200) | ✅ | 作廢原因 |

### 回應 Data（解密後）

| 欄位 | 類型 | 說明 |
|------|------|------|
| `RtnCode` | Int | `1`=成功，其他=失敗 |
| `RtnMsg` | String(200) | 回應訊息 |

### 範例（PHP）

```php
$data = [
    'MerchantID' => '2000132',
    'ReceiptNo'  => 'Sale2026040800000448',
    'Reason'     => '客戶取消交易',
];
// 端點：/Receipt/Invalid，加密、POST、雙層檢查流程同 Issue
```

> ⚠️ **作廢時限與規則**：官方文件未明訂可作廢時限。若作廢失敗（RtnCode ≠ 1），常見原因為：① 收據已作廢不可重複作廢；② 政治獻金收據依政治獻金法有特殊規範需聯繫綠界業務處理。

---

## Notification（收據通知）

> ⚠️ **SNAPSHOT 2026-04** | 來源：`references/Receipt/電子收據API技術串接文件.md`
> 📋 詳細規格請 web_fetch [64624.md](https://developers.ecpay.com.tw/64624.md)。

> 🎯 **方向注意（重要）**：此 API 為 **client → server**（**特店主動呼叫綠界**）。特店端透過此 API **請綠界寄出** 開立/作廢通知郵件給消費者（或特店自己）。
>
> **不是 Callback**（綠界→特店）。`Notified` / `NotifyTag` 是特店送給綠界的**指示欄位**，告訴綠界「請通知誰」「通知什麼事件」，而非綠界回報給特店的資訊。與電子發票的「發送發票通知」（`/B2CInvoice/Notify/*`）概念相同。

### 請求 Data（加密前）

| 欄位 | 類型 | 必填？ | 說明 |
|------|------|:------:|------|
| `MerchantID` | String(10) | ✅ | 特店編號 |
| `ReceiptNo` | String(20) | ✅ | 綠界收據編號 |
| `Notified` | String(1) | ✅ | **請綠界通知的對象**：`C`=消費者 / `M`=特店 / `A`=兩者皆通知 |
| `NotifyTag` | Int | ✅ | **通知的事件類型**：`1`=開立成功 / `2`=作廢成功 |
| `NotifyMail` | String(200) | ⚠️ Notified=C 或 A 時必填 | 收件人電子郵件，多筆用分號 `;` 分隔 |

### 回應 Data（解密後）

| 欄位 | 類型 | 說明 |
|------|------|------|
| `RtnCode` | Int | `1`=成功（綠界已排程通知發送），其他=失敗 |
| `RtnMsg` | String(200) | 回應訊息 |

### 範例（PHP，client → server）

```php
// 請綠界寄一封「收據開立成功通知」郵件給消費者
$data = [
    'MerchantID' => '2000132',
    'ReceiptNo'  => 'Sale2026040800000448',
    'Notified'   => 'C',                       // 通知消費者
    'NotifyTag'  => 1,                         // 開立成功
    'NotifyMail' => 'customer@example.com',
];
// 端點請以 web_fetch 64624.md 取得最新規格為準
// 加密、POST、雙層錯誤檢查流程同 Issue（§建立 AES-JSON 請求並開立收據）
```

---

## GetReceipt（單筆查詢）

> ⚠️ **SNAPSHOT 2026-04** | 來源：`references/Receipt/電子收據API技術串接文件.md`
> 📋 詳細規格請 web_fetch [64544.md](https://developers.ecpay.com.tw/64544.md)。

### 請求 Data（加密前）

| 欄位 | 類型 | 必填？ | 說明 |
|------|------|:------:|------|
| `MerchantID` | String(10) | ✅ | 特店編號 |
| `ReceiptNo` | String(20) | ⚠️ 與 RelateNumber 擇一 | 綠界收據編號 |
| `RelateNumber` | String(64) | ⚠️ 與 ReceiptNo 擇一 | 特店自訂編號 |

### 回應 Data（解密後）

| 欄位 | 類型 | 說明 |
|------|------|------|
| `RtnCode` | Int | `1`=成功 |
| `RtnMsg` | String(200) | 回應訊息 |
| `Amount` | Number | 收據金額 |
| `Name` | String | 收據抬頭 |
| `ReceiptType` | Int | 收據類型 |
| `ReceiptNo` | String | 綠界收據編號 |
| `RelateNumber` | String | 特店自訂編號 |
| `ReceiptDate` | String | 開立日期 |
| `InvalidStatus` | Int | 作廢狀態（`0`=正常 / `1`=已作廢）|
| `InvalidDate` | String | 作廢日期（若已作廢）|
| `Identifier` / `Email` / `Phone` / `CellPhone` | String | 持有人資訊 |
| `Items` | Array | 商品明細 |

### 範例（PHP）

```php
// 用綠界收據編號查詢單筆收據明細
$data = [
    'MerchantID' => '2000132',
    'ReceiptNo'  => 'Sale2026040800000448',   // 與 RelateNumber 擇一
    // 'RelateNumber' => 'RCPT1712345678',    // 或改用自訂編號
];
// 端點：/Receipt/GetReceipt
// 加密、POST、雙層錯誤檢查流程同 Issue；回傳 Data 內含 Amount / Name / InvalidStatus 等
```

---

## AES-GCM 模式（新加密選項）

> ⚠️ **SNAPSHOT 2026-04** | 來源：`references/Receipt/電子收據API技術串接文件.md`
> 📋 詳細規格請 web_fetch [64820.md](https://developers.ecpay.com.tw/64820.md)。

電子收據是**首個支援 AES-GCM** 的綠界 API 服務。AES-GCM 提供**認證加密（AEAD）**，能同時驗證資料完整性，理論上更安全，但實作需配合 IV 自產與 Tag 處理。

### 與 AES-CBC 對照

| 項目 | AES-CBC（預設）| AES-GCM（新模式）|
|------|---------------|-----------------|
| 演算法 | AES-128-CBC + PKCS7 padding | AES-128-GCM |
| Key | `HashKey`（取前 16 bytes）| `HashKey`（16 bytes）|
| IV | `HashIV`（取前 16 bytes，固定）| **自行產生 12 byte 英數字**（每次不同）|
| 認證標籤 | 無 | 16 byte GCM Tag |
| Padding | PKCS7 | 無（GCM 不需要）|
| 輸出結構 | `Base64(AES-CBC(data))` | `Base64(IV(12B) + Ciphertext + Tag(16B))` |
| 啟用方式 | 預設 | 需於綠界後台或程式碼切換（詳洽業務）|

### AES-GCM 加密步驟

1. 將 Data 物件 → `json_encode` → `urlencode`
2. 自行產生 12 byte 英數字作為 IV（每次請求產生新的 IV）
3. 使用 `HashKey` + 產生的 IV 執行 AES-128-GCM 加密，取得 Ciphertext 與 16 byte Tag
4. 按順序組合位元組：`IV (12B) || Ciphertext || Tag (16B)`
5. 對組合結果進行 Base64 編碼

### AES-GCM 解密步驟

1. Base64 解碼 → 取得位元組串
2. 前 12 byte = IV；最後 16 byte = Tag；中間 = Ciphertext
3. 使用 `HashKey` + IV + Tag 執行 AES-128-GCM 解密（若 Tag 驗證失敗，解密會報錯，代表資料被竄改）
4. 對解密結果進行 URL Decode
5. 解析 JSON

### PHP 實作（AES-GCM）

> 📌 **PHP 開發者看這裡**：綠界 PHP SDK 目前**不支援 GCM**（SDK 只封裝 CBC）。下方為 service-specific 的 PHP 手寫實作。
>
> **其他 12 語言的 AES-GCM 完整實作**（Python / Node.js / TypeScript / Java / C# / Go / C / C++ / Rust / Swift / Kotlin / Ruby）請見 **[guides/14 §AES-GCM 模式](./14-aes-encryption.md#aes-gcm-模式電子收據選用)**。該章節涵蓋跨語言的 GCM 加解密函式、測試向量模式、常見錯誤對照。

```php
<?php
// AES-GCM 加密（openssl 需 PHP 7.1+）
function aesGcmEncrypt(array $data, string $hashKey): string {
    $json       = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $urlEncoded = urlencode($json);

    // 自產 12 byte 英數字 IV（每次不同）
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $iv    = '';
    for ($i = 0; $i < 12; $i++) {
        $iv .= $chars[random_int(0, strlen($chars) - 1)];
    }

    $tag        = '';
    $ciphertext = openssl_encrypt($urlEncoded, 'aes-128-gcm', $hashKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

    // 組合：IV (12B) + Ciphertext + Tag (16B)
    return base64_encode($iv . $ciphertext . $tag);
}

function aesGcmDecrypt(string $base64, string $hashKey): array {
    $bytes      = base64_decode($base64);
    $iv         = substr($bytes, 0, 12);
    $tag        = substr($bytes, -16);
    $ciphertext = substr($bytes, 12, -16);

    $decrypted = openssl_decrypt($ciphertext, 'aes-128-gcm', $hashKey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($decrypted === false) {
        throw new RuntimeException('AES-GCM 解密失敗（可能 Tag 驗證不通過）');
    }
    return json_decode(urldecode($decrypted), true);
}
```

> ⚠️ **AES-GCM 注意事項**：
> 1. **IV 絕對不可重複**：GCM 模式若相同 Key 用同一 IV 加密不同內容，會洩露明文。必須每次請求自產隨機 IV。
> 2. **IV 長度固定 12 byte**：綠界規格為 12，**不是 16**（AES-CBC 的 IV 長度）。誤用 16 會解密失敗。
> 3. **Tag 驗證失敗 = 資料被竄改**：若解密時 Tag 不符，代表傳輸過程資料異動（或 Key 錯誤），應視為錯誤拒絕。
> 4. **PHP openssl 參數順序**：加密時 `$tag` 是 by-reference 輸出；解密時 `$tag` 是輸入參數。兩者呼叫方式不同。
> 5. **啟用 AES-GCM 需特店開通**：預設為 AES-CBC。切換至 GCM 需聯繫綠界業務確認後台設定。

### 其他語言的 AES-GCM 完整實作

**PHP 以外的 12 種語言（Python / Node.js / TypeScript / Java / C# / Go / C / C++ / Rust / Swift / Kotlin / Ruby）的 AES-GCM 完整加解密函式，請見 [guides/14 §AES-GCM 模式](./14-aes-encryption.md#aes-gcm-模式電子收據選用)**。

該章節包含：
- 每語言完整 `aes_gcm_encrypt` / `aes_gcm_decrypt` 函式（含隨機 IV 生成與測試向量模式）
- AES-CBC 與 AES-GCM 對照表
- GCM 特有常見錯誤（IV 長度誤用、Tag 驗證失敗、AAD 設定等 8 條）
- 每語言的依賴與版本需求

**完整 E2E（Go + TypeScript）**：包含 HTTP Server 骨架、Callback handler、業務邏輯，見 [guides/23 §電子收據 AES-GCM E2E 範例](./23-multi-language-integration.md#電子收據-aes-gcm-e2e-範例v30)。

**測試向量**（4 組 GCM 固定 IV 向量，跨 Python / Node.js / Go / Java / C# 五語言驗證）：見 [test-vectors/aes-encryption.json](../test-vectors/aes-encryption.json) 的 `mode: "gcm"` 向量（#10-13）。

**各語言 API 快速對照**（詳細實作仍需看 guides/14）：

| 語言 | AES-GCM API |
|------|-------------|
| PHP | `openssl_encrypt($pt, 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16)` |
| Python | `Crypto.Cipher.AES.new(key, AES.MODE_GCM, nonce=iv)` + `.encrypt_and_digest()` |
| Node.js / TypeScript | `crypto.createCipheriv('aes-128-gcm', key, iv)` + `.getAuthTag()` |
| Go | `cipher.NewGCMWithNonceSize(block, 12)` + `.Seal(iv, iv, pt, nil)` |
| Java / Kotlin | `Cipher.getInstance("AES/GCM/NoPadding")` + `new GCMParameterSpec(128, iv)` |
| C# | `new AesGcm(key, 16)` + `.Encrypt(nonce, pt, ct, tag)` |
| Swift | `CryptoKit.AES.GCM.seal(pt, using: key)` — 首選，比 CBC 大幅簡化 |
| Rust | `aes_gcm::Aes128Gcm` + `.encrypt(nonce, pt)`（需新增 `aes-gcm` crate）|
| Ruby | `OpenSSL::Cipher.new('aes-128-gcm')` + `.auth_tag` |
| C / C++ | OpenSSL `EVP_aes_128_gcm()` + `EVP_CTRL_GCM_GET_TAG` |

---

## 收據類型詳細限制

### ReceiptType=1（一般收據）

- `DonorType` 系統忽略（可不填）
- `PaymentMethod` 系統忽略（皆視為匯款）
- `Items` 必填，數量不限
- 適用於：定金、押金、雜支、退款證明等非發票用途

### ReceiptType=2（公益收據）

- `DonorType` **僅可填 1（自然人）或 2（公司法人）**，不可填 3/4/5
- `PaymentMethod` 系統忽略
- `Items` 必填，**僅可帶 1 項商品**
- `CellPhone`（DonorType=1 時）或 `Phone`（DonorType=2 時）必填
- 需聯繫綠界業務團隊申請開通
- 適用於：捐贈給社福團體、公益勸募活動

### ReceiptType=4（政治獻金）

- `DonorType` 必填（1~5 皆可）
- `DonationInfo` 必填
- `PaymentMethod` 必填（1=匯款 / 2=票據 / 3=現金）
- 金額限制：
  - `DonorType=5`（匿名）金額不可 > **10,000** 元
  - `PaymentMethod=3`（現金）金額不可 > **100,000** 元
- `CheckInfo` 於 PaymentMethod=2（票據）時必填
- 需聯繫綠界業務團隊申請開通
- 適用於：政治獻金法規範的政黨/政治團體/擬參選人捐贈

---

## 常見陷阱

### 1. RqHeader 誤加 Revision

電子收據 **不需要** Revision。若誤加 `RqHeader.Revision: "3.0.0"`（沿用 B2C 發票習慣）：
- 不會導致錯誤（綠界忽略未知欄位）
- 但維護者易混淆，建議移除

### 2. RtnCode 型別誤判（字串 vs 整數）

電子收據回應（AES-JSON 協定）解密後 `RtnCode` 是**整數 `1`**，非字串 `'1'`。
- ❌ `if ($result['RtnCode'] === '1')` → 永遠為 false
- ✅ `if ((int)$result['RtnCode'] === 1)` 或 `if ($result['RtnCode'] === 1)`

JavaScript 建議使用 `Number(rtnCode) === 1` 防禦性轉型。

### 3. 金額與商品明細不一致

`Amount` 必須等於 `sum(Items[].ItemAmount)`。不符會觸發 RtnCode 錯誤（常見錯誤訊息：金額合計不符）。

### 4. RelateNumber 大小寫陷阱

系統視 `ABC123` 與 `abc123` 為相同。若靠大小寫區分唯一性會出現「RelateNumber 重複」錯誤。建議：
- 只用大寫或只用小寫
- 不使用特殊符號（`-`、`_`、`.` 等依系統可能被視為不同）

### 5. ReceiptType=2 帶多項商品

公益收據系統限制**僅 1 項商品**，帶 2 項以上會被拒絕。若要記錄多品項合計，將名稱合併為「捐贈物品一批」並用單一 Items 項。

### 6. 時間校正漏做

綠界 Timestamp 驗證區間 10 分鐘。若伺服器使用 UTC 或時區不正（例如海外機房）:
- 錯誤：使用 `time()` 但伺服器時區為 UTC+0 → 發送的 Timestamp 已過期
- 正確：確保伺服器時區為 `Asia/Taipei` 或 `UTC+8`，或在程式中校正 `date_default_timezone_set('Asia/Taipei')`

### 7. 政治獻金金額超限

ReceiptType=4 且 DonorType=5（匿名）或 PaymentMethod=3（現金）時有金額上限。違反會被拒絕，不會自動截斷。送出前須程式驗證。

### 8. Items 陣列被錯誤序列化

若使用動態語言（PHP/Python/Node.js），且 `Items` 建成物件而非陣列，會在 JSON 序列化為 `{}` 而非 `[]`。PHP 需用 numerically-indexed array，Python 需用 `list` 而非 `dict`。

### 9. DepositDate 缺漏導致監察院申報失敗

政治獻金收據若 `DonationInfo.DepositDate` 與 `DepositTradeNo` 皆未填，系統不自動補齊，**可能無法上傳至監察院進行申報**。建議：
- 有綠界交易關聯 → 帶 `DepositTradeNo`（系統自動補 `RemittingBank` 為「永豐商業銀行」）
- 無綠界交易關聯 → 手動填 `DepositDate`

### 10. AES-GCM 與 AES-CBC 誤用

若特店後台設定為 AES-GCM 但程式仍送 AES-CBC（或反之），會收到解密失敗錯誤。切換模式前須確認：
- 綠界後台設定值
- 加密程式採用對應模式
- IV 長度：CBC=16 byte / GCM=12 byte

---

## 相關連結

- [references/Receipt/電子收據API技術串接文件.md](../references/Receipt/電子收據API技術串接文件.md) — 官方文件 URL 索引（12 個子頁面）
- [guides/14 AES 加密](./14-aes-encryption.md) — 多語言 AES-CBC 實作（GCM 見本指南 §AES-GCM 模式）
- [guides/20 錯誤碼速查](./20-error-codes-reference.md) — 跨服務錯誤碼對照
- [guides/04 B2C 電子發票](./04-invoice-b2c.md) — 對比發票介接流程
- [SKILL.md Callback 格式速查表](../SKILL.md) — 各服務 Callback 回應格式
