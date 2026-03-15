> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# B2B 電子發票完整指南

## 快速導航

> **不確定選哪個模式？** → [決策樹](#何時選交換何時選存證)
>
> **交換模式**（雙方確認流程）：[概述](#交換模式-vs-存證模式) | [開立](#開立發票) | [確認](#確認發票交換模式) | [折讓](#折讓) | [退回](#退回發票) | [端點一覽](#端點-url-一覽交換模式)
>
> **存證模式**（開立即生效）：[專屬章節](#存證模式專屬章節) | [開立範例](#存證模式--開立發票範例) | [折讓範例](#存證模式--折讓範例) | [端點一覽](#存證模式端點-url-一覽)
>
> **共通操作**：[通知](#通知) | [交易對象維護](#交易對象維護) | [查詢操作](#查詢操作一覽) | [字軌設定](#字軌設定查詢)

## 概述

B2B 電子發票適用於**賣給企業（含統編）**的情境。分為**交換模式**和**存證模式**兩種。使用 AES 加密 + JSON 格式。

## 交換模式 vs 存證模式

| 面向 | 交換模式 | 存證模式 |
|------|---------|---------|
| 用途 | 雙方互開互確認 | 單方存證備查 |
| 確認流程 | 需要對方確認 | 不需要確認 |
| 操作數量 | 較多（含 Confirm 系列） | 較少 |
| 適用場景 | 正式 B2B 交易 | 內部存證 |

### 何時選交換？何時選存證？

```
需要 B2B 電子發票？
├── 買方也使用電子發票系統，需要雙方確認 → 交換模式
│   適用：正式 B2B 交易、需要買方簽收確認
├── 僅需存檔備查，不需買方確認 → 存證模式
│   適用：內部存證、小型 B2B、買方無電子發票系統
└── 不確定 → 建議先用存證模式（較簡單，之後可升級）
```

### B2B vs B2C 功能對照

| 面向 | B2C (guides/04) | B2B (本指南) |
|------|:---:|:---:|
| RqHeader.Revision | `3.0.0` | `1.0.0` |
| RqHeader.RqID | 不需要 | **必填**（UUID 格式） |
| 端點前綴 | `/B2CInvoice/` | `/B2BInvoice/` |
| Confirm/Reject API | 無 | 有（交換模式） |
| 買方統編 | 選填 | **必填** |
| 載具類型 | 手機/自然人/捐贈 | 不適用 |
| 適用情境 | 賣給消費者 | 賣給企業 |

## 前置需求

- MerchantID / HashKey / HashIV（測試：2000132 / ejCk326UnaZWKisg / q9jcZX8Ib9LM8wYk）
- SDK Service：`PostWithAesJsonResponseService`
- 基礎端點：`https://einvoice-stage.ecpay.com.tw/B2BInvoice/`

## 🚀 首次串接：最快成功路徑

> 第一次串接 B2B 電子發票？從這裡開始，目標是開立**一張存證模式 B2B 發票**（最少步驟）。
> 確認成功後再依業務需求切換到交換模式或加入 Confirm 流程。

### 前置確認清單

- [ ] ⚠️ **B2B 發票測試帳號與 B2C 發票帳號相同，但與金流帳號不同**（金流用 `3002607`，發票用 `2000132`）：MerchantID `2000132` / HashKey `ejCk326UnaZWKisg` / HashIV `q9jcZX8Ib9LM8wYk`
- [ ] ⚠️ **RqHeader 與 B2C 不同**：B2B 多了 `RqID`（UUID 格式，每次請求唯一），且 `Revision` 為 `"1.0.0"`（B2C 是 `"3.0.0"`）
- [ ] ⚠️ **存證模式 vs 交換模式**：存證模式開立後即生效（無需買方確認），交換模式需對方確認。首次測試建議用存證模式
- [ ] AES-128-CBC 加密已實作，或使用 PHP SDK 的 `PostWithAesJsonResponseService`
- [ ] 了解三層 JSON 結構（外層 TransCode → 解密 Data → 內層 RtnCode），做**兩次**錯誤檢查
- [ ] `CustomerIdentifier`（統一編號）必填 8 碼，格式為數字字串

---

### 步驟 1：開立存證模式 B2B 發票

> 參考範例：`scripts/SDK_PHP/example/Invoice/B2B/Issue.php`

```php
$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv'  => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),  // ← 每次請求唯一 UUID v4（B2B 必填，B2C 無此欄位）
        'Revision'  => '1.0.0',           // ← B2B 固定 1.0.0（B2C 是 3.0.0）
    ],
    'Data' => [
        'MerchantID'         => '2000132',         // Data 內層也必填
        'RelateNumber'       => 'B2B' . time(),    // 每次唯一
        'CustomerIdentifier' => '12345678',        // 統一編號（8碼數字）
        'CustomerEmail'      => 'company@example.com',
        'InvType'            => '07',
        'TaxType'            => '1',               // 1=應稅
        'SalesAmount'        => 100,               // 未稅金額
        'TaxAmount'          => 5,
        'TotalAmount'        => 105,               // 含稅總額
        'Items'              => [[
            'ItemSeq'     => 1,
            'ItemName'    => '測試商品',
            'ItemCount'   => 1,
            'ItemPrice'   => 100,
            'ItemTaxType' => '1',
            'ItemAmount'  => 100,
        ]],
    ],
];

try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Issue');
    // 兩層錯誤檢查（AES-JSON 協議要求）：先 TransCode，再 RtnCode
    if (($response['TransCode'] ?? 0) !== 1)
        throw new \Exception('AES/格式錯誤: ' . ($response['TransMsg'] ?? ''));
    $data = $response['Data'] ?? [];  // PHP SDK 已自動解密
    if (($data['RtnCode'] ?? 0) !== 1) {  // RtnCode 為整數 1（AES-JSON 服務，非字串 "1"）
        throw new \Exception('業務錯誤 RtnCode=' . ($data['RtnCode'] ?? '?') . ' ' . ($data['RtnMsg'] ?? ''));
    }
    echo '發票開立成功：' . $data['InvoiceNumber'];
} catch (\Exception $e) {
    error_log('B2B Invoice Error: ' . $e->getMessage());
}
```

```typescript
// Node.js / TypeScript — 開立 B2B 電子發票（npm install axios；crypto 為內建）
// ⚠️ B2B 與 B2C 的兩個關鍵差異：RqHeader 有 RqID（UUID），且 Revision 為 "1.0.0"
import axios from 'axios';
import * as crypto from 'crypto';

const MERCHANT_ID = '2000132';
const HASH_KEY = Buffer.from('ejCk326UnaZWKisg');  // ⚠️ 發票帳號，不同於金流帳號
const HASH_IV  = Buffer.from('q9jcZX8Ib9LM8wYk');

function aesEncrypt(data: object): string {
    const encoded = encodeURIComponent(JSON.stringify(data))
        .replace(/%20/g, '+').replace(/~/g, '%7E')
        .replace(/!/g, '%21').replace(/'/g, '%27')
        .replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A');
    const cipher = crypto.createCipheriv('aes-128-cbc', HASH_KEY, HASH_IV);
    return Buffer.concat([cipher.update(encoded, 'utf8'), cipher.final()]).toString('base64');
}

function aesDecrypt(base64: string): any {
    const decipher = crypto.createDecipheriv('aes-128-cbc', HASH_KEY, HASH_IV);
    const raw = Buffer.concat([decipher.update(base64, 'base64'), decipher.final()]).toString();
    return JSON.parse(decodeURIComponent(raw.replace(/\+/g, '%20')));
}

async function postInvoiceB2B(url: string, data: object): Promise<any> {
    const resp = await axios.post(url, {
        MerchantID: MERCHANT_ID,
        RqHeader: {
            Timestamp: Math.floor(Date.now() / 1000),
            RqID:      crypto.randomUUID(),   // ⚠️ B2B 必填，每次請求必須唯一（B2C 無此欄位）
            Revision:  '1.0.0',               // ⚠️ B2B 固定 1.0.0（B2C 是 3.0.0）
        },
        Data: aesEncrypt(data),
    });
    if (resp.data.TransCode !== 1)
        throw new Error(`AES/格式錯誤: ${resp.data.TransMsg}`);
    return aesDecrypt(resp.data.Data);
}

// 開立存證模式 B2B 發票
postInvoiceB2B('https://einvoice-stage.ecpay.com.tw/B2BInvoice/Issue', {
    MerchantID:          MERCHANT_ID,
    RelateNumber:        'B2B' + Math.floor(Date.now() / 1000),
    CustomerIdentifier:  '12345678',    // 統一編號（8碼數字字串）
    CustomerEmail:       'company@example.com',
    InvType:             '07',
    TaxType:             '1',
    SalesAmount:         100,   // 未稅金額
    TaxAmount:           5,
    TotalAmount:         105,   // 含稅總額
    Items: [{ ItemSeq: 1, ItemName: '測試商品', ItemCount: 1,
               ItemPrice: 100, ItemTaxType: '1', ItemAmount: 100 }],
}).then(data => {
    if (data.RtnCode === 1)
        console.log(`✅ 發票開立成功！發票號碼：${data.InvoiceNumber}`);
    else
        console.error(`❌ 業務錯誤 RtnCode=${data.RtnCode} RtnMsg=${data.RtnMsg}`);
}).catch(console.error);
```

> **⚠️ 步驟 1 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | `TransCode` ≠ 1 | RqID 缺少或 AES 加密失敗 | 確認 RqHeader 有 `RqID`（UUID 格式）+ `Revision` = `"1.0.0"` |
> | `RtnCode` ≠ 1 | 統一編號格式錯誤 | `CustomerIdentifier` 必須是 8 碼純數字字串（如 `"12345678"`） |
> | `TransCode` ≠ 1，TransMsg 提到 Revision | Revision 填成 `"3.0.0"` | B2B 固定用 `"1.0.0"`，B2C 才是 `"3.0.0"` |
> | RqID 重複導致失敗 | 使用靜態 RqID | 每次請求都必須產生新 UUID，不可重複使用 |

**步驟 1 成功後應看到（Data 解密後）**：
```json
{
  "RtnCode": 1,
  "RtnMsg": "新增成功",
  "InvoiceNumber": "AB12345678",
  "RandomNumber": "3456"
}
```

---

### 步驟 2（交換模式）：買方確認發票

> 存證模式開立後即完成，**不需要此步驟**。交換模式才需要對方呼叫 Confirm API。

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'RqID' => \Ramsey\Uuid\Uuid::uuid4()->toString(), 'Revision' => '1.0.0'],
    'Data' => [
        'MerchantID'   => '2000132',
        'InvoiceNumber' => 'AB12345678',   // 步驟 1 回應的 InvoiceNumber，於此欄位帶入
        'InvoiceDate'  => '2026-03-12',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/IssueConfirm');
```

---

## AES 請求格式

B2B 的 RqHeader 與 B2C 不同，多了 `RqID`，且 Revision 為 `1.0.0`：

```json
{
  "MerchantID": "2000132",
  "RqHeader": {
    "Timestamp": 1234567890,
    "RqID": "uuid-string",
    "Revision": "1.0.0"
  },
  "Data": "AES加密後的Base64字串"
}
```

> **RqID 格式**：建議使用 UUID v4（如 `550e8400-e29b-41d4-a716-446655440000`）。
> PHP 可用 `\Ramsey\Uuid\Uuid::uuid4()->toString()` 搭配 Composer 套件，或 PHP 8.0+ 可用 `sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', ...)` 自行生成。

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式 | AES-JSON*（RqHeader 含 RqID，Revision 為 `1.0.0`） — 詳見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證 | AES-128-CBC 加密 Data 欄位 — 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| 測試環境 | `https://einvoice-stage.ecpay.com.tw` |
| 正式環境 | `https://einvoice.ecpay.com.tw` |
| Revision | `1.0.0`（與 B2C 的 `3.0.0` 不同） |
| RqHeader 差異 | 多了 `RqID`（唯一請求識別碼，UUID 格式） |
| 回應結構 | 三層 JSON（TransCode → 解密 Data → RtnCode）；RtnCode 為**整數** `1`（非字串 `"1"`） |
| 端點前綴 | `/B2BInvoice/`（B2C 為 `/B2CInvoice/`） |

> **與 B2C 的關鍵差異**：B2B 的 RqHeader 多了 `RqID` 欄位，`Revision` 為 `1.0.0`，且交換模式多了 Confirm/Reject 系列 API。

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Invoice/B2B電子發票API技術文件_交換模式.md` 或 `_存證模式.md`
> 以下端點及參數僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

### 端點 URL 一覽（交換模式）

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

> 存證模式不含 Confirm/Reject 系列 API

## 開立發票

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/Issue.php`

```php
$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv'  => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'         => '2000132',
        'RelateNumber'       => 'B2B' . time(),
        'CustomerIdentifier' => '12345678',   // 統一編號（8 碼）
        'CustomerEmail'      => 'company@example.com',
        'InvType'            => '07',
        'TaxType'            => '1',
        'Items'              => [
            [
                'ItemSeq'     => 1,
                'ItemName'    => '企業商品',
                'ItemCount'   => 10,
                'ItemPrice'   => 100,
                'ItemTaxType' => '1',
                'ItemAmount'  => 1000,
            ],
        ],
        'SalesAmount' => 1000,   // 未稅金額
        'TaxAmount'   => 50,     // 稅額
        'TotalAmount' => 1050,   // 含稅總額
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Issue');
```

### B2B vs B2C 發票差異

| 欄位 | B2C | B2B |
|------|-----|-----|
| CustomerIdentifier | 選填 | **必填**（統編） |
| SalesAmount | 含稅金額 | **未稅金額** |
| TaxAmount | 不需要 | **必填** |
| TotalAmount | 不需要 | **必填**（含稅） |
| Items.ItemSeq | 不需要 | **必填** |

## 確認發票（交換模式）

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/IssueConfirm.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'    => '2000132',
        'InvoiceNumber' => 'AB12345678',
        'InvoiceDate'   => '2025-01-15',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/IssueConfirm');
```

## 作廢發票

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/Invalid.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'    => '2000132',
        'InvoiceNumber' => 'AB12345678',
        'InvoiceDate'   => '2025-01-15',
        'Reason'        => '開立錯誤',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Invalid');
```

### 確認作廢（交換模式）

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/InvalidConfirm.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'    => '2000132',
        'InvoiceNumber' => 'AB12345678',
        'InvoiceDate'   => '2025-01-15',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/InvalidConfirm');
```

## 退回發票

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/Reject.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'    => '2000132',
        'InvoiceNumber' => 'AB12345678',
        'InvoiceDate'   => '2025-01-15',
        'Reason'        => '金額不符',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Reject');
```

### 確認退回（交換模式）

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/RejectConfirm.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'    => '2000132',
        'InvoiceNumber' => 'AB12345678',
        'InvoiceDate'   => '2025-01-15',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/RejectConfirm');
```

## 折讓

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/Allowance.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'  => '2000132',
        'TaxAmount'   => 5,
        'TotalAmount' => 100,
        'Details'     => [
            [
                'OriginalInvoiceNumber' => 'AB12345678',
                'OriginalInvoiceDate'   => '2025-01-15',
                'ItemName'              => '折讓商品',
                'OriginalSequenceNumber'=> 1,
                'ItemCount'             => 1,
                'ItemPrice'             => 100,
                'ItemAmount'            => 100,
            ],
        ],
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Allowance');
```

### 確認折讓 / 取消折讓 / 確認取消折讓 / 作廢折讓

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/AllowanceConfirm.php`, `scripts/SDK_PHP/example/Invoice/B2B/CancelAllowance.php`, `scripts/SDK_PHP/example/Invoice/B2B/CancelAllowanceConfirm.php`

> **AllowanceInvalid vs CancelAllowance 差異**：
> - **CancelAllowance**（取消折讓）：折讓申請後、買方確認前，由賣方撤回申請。
> - **AllowanceInvalid**（作廢折讓）：折讓已確認後，因故作廢已生效的折讓發票（需提供 Reason）。

| 操作 | 端點 | Data |
|------|------|------|
| 確認折讓 | /B2BInvoice/AllowanceConfirm | MerchantID, AllowanceNo |
| 取消折讓 | /B2BInvoice/CancelAllowance | MerchantID, AllowanceNo, Reason |
| 確認取消折讓 | /B2BInvoice/CancelAllowanceConfirm | MerchantID, AllowanceNo |
| 作廢折讓（交換模式）| /B2BInvoice/AllowanceInvalid | MerchantID, AllowanceNo, Reason |
| 確認作廢折讓（交換模式）| /B2BInvoice/AllowanceInvalidConfirm | MerchantID, AllowanceNo |

## 通知

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/Notify.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'    => '2000132',
        'InvoiceDate'   => '2025-01-15',
        'InvoiceNumber' => 'AB12345678',
        'NotifyMail'    => 'company@example.com',
        'InvoiceTag'    => '1',
        'Notified'      => 'C',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Notify');
```

## 交易對象維護

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/MaintainMerchantCustomerData.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'   => '2000132',
        'Action'       => 'Add',          // Add=新增, Update=修改, Delete=刪除
        'Identifier'   => '12345678',     // 統編
        'type'         => '2',
        'CompanyName'  => '測試公司',
        'TradingSlang' => '測試',
        'ExchangeMode' => '0',
        'EmailAddress' => 'company@example.com',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/MaintainMerchantCustomerData');
```

## 字軌設定查詢

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2B/GetInvoiceWordSetting.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'      => '2000132',
        'InvoiceYear'     => '109',
        'InvoiceTerm'     => 0,
        'UseStatus'       => 0,
        'InvoiceCategory' => 2,    // 2=B2B
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/GetInvoiceWordSetting');
```

## 查詢操作一覽

| 操作 | 端點 | Data | 範例檔案 |
|------|------|------|---------|
| 查詢開立 | /B2BInvoice/GetIssue | MerchantID, InvoiceCategory=0, InvoiceNumber, InvoiceDate | `scripts/SDK_PHP/example/Invoice/B2B/GetIssue.php` |
| 查詢開立確認 | /B2BInvoice/GetIssueConfirm | 同上 | `scripts/SDK_PHP/example/Invoice/B2B/GetIssueConfirm.php` |
| 查詢作廢 | /B2BInvoice/GetInvalid | 同上 | `scripts/SDK_PHP/example/Invoice/B2B/GetInvalid.php` |
| 查詢作廢確認 | /B2BInvoice/GetInvalidConfirm | 同上 | `scripts/SDK_PHP/example/Invoice/B2B/GetInvalidConfirm.php` |
| 查詢折讓 | /B2BInvoice/GetAllowance | MerchantID, AllowanceNo | `scripts/SDK_PHP/example/Invoice/B2B/GetAllowance.php` |
| 查詢折讓確認 | /B2BInvoice/GetAllowanceConfirm | 同上 | `scripts/SDK_PHP/example/Invoice/B2B/GetAllowanceConfirm.php` |
| 查詢折讓作廢 | /B2BInvoice/GetAllowanceInvalid | 同上 | `scripts/SDK_PHP/example/Invoice/B2B/GetAllowanceInvalid.php` |
| 查詢折讓作廢確認 | /B2BInvoice/GetAllowanceInvalidConfirm | 同上 | `scripts/SDK_PHP/example/Invoice/B2B/GetAllowanceInvalidConfirm.php` |
| 查詢退回 | /B2BInvoice/GetReject | MerchantID, InvoiceNumber, InvoiceDate, Reason | `scripts/SDK_PHP/example/Invoice/B2B/GetReject.php` |
| 查詢退回確認 | /B2BInvoice/GetRejectConfirm | MerchantID, InvoiceCategory=0, InvoiceNumber, InvoiceDate | `scripts/SDK_PHP/example/Invoice/B2B/GetRejectConfirm.php` |

## 完整範例檔案對照（23 個）

| 檔案 | 用途 |
|------|------|
| `scripts/SDK_PHP/example/Invoice/B2B/Issue.php` | 開立 |
| `scripts/SDK_PHP/example/Invoice/B2B/IssueConfirm.php` | 確認開立 |
| `scripts/SDK_PHP/example/Invoice/B2B/Invalid.php` | 作廢 |
| `scripts/SDK_PHP/example/Invoice/B2B/InvalidConfirm.php` | 確認作廢 |
| `scripts/SDK_PHP/example/Invoice/B2B/Reject.php` | 退回 |
| `scripts/SDK_PHP/example/Invoice/B2B/RejectConfirm.php` | 確認退回 |
| `scripts/SDK_PHP/example/Invoice/B2B/Allowance.php` | 折讓 |
| `scripts/SDK_PHP/example/Invoice/B2B/AllowanceConfirm.php` | 確認折讓 |
| `scripts/SDK_PHP/example/Invoice/B2B/CancelAllowance.php` | 取消折讓 |
| `scripts/SDK_PHP/example/Invoice/B2B/CancelAllowanceConfirm.php` | 確認取消折讓 |
| `scripts/SDK_PHP/example/Invoice/B2B/Notify.php` | 通知 |
| `scripts/SDK_PHP/example/Invoice/B2B/MaintainMerchantCustomerData.php` | 交易對象維護 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetInvoiceWordSetting.php` | 字軌設定 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetIssue.php` | 查詢開立 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetIssueConfirm.php` | 查詢開立確認 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetInvalid.php` | 查詢作廢 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetInvalidConfirm.php` | 查詢作廢確認 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetAllowance.php` | 查詢折讓 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetAllowanceConfirm.php` | 查詢折讓確認 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetAllowanceInvalid.php` | 查詢折讓作廢 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetAllowanceInvalidConfirm.php` | 查詢折讓作廢確認 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetReject.php` | 查詢退回 |
| `scripts/SDK_PHP/example/Invoice/B2B/GetRejectConfirm.php` | 查詢退回確認 |

## 存證模式專屬章節

### 存證模式 vs 交換模式 詳細對照

| 比較項目 | 交換模式 | 存證模式 |
|---------|---------|---------|
| 發票傳遞方式 | 透過加值中心交換給買方 | 直接存證於財政部電子發票整合服務平台 |
| 買方確認流程 | 需要買方在加值中心確認接收 | 不需要買方確認 |
| Confirm/Reject API | 有（IssueConfirm, RejectConfirm 等） | **無** — 存證模式不含任何 Confirm/Reject API |
| 適用場景 | 大型企業對大型企業，雙方皆有加值中心帳號 | 一般企業交易、內部存證備查 |
| API 數量 | 較多（含 Confirm/Reject + 字軌管理共 30 個） | 較少（約 17 個，無確認/退回系列） |
| 發票生效時機 | 買方確認後生效 | 開立即生效 |
| 端點前綴 | `/B2BInvoice/` | `/B2BInvoice/`（路徑相同，但不含 Confirm/Reject 端點） |
| RqHeader | Timestamp + RqID + Revision `1.0.0` | 同交換模式 |
| 選擇建議 | 需要雙方確認的正式 B2B 交易 | 不確定時先用此模式，流程較簡單 |

### 存證模式不含的 API（僅交換模式使用）

以下 API 在存證模式中**不存在**，呼叫會回傳錯誤：

| 交換模式專屬 API | 說明 |
|-----------------|------|
| IssueConfirm / GetIssueConfirm | 確認開立 / 查詢確認開立 |
| Reject / GetReject | 退回發票 / 查詢退回 |
| RejectConfirm / GetRejectConfirm | 確認退回 / 查詢確認退回 |
| InvalidConfirm / GetInvalidConfirm | 確認作廢 / 查詢確認作廢 |
| AllowanceConfirm / GetAllowanceConfirm | 確認折讓 / 查詢確認折讓 |
| CancelAllowanceConfirm / GetAllowanceInvalidConfirm | 確認取消折讓 / 查詢折讓作廢確認 |

### 存證模式端點 URL 一覽

| 功能 | 端點路徑 |
|------|---------|
| 交易對象維護 | `/B2BInvoice/MaintainMerchantCustomerData` |
| 查詢財政部配號 | `/B2BInvoice/GetGovInvoiceWordSetting` |
| 字軌與配號設定 | `/B2BInvoice/InvoiceWordSetting` |
| 設定字軌號碼狀態 | `/B2BInvoice/UpdateInvoiceWordStatus` |
| 查詢字軌 | `/B2BInvoice/GetInvoiceWordSetting` |
| 開立發票 | `/B2BInvoice/Issue` |
| 作廢發票 | `/B2BInvoice/Invalid` |
| 折讓 | `/B2BInvoice/Allowance` |
| 作廢折讓 | `/B2BInvoice/AllowanceInvalid` |
| 註銷重開 | `/B2BInvoice/VoidWithReIssue` |
| 查詢發票 | `/B2BInvoice/GetIssue` |
| 查詢作廢發票 | `/B2BInvoice/GetInvalid` |
| 查詢折讓 | `/B2BInvoice/GetAllowance` |
| 查詢作廢折讓 | `/B2BInvoice/GetAllowanceInvalid` |
| 發送通知 | `/B2BInvoice/Notify` |
| 發票列印 | `/B2BInvoice/InvoicePrint` |
| 發票列印 PDF | `/B2BInvoice/InvoicePrintPDF` |

### 存證模式 — 開立發票範例

```php
$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv'  => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'         => '2000132',
        'RelateNumber'       => 'B2BATTEST' . time(),
        'CustomerIdentifier' => '12345678',   // 統一編號（8 碼）
        'CustomerEmail'      => 'company@example.com',
        'InvType'            => '07',
        'TaxType'            => '1',
        'Items'              => [
            [
                'ItemSeq'     => 1,
                'ItemName'    => '存證模式商品',
                'ItemCount'   => 5,
                'ItemPrice'   => 200,
                'ItemTaxType' => '1',
                'ItemAmount'  => 1000,
            ],
        ],
        'SalesAmount' => 1000,   // 未稅金額（= ItemAmount 之和）
        'TaxAmount'   => 50,     // 稅額（= round(1000 * 0.05)）
        'TotalAmount' => 1050,   // 含稅總額（= SalesAmount + TaxAmount）
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Issue');
    // 存證模式開立後即生效，不需要買方確認
} catch (\Exception $e) {
    error_log('ECPay B2B Attestation Issue Error: ' . $e->getMessage());
}
```

> **與交換模式差異**：存證模式開立後即生效，無需呼叫 IssueConfirm，也沒有被 Reject 的可能。

### 存證模式 — 折讓範例

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'  => '2000132',
        'TaxAmount'   => 5,
        'TotalAmount' => 100,
        'Details'     => [
            [
                'OriginalInvoiceNumber' => 'AB12345678',
                'OriginalInvoiceDate'   => '2026-01-15',
                'ItemName'              => '存證折讓商品',
                'OriginalSequenceNumber'=> 1,
                'ItemCount'             => 1,
                'ItemPrice'             => 100,
                'ItemAmount'            => 100,
            ],
        ],
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Allowance');
    // 存證模式折讓直接生效，不需要 AllowanceConfirm
} catch (\Exception $e) {
    error_log('ECPay B2B Attestation Allowance Error: ' . $e->getMessage());
}
```

### 存證模式 — 作廢發票範例

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'    => '2000132',
        'InvoiceNumber' => 'AB12345678',
        'InvoiceDate'   => '2026-01-15',
        'Reason'        => '開立資料錯誤',
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Invalid');
    // 存證模式作廢直接生效，不需要 InvalidConfirm
} catch (\Exception $e) {
    error_log('ECPay B2B Attestation Invalid Error: ' . $e->getMessage());
}
```

### 存證模式 — 作廢折讓範例

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => [
        'Timestamp' => time(),
        'RqID'      => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'  => '2000132',
        'AllowanceNo' => '折讓編號',
        'Reason'      => '折讓金額錯誤',
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/AllowanceInvalid');
} catch (\Exception $e) {
    error_log('ECPay B2B Attestation AllowanceInvalid Error: ' . $e->getMessage());
}
```

### 存證模式選擇建議

- 大型企業對大型企業，雙方皆有加值中心帳號 → **交換模式**
- 一般企業交易、內部存證需求 → **存證模式**（流程較簡單）
- 不確定該選哪個 → **先用存證模式**，後續需要再切換交換模式

## 相關文件

- 交換模式 API：`references/Invoice/B2B電子發票API技術文件_交換模式.md`（36 個 URL）
- 存證模式 API：`references/Invoice/B2B電子發票API技術文件_存證模式.md`（25 個 URL）
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)

