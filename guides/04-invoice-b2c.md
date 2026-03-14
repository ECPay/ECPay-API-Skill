> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# B2C 電子發票完整指南

> **讀對指南了嗎？** 企業對企業開票 → [guides/05 B2B 發票](./05-invoice-b2b.md)。無網路環境 → [guides/19 離線發票](./19-invoice-offline.md)。需要串金流而非發票 → [guides/01 AIO](./01-payment-aio.md) 或 [guides/02 ECPG](./02-payment-ecpg.md)。

## 概述

B2C 電子發票適用於**賣給消費者**的情境。支援手機條碼載具、自然人憑證、綠界會員載具、捐贈（愛心碼）等。使用 AES 加密 + JSON 格式。

### ⚠️ AES-JSON 開發者必讀：雙層錯誤檢查

B2C 發票（以及所有 AES-JSON 服務）的回應為**三層 JSON** 結構。**必須做兩次檢查**：

1. 檢查外層 `TransCode === 1`（否則 AES 加密/格式有問題，無需解密 Data）
2. 解密 Data 後，檢查內層 `RtnCode === 1`（業務邏輯問題）

只檢查其中一層會導致錯誤漏檢。完整錯誤碼參考見 [guides/21](./21-error-codes-reference.md)。

## 前置需求

- MerchantID / HashKey / HashIV（測試：2000132 / ejCk326UnaZWKisg / q9jcZX8Ib9LM8wYk）
- PHP SDK：`composer require ecpay/sdk`
- SDK Service：`PostWithAesJsonResponseService`
- 基礎端點：`https://einvoice-stage.ecpay.com.tw/B2CInvoice/`

## 🚀 首次串接：最快成功路徑

> 第一次串接 B2C 電子發票？從這裡開始，目標是開立**一張即時應稅 B2C 發票**。

### 前置確認清單

- [ ] ⚠️ **發票測試帳號與金流不同**：MerchantID `2000132` / HashKey `ejCk326UnaZWKisg` / HashIV `q9jcZX8Ib9LM8wYk`（不可用 AIO 的 3002607 帳號）
- [ ] AES-128-CBC 加密已實作，或使用 PHP SDK 的 `PostWithAesJsonResponseService`
- [ ] 了解三層 JSON 結構（外層 TransCode → 解密 Data → 內層 RtnCode），必須做**兩次**錯誤檢查
- [ ] `Revision` 固定填 `"3.0.0"`（空白或忘記填會導致 TransCode ≠ 1）

### Issue（開立發票）必填欄位速查

> 📋 以下為 B2C 發票 Issue API 最常用必填欄位。詳細規格請 web_fetch `references/Invoice/B2C電子發票API技術文件.md`。

| 欄位 | 類型 | 必填？ | 說明 / 常見陷阱 |
|------|------|:------:|----------------|
| `MerchantID`（Data 內） | String(10) | ✅ 必填 | 外層也有一個，**兩處都要填** |
| `RelateNumber` | String(50) | ✅ 必填 | 特店自訂編號，每次唯一；英數字 |
| `CustomerPhone` | String(20) | ✅ Phone/Email 擇一 | 手機號碼或 Email 至少填一個 |
| `CustomerEmail` | String(80) | ✅ Phone/Email 擇一 | 格式需符合 email 正規表達式；僅能填一組 |
| `Print` | String(1) | ✅ 必填 | `'0'`=不列印（雲端）`'1'`=列印紙本 |
| `Donation` | String(1) | ✅ 必填 | `'0'`=不捐贈 `'1'`=捐贈 |
| `CarrierType` | String(1) | ✅ 必填 | `''`=無載具 `'1'`=綠界載具（測試最簡單）`'2'`=自然人憑證 `'3'`=手機條碼 |
| `TaxType` | String(1) | ✅ 必填 | `'1'`=應稅 `'2'`=零稅率 `'3'`=免稅 `'9'`=混合 |
| `SalesAmount` | Int | ✅ 必填 | 發票金額（整數，**必須與 Items 總和相符**） |
| `Items` | Array | ✅ 必填 | 商品明細陣列（見下方） |
| `Items[].ItemName` | String(500) | ✅ 必填 | 商品名稱 |
| `Items[].ItemCount` | Number | ✅ 必填 | 商品數量（支援小數） |
| `Items[].ItemWord` | String(6) | ✅ 必填 | 單位（例如：`'件'`、`'個'`） |
| `Items[].ItemPrice` | Number | ✅ 必填 | 單價（支援小數） |
| `Items[].ItemTaxType` | String(1) | ⚠️ TaxType=9 時必填 | `'1'`=應稅 `'2'`=零稅率 `'3'`=免稅 |
| `Items[].ItemAmount` | Number | ✅ 必填 | 小計（ItemCount × ItemPrice，**加總需等於 SalesAmount**） |
| `InvType` | String(2) | ✅ 必填 | `'07'`=一般稅額計算 `'08'`=特種稅額計算 |

---

### 步驟 1：建立 AES-JSON 請求並開立發票

> 參考範例：`scripts/SDK_PHP/example/Invoice/B2C/Issue.php`

```php
$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv'  => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],  // Revision 必填
    'Data'       => [
        'MerchantID'    => '2000132',   // MerchantID 必須同時出現在外層和 Data 層
        'RelateNumber'  => 'Inv' . time(),
        'CustomerPhone' => '0912345678',  // Phone 或 Email 至少填一個
        'Print'         => '0',
        'Donation'      => '0',
        'CarrierType'   => '1',  // 1=綠界載具（測試最簡單）
        'TaxType'       => '1',  // 1=應稅
        'SalesAmount'   => 100,
        'Items'         => [[
            'ItemName'    => '測試商品',
            'ItemCount'   => 1,
            'ItemWord'    => '件',
            'ItemPrice'   => 100,
            'ItemTaxType' => '1',
            'ItemAmount'  => 100,
        ]],
        'InvType' => '07',  // 07=一般發票
    ],
];

try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue');
} catch (\Exception $e) {
    error_log('ECPay Invoice Error: ' . $e->getMessage());
}
```

```python
# Python — 開立 B2C 電子發票（AES-JSON）
import json, time, base64
from urllib.parse import quote_plus, unquote_plus
import requests as req
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

MERCHANT_ID = '2000132'
HASH_KEY    = 'ejCk326UnaZWKisg'   # ⚠️ 發票帳號，不同於金流帳號
HASH_IV     = 'q9jcZX8Ib9LM8wYk'

def aes_encrypt(data: dict) -> str:
    # 1. JSON encode（PHP json_encode 預設將非 ASCII 轉為 \uXXXX，Python ensure_ascii=False 保留原字元；
    #    ECPay 伺服器兩種格式均可接受，但若需與 PHP 完全一致可改 ensure_ascii=True）
    json_str = json.dumps(data, separators=(',', ':'), ensure_ascii=False)
    # 2. URL encode（空格→+，~ 需手動補；對應 PHP urlencode）
    url_encoded = quote_plus(json_str).replace('~', '%7E')
    # 3. AES-128-CBC + PKCS7（key/iv 取前 16 bytes）
    key = HASH_KEY.encode('utf-8')[:16]
    iv  = HASH_IV.encode('utf-8')[:16]
    cipher = AES.new(key, AES.MODE_CBC, iv)
    return base64.b64encode(cipher.encrypt(pad(url_encoded.encode('utf-8'), 16))).decode('utf-8')

def aes_decrypt(cipher_b64: str) -> dict:
    # 1. Base64 decode → 2. AES decrypt → 3. URL decode → 4. JSON decode
    ct = base64.b64decode(cipher_b64)
    key = HASH_KEY.encode('utf-8')[:16]
    iv  = HASH_IV.encode('utf-8')[:16]
    decipher = AES.new(key, AES.MODE_CBC, iv)
    decrypted = unpad(decipher.decrypt(ct), 16).decode('utf-8')
    return json.loads(unquote_plus(decrypted))

def post_invoice(url: str, data: dict) -> dict:
    body = {
        'MerchantID': MERCHANT_ID,
        'RqHeader':   {'Timestamp': int(time.time()), 'Revision': '3.0.0'},  # ⚠️ Revision 必填
        'Data':       aes_encrypt(data),
    }
    r = req.post(url, json=body, timeout=10)
    res = r.json()
    if res.get('TransCode') != 1:
        raise ValueError(f"AES/格式錯誤: {res.get('TransMsg')}")
    return aes_decrypt(res['Data'])

# 開立發票
data = post_invoice('https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue', {
    'MerchantID':    MERCHANT_ID,     # ⚠️ Data 層也需填 MerchantID
    'RelateNumber':  'Inv' + str(int(time.time())),
    'CustomerPhone': '0912345678',    # Phone 或 Email 至少填一個
    'Print':         '0',
    'Donation':      '0',
    'CarrierType':   '1',             # 1=綠界載具（測試最簡單）
    'TaxType':       '1',             # 1=應稅
    'SalesAmount':   100,
    'Items':         [{'ItemName':'測試商品','ItemCount':1,'ItemWord':'件','ItemPrice':100,'ItemTaxType':'1','ItemAmount':100}],
    'InvType':       '07',
})
if data.get('RtnCode') == 1:
    print(f"✅ 發票開立成功！發票號碼：{data['InvoiceNo']}")  # 例如 QQ00000001
else:
    print(f"❌ 業務錯誤 RtnCode={data.get('RtnCode')} RtnMsg={data.get('RtnMsg')}")
```

```typescript
// Node.js / TypeScript — 開立 B2C 電子發票（npm install axios；crypto 為內建）
import axios from 'axios';
import * as crypto from 'crypto';

const MERCHANT_ID = '2000132';
const HASH_KEY = Buffer.from('ejCk326UnaZWKisg');  // ⚠️ 發票帳號，不同於金流帳號
const HASH_IV  = Buffer.from('q9jcZX8Ib9LM8wYk');

function aesEncrypt(data: object): string {
    // aesUrlEncode：encodeURIComponent + %20→+ + ~→%7E + 補足 PHP urlencode 範圍
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

async function postInvoice(url: string, data: object): Promise<any> {
    const resp = await axios.post(url, {
        MerchantID: MERCHANT_ID,
        RqHeader: {
            Timestamp: Math.floor(Date.now() / 1000),
            Revision: '3.0.0',   // ⚠️ B2C 固定 3.0.0；不可省略
        },
        Data: aesEncrypt(data),
    });
    if (resp.data.TransCode !== 1)
        throw new Error(`AES/格式錯誤: ${resp.data.TransMsg}`);
    return aesDecrypt(resp.data.Data);
}

// 開立發票
postInvoice('https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue', {
    MerchantID:    MERCHANT_ID,     // ⚠️ Data 層也需填 MerchantID
    RelateNumber:  'Inv' + Math.floor(Date.now() / 1000),
    CustomerPhone: '0912345678',    // Phone 或 Email 至少填一個
    Print:         '0',
    Donation:      '0',
    CarrierType:   '1',            // 1=綠界載具（測試最簡單）
    TaxType:       '1',            // 1=應稅
    SalesAmount:   100,
    Items: [{ ItemName: '測試商品', ItemCount: 1, ItemWord: '件',
               ItemPrice: 100, ItemTaxType: '1', ItemAmount: 100 }],
    InvType: '07',
}).then(data => {
    if (data.RtnCode === 1)
        console.log(`✅ 發票開立成功！發票號碼：${data.InvoiceNo}`);
    else
        console.error(`❌ 業務錯誤 RtnCode=${data.RtnCode} RtnMsg=${data.RtnMsg}`);
}).catch(console.error);
```

> ✅ **成功時的預期輸出**：
> ```
> ✅ 發票開立成功！發票號碼：QQ00000001
> ```
> 發票號碼格式：兩個英文字母 + 8位數字（例如 `QQ00000001`）。若未看到此格式，確認 `TransCode` 是否為 `1`。

> 🔍 **此步驟失敗？**
>
> | 症狀 | 最可能原因 |
> |------|----------|
> | TransCode ≠ 1（AES 格式問題） | `Revision` 遺漏；MerchantID 只填了一層；AES Key/IV 錯誤 |
> | 報錯「cannot decrypt」 | HashKey/HashIV 填錯（確認用發票專屬帳號，不是金流帳號） |
> | `SalesAmount` 與 Items 金額不符 | Items 中各 `ItemAmount` 加總必須等於 `SalesAmount` |

---

### 步驟 2：解析雙層回應

```php
// 外層：先確認 TransCode
if (($response['TransCode'] ?? null) !== 1) {
    error_log('AES/格式錯誤: ' . ($response['TransMsg'] ?? 'unknown'));
    // 不要繼續解密 Data
    return;
}

// 內層：解密 Data，再確認 RtnCode
$data = $response['Data'];  // SDK 自動解密後為陣列
if (($data['RtnCode'] ?? null) !== 1) {
    error_log('業務錯誤: ' . ($data['RtnMsg'] ?? 'unknown'));
    return;
}

// 成功！取得發票號碼
$invoiceNo = $data['InvoiceNo'];  // 例如：QQ00000001
```

> 🔍 **此步驟失敗？**
>
> | 症狀 | 最可能原因 |
> |------|----------|
> | RtnCode = 10000009 | `RelateNumber` 重複（同一帳號不可重用） |
> | RtnCode = 10000002 | 必填欄位遺漏（Phone/Email 都沒填，或 Items 格式錯誤） |
> | InvoiceNo 空白 | 在測試環境確認 API 端點（einvoice-stage，非 einvoice） |

---

### 首次串接常見失誤

| 錯誤 | 解法 |
|------|------|
| 用了金流帳號（3002607）開發票 | 發票與金流帳號**完全獨立**，必須用 2000132 |
| 只看一層錯誤碼 | 必須先看 TransCode（格式），再解密看 RtnCode（業務） |
| Items 金額計算錯誤 | ItemAmount = ItemCount × ItemPrice；SalesAmount 必須等於所有 ItemAmount 加總 |
| Revision 遺漏 | B2C 發票 Revision 固定 `"3.0.0"`（站內付2.0 的 RqHeader 沒有 Revision） |

---

## AES 請求格式

與站內付 2.0 相同的三層結構，但 Revision 固定為 `3.0.0`：

```json
{
  "MerchantID": "2000132",
  "RqHeader": {
    "Timestamp": 1234567890,
    "Revision": "3.0.0"
  },
  "Data": "AES加密後的Base64字串"
}
```

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式 | AES-JSON — 詳見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證 | AES-128-CBC 加密 Data 欄位 — 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| 測試環境 | `https://einvoice-stage.ecpay.com.tw` |
| 正式環境 | `https://einvoice.ecpay.com.tw` |
| Revision | `3.0.0` |
| 回應結構 | 三層 JSON（TransCode → 解密 Data → RtnCode） |

### 端點 URL 一覽

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
| 線上折讓 | `/B2CInvoice/AllowanceByCollegiate`（⚠️ Callback 含 CheckMacValue MD5，見下方說明） |
| 作廢發票 | `/B2CInvoice/Invalid` |
| 作廢折讓 | `/B2CInvoice/AllowanceInvalid` |
| 取消線上折讓 | `/B2CInvoice/CancelAllowance` |
| 註銷重開 | `/B2CInvoice/VoidWithReIssue` |
| 查詢發票明細 | `/B2CInvoice/GetIssue` |
| 依關聯編號查詢 | `/B2CInvoice/GetIssueByRelateNo`（⚠️ 此端點未列於官方技術文件目錄，**建議改用 `GetIssueList` 查詢**，如需使用請先向綠界確認） |
| 查詢特定多筆發票 | `/B2CInvoice/GetIssueList` |
| 查詢折讓明細 | `/B2CInvoice/GetAllowance` |
| 查詢作廢發票 | `/B2CInvoice/GetInvalid` |
| 查詢作廢折讓 | `/B2CInvoice/GetAllowanceInvalid` |
| 發送通知 | `/B2CInvoice/InvoiceNotify` |
| 發票列印 | `/B2CInvoice/InvoicePrint` |
| 統一編號驗證 | `/B2CInvoice/CheckCompanyIdentifier` |
| 手機條碼驗證 | `/B2CInvoice/CheckBarcode` |
| 捐贈碼驗證 | `/B2CInvoice/CheckLoveCode` |

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Invoice/B2C電子發票介接技術文件.md`
> 以上端點及後續參數表僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

## 開立發票

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/Issue.php`

```php
$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv'  => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'    => '2000132',
        'RelateNumber'  => 'Inv' . time(),     // 自訂關聯編號
        'CustomerPhone' => '0912345678',        // CustomerPhone 或 CustomerEmail 至少填一個
        'Print'         => '0',                 // 0=不列印, 1=列印
        'Donation'      => '0',                 // 0=不捐贈, 1=捐贈
        'CarrierType'   => '1',                 // 載具類型（見下表）
        'TaxType'       => '1',                 // 稅別（見下表）
        'SalesAmount'   => 100,
        'Items'         => [
            [
                'ItemName'    => '測試商品',
                'ItemCount'   => 1,
                'ItemWord'    => '件',
                'ItemPrice'   => 100,
                'ItemTaxType' => '1',
                'ItemAmount'  => 100,
            ],
        ],
        'InvType' => '07',                     // 07=一般, 08=特種
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue');
    // 成功時 Data 包含 InvoiceNo（發票號碼）
} catch (\Exception $e) {
    error_log('ECPay Invoice Issue Error: ' . $e->getMessage());
}
```

> 🔍 **此步驟失敗？** TransCode ≠ 1 → AES 加密問題，確認 Revision=`"3.0.0"` 且 MerchantID 在外層和 Data 層都有填；RtnCode ≠ 1 → 業務錯誤，查 [guides/21 §B2C發票錯誤碼](./21-error-codes-reference.md)；Items 金額錯誤是最常見原因（ItemAmount 加總必須等於 SalesAmount）。

### 載具類型（CarrierType）

| 值 | 說明 |
|----|------|
| （空白） | 無載具（紙本或捐贈） |
| 1 | 綠界科技電子發票載具 |
| 2 | 自然人憑證條碼 |
| 3 | 手機條碼 |
| 4 | 悠遊卡 |
| 5 | 一卡通 |

### 稅別（TaxType）

| 值 | 說明 |
|----|------|
| 1 | 應稅 |
| 2 | 零稅率 |
| 3 | 免稅 |
| 4 | 特種應稅（InvType=08 時使用） |
| 9 | 混合稅率（Items 中各項目分別指定 ItemTaxType） |

### 發票類型（InvType）

| 值 | 說明 |
|----|------|
| 07 | 一般稅額 |
| 08 | 特種稅額 |

### Items 欄位

| 欄位 | 說明 |
|------|------|
| ItemName | 商品名稱 |
| ItemCount | 數量 |
| ItemWord | 單位（件、個、組…） |
| ItemPrice | 單價 |
| ItemTaxType | 該項稅別（與 TaxType 對應） |
| ItemAmount | 小計（ItemCount × ItemPrice） |

## 延遲開立

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/DelayIssue.php`

適用場景：等付款確認後再正式開立。先建立發票資料，待觸發條件成立後自動開立。

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'    => '2000132',
        'RelateNumber'  => 'Delay' . time(),
        'CustomerName'  => '測試客戶',
        'CustomerAddr'  => '測試地址',
        'CustomerEmail' => 'test@example.com',
        'Print'         => '1',
        'Donation'      => '0',
        'TaxType'       => '1',
        'SalesAmount'   => 100,
        'Items'         => [/* 同上 */],
        'InvType'       => '07',
        'DelayFlag'     => '1',         // 1=延遲開立
        'DelayDay'      => 15,          // 延遲天數
        'Tsr'           => 'tsr' . time(), // 交易序號
        'PayType'       => '2',         // 2=綠界金流
        'PayAct'        => 'ECPAY',
        'NotifyURL'     => 'https://你的網站/ecpay/invoice-notify',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/DelayIssue');
```

### 觸發延遲開立

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/TriggerIssue.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID' => '2000132',
        'Tsr'        => '之前的交易序號',
        'PayType'    => '2',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/TriggerIssue');
```

### 取消延遲開立

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/CancelDelayIssue.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => ['MerchantID' => '2000132', 'Tsr' => '之前的交易序號'],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/CancelDelayIssue');
```

### 編輯延遲開立

修改已建立但尚未觸發的延遲開立發票內容。端點：`/B2CInvoice/EditDelayIssue`。
參數與 DelayIssue 相同，額外需帶入原始的 `RelateNumber` 以識別要編輯的發票。
詳細參數見 [B2C 電子發票介接技術文件](../references/Invoice/B2C電子發票介接技術文件.md)。

### 開立回應處理

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/GetInvoicedResponse.php`

延遲開立成功後，綠界會 POST 通知到 NotifyURL：

```php
use Ecpay\Sdk\Response\ArrayResponse;
$arrayResponse = $factory->create(ArrayResponse::class);
$result = $arrayResponse->get($_POST);
```

## 作廢發票

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/Invalid.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'  => '2000132',
        'InvoiceNo'   => 'AB12345678',
        'InvoiceDate' => '2025-01-15',
        'Reason'      => '客戶退貨',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Invalid');
```

### 作廢重開

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/VoidWithReIssue.php`

一次完成作廢舊發票 + 開立新發票：

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'VoidModel' => [
            'MerchantID' => '2000132',
            'InvoiceNo'  => 'AB12345678',
            'VoidReason' => '金額錯誤',
        ],
        'IssueModel' => [
            'MerchantID'   => '2000132',
            'RelateNumber' => 'ReIssue' . time(),
            'InvoiceDate'  => date('Y-m-d H:i:s'),
            'CustomerEmail'=> 'test@example.com',
            'Print'        => '0',
            'Donation'     => '0',
            'TaxType'      => '1',
            'SalesAmount'  => 200,
            'Items'        => [/* 新的商品清單 */],
            'InvType'      => '07',
        ],
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/VoidWithReIssue');
```

## 折讓（退款部分金額）

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/Allowance.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'      => '2000132',
        'InvoiceNo'       => 'AB12345678',
        'InvoiceDate'     => '2025-01-15',
        'AllowanceNotify' => 'E',           // E=Email, S=SMS, A=全部, N=不通知
        'NotifyMail'      => 'test@example.com',
        'AllowanceAmount' => 50,
        'Items'           => [
            [
                'ItemSeq'    => 1,
                'ItemName'   => '退款商品',
                'ItemCount'  => 1,
                'ItemWord'   => '件',
                'ItemPrice'  => 50,
                'ItemAmount' => 50,
            ],
        ],
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Allowance');
```

### 線上折讓（通知開立）

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/AllowanceByCollegiate.php`

與一般折讓相同，但多一個 `ReturnURL` 參數，結果非同步通知。

> 🔴 **重要安全警告：混合協議陷阱**
>
> `AllowanceByCollegiate` 是 B2C 發票中**唯一一個** Callback 包含 `CheckMacValue` 的 API，且使用 **MD5**（不是 SHA256、也不是 AES 加密），是整個 AES-JSON 發票服務中的例外：
> - **請求**：AES-JSON（和其他發票 API 相同）
> - **Callback**：Form POST + CheckMacValue **MD5**（和 AIO 金流格式相同，但用 MD5 不是 SHA256）
>
> 若未驗證 Callback 的 CheckMacValue，攻擊者可偽造折讓通知。**必須實作 MD5 驗證**（見下方 §折讓回應處理）。
>
> 非 PHP 語言實作 MD5 CheckMacValue（步驟、字元替換清單）見 **[guides/13 §計算流程](./13-checkmacvalue.md)**，對應 `generateHash(METHOD_MD5)` 分支。

```php
$data = [
    'MerchantID'      => '2000132',
    'InvoiceNo'       => 'AB12345678',
    'InvoiceDate'     => '2025-01-15',
    'AllowanceNotify' => 'E',
    'NotifyMail'      => 'test@example.com',
    'AllowanceAmount' => 50,
    'Items'           => [
        [
            'ItemSeq'    => 1,
            'ItemName'   => '退款商品',
            'ItemCount'  => 1,
            'ItemWord'   => '件',
            'ItemPrice'  => 50,
            'ItemAmount' => 50,
        ],
    ],
    // 學校折讓專屬：結果非同步通知到此 URL
    'ReturnURL' => 'https://你的網站/ecpay/allowance-collegiate-notify',
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => $data,
];

try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/AllowanceByCollegiate');
} catch (\Exception $e) {
    error_log('ECPay AllowanceByCollegiate Error: ' . $e->getMessage());
}
```

### 折讓回應處理（ReturnURL Callback）

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/GetAllowanceByCollegiateResponse.php`
>
> ⚠️ **注意**：SDK 原始範例僅示範接收 POST 並輸出資料，**不包含 `CheckMacValue` 驗證**。正式環境請依下方步驟自行驗證 MD5 檢查碼。

消費者同意折讓後，綠界以 **Form POST** 通知 ReturnURL，包含 `CheckMacValue`（MD5）：

```
ReturnURL Callback 參數：
RtnCode, RtnMsg, IA_Allow_No, IA_Invoice_No, IA_Date, IIS_Remain_Allowance_Amt, CheckMacValue
```

**CheckMacValue 驗證步驟**（與 AIO 金流相同公式，但用 MD5）：

1. 將回傳參數（不含 CheckMacValue）依英文字母 A-Z 排序，以 `&` 串連
2. 前加 `HashKey=...&`，後加 `&HashIV=...`
3. URL encode（使用 ecpay 規格，見 [guides/13](./13-checkmacvalue.md)）
4. 轉小寫
5. **MD5** 雜湊 → 轉大寫 = CheckMacValue

> ⚠️ **與 AIO 金流的差異**：AIO 金流 CheckMacValue 使用 SHA256，此 API 使用 **MD5**。不可混用。

驗證成功後回應純字串 `1|OK`。

```python
# Python / Flask — AllowanceByCollegiate ReturnURL Callback（CheckMacValue MD5）
import hmac, hashlib, urllib.parse
from flask import Flask, request

app = Flask(__name__)
HASH_KEY = 'ejCk326UnaZWKisg'
HASH_IV  = 'q9jcZX8Ib9LM8wYk'

def verify_allowance_cmv(params: dict) -> bool:
    """AllowanceByCollegiate 使用 MD5（不是 SHA256），需特別注意"""
    received = params.get('CheckMacValue', '')
    sorted_params = sorted(((k, v) for k, v in params.items() if k != 'CheckMacValue'), key=lambda x: x[0].lower())
    raw = f'HashKey={HASH_KEY}&' + '&'.join(f'{k}={v}' for k, v in sorted_params) + f'&HashIV={HASH_IV}'
    encoded = urllib.parse.quote_plus(raw).replace('~', '%7e').lower()
    for orig, repl in [('%2d','-'),('%5f','_'),('%2e','.'),('%21','!'),('%2a','*'),('%28','('),('%29',')')]:
        encoded = encoded.replace(orig, repl)
    # ⚠️ 發票折讓用 MD5（不是 AIO 的 SHA256）
    computed = hashlib.md5(encoded.encode()).hexdigest().upper()
    return hmac.compare_digest(computed, received.upper())  # timing-safe

@app.route('/ecpay/allowance-collegiate-notify', methods=['POST'])
def allowance_callback():
    params = {k: (v[0] if isinstance(v, list) else v) for k, v in request.form.items()}
    if not verify_allowance_cmv(params):
        return '', 400
    if params.get('RtnCode') == '1':
        print(f"[折讓] ✅ 折讓成功 折讓單號={params.get('IA_Allow_No')} 發票號={params.get('IA_Invoice_No')}")
        # TODO: 更新資料庫折讓狀態
    return '1|OK', 200, {'Content-Type': 'text/plain'}
```

### 折讓作廢

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/AllowanceInvalid.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'  => '2000132',
        'InvoiceNo'   => 'AB12345678',
        'AllowanceNo' => '折讓編號',
        'Reason'      => '折讓金額錯誤',
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/AllowanceInvalid');
```

## 查驗

### 查驗手機條碼

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/CheckBarcode.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => ['MerchantID' => '2000132', 'BarCode' => '/1234567'],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/CheckBarcode');
```

### 查驗愛心碼

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/CheckLoveCode.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => ['MerchantID' => '2000132', 'LoveCode' => '168001'],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/CheckLoveCode');
```

## 查詢

### 查詢發票

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/GetIssue.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => ['MerchantID' => '2000132', 'InvoiceNo' => 'AB12345678', 'InvoiceDate' => '2025-01-15'],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/GetIssue');
```

### 查詢折讓

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/GetAllowance.php`

端點：`POST /B2CInvoice/GetAllowance`，Data：`MerchantID, InvoiceNo, AllowanceNo`

### 查詢折讓作廢

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/GetAllowanceInvalid.php`

端點：`POST /B2CInvoice/GetAllowanceInvalid`，Data：`MerchantID, InvoiceNo, AllowanceNo`

### 查詢作廢

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/GetInvalid.php`

端點：`POST /B2CInvoice/GetInvalid`，Data：`MerchantID, RelateNumber, InvoiceNo, InvoiceDate`

### 查詢特定多筆發票

端點：`POST /B2CInvoice/GetIssueList`

以日期區間批次查詢多筆發票，支援分頁與多種篩選條件。適合對帳或批次匯出場景。

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'  => '2000132',
        'BeginDate'   => '2025-01-01',
        'EndDate'     => '2025-01-31',
        'NumPerPage'  => 10,           // 每頁筆數
        'ShowingPage' => 1,            // 顯示頁碼
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/GetIssueList');
    // Data 包含 TotalCount（總筆數）、InvoiceData（發票陣列）
} catch (\Exception $e) {
    error_log('ECPay GetIssueList Error: ' . $e->getMessage());
}
```

> 完整篩選參數（Query_Award、Query_Invalid 等）見 `references/Invoice/B2C電子發票介接技術文件.md` → 查詢特定多筆發票。

## 發票通知

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/InvoiceNotify.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID' => '2000132',
        'InvoiceNo'  => 'AB12345678',
        'NotifyMail' => 'test@example.com',
        'Notify'     => 'E',         // E=Email, S=SMS, A=全部
        'InvoiceTag' => 'I',         // I=開立, II=作廢, A=折讓, AI=折讓作廢, AW=折讓中獎
        'Notified'   => 'C',         // C=已通知, N=未通知
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/InvoiceNotify');
```

## 字軌設定查詢

> 原始範例：`scripts/SDK_PHP/example/Invoice/B2C/GetInvoiceWordSetting.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'      => '2000132',
        'InvoiceYear'     => '109',     // 民國年
        'InvoiceTerm'     => 0,         // 0=全部, 1=一月, 2=三月...
        'UseStatus'       => 0,         // 0=全部, 1=已使用, 2=未使用
        'InvoiceCategory' => 1,         // 1=B2C
    ],
];
$response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/GetInvoiceWordSetting');
```

## 法規提醒

- 電子發票開立後需在 **48 小時內**上傳財政部
- 捐贈發票不可作廢
- 已折讓的發票需先作廢折讓才能作廢發票

## 完整範例檔案對照（19 個）

| 檔案 | 用途 | 端點 |
|------|------|------|
| Issue.php | 開立發票 | /B2CInvoice/Issue |
| DelayIssue.php | 延遲開立 | /B2CInvoice/DelayIssue |
| TriggerIssue.php | 觸發延遲開立 | /B2CInvoice/TriggerIssue |
| CancelDelayIssue.php | 取消延遲開立 | /B2CInvoice/CancelDelayIssue |
| Invalid.php | 作廢 | /B2CInvoice/Invalid |
| VoidWithReIssue.php | 作廢重開 | /B2CInvoice/VoidWithReIssue |
| Allowance.php | 折讓 | /B2CInvoice/Allowance |
| AllowanceByCollegiate.php | 學校折讓 | /B2CInvoice/AllowanceByCollegiate |
| AllowanceInvalid.php | 折讓作廢 | /B2CInvoice/AllowanceInvalid |
| CheckBarcode.php | 查驗條碼 | /B2CInvoice/CheckBarcode |
| CheckLoveCode.php | 查驗愛心碼 | /B2CInvoice/CheckLoveCode |
| GetIssue.php | 查詢發票 | /B2CInvoice/GetIssue |
| GetAllowance.php | 查詢折讓 | /B2CInvoice/GetAllowance |
| GetAllowanceInvalid.php | 查詢折讓作廢 | /B2CInvoice/GetAllowanceInvalid |
| GetInvalid.php | 查詢作廢 | /B2CInvoice/GetInvalid |
| InvoiceNotify.php | 發票通知 | /B2CInvoice/InvoiceNotify |
| GetInvoiceWordSetting.php | 字軌設定 | /B2CInvoice/GetInvoiceWordSetting |
| GetInvoicedResponse.php | 開立回應處理 | — |
| GetAllowanceByCollegiateResponse.php | 學校折讓回應 | — |

> ⚠️ **安全必做清單**
> 1. 驗證 MerchantID 為自己的
> 2. 防重複處理（記錄已處理的 InvoiceNo）
> 3. 記錄完整日誌（遮蔽 HashKey/HashIV）
> 4. 折讓 Callback 的 CheckMacValue 驗證**必須**使用 timing-safe 比較函式（見 [guides/13](./13-checkmacvalue.md) 各語言實作），禁止使用 `==` 或 `===` 直接比對

## 查詢財政部配號

端點：`POST /B2CInvoice/GetGovInvoiceWordSetting`

查詢財政部核發的字軌配號結果，確認可使用的發票號碼區間。

### 參數說明

| 參數 | 必填 | 說明 |
|------|------|------|
| MerchantID | 是 | 特店代號 |
| InvoiceYear | 是 | 民國年（例：`113`） |
| InvoiceTerm | 否 | 期數，0=全部、1=一月…6=十一月 |

```php
$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv'  => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'  => '2000132',
        'InvoiceYear' => '113',
        'InvoiceTerm' => 0,         // 0=全部
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/GetGovInvoiceWordSetting');
    // Data 包含財政部核發的字軌配號清單
} catch (\Exception $e) {
    error_log('ECPay GetGovInvoiceWordSetting Error: ' . $e->getMessage());
}
```

## 字軌與配號設定

端點：`POST /B2CInvoice/InvoiceWordSetting`

設定特店使用的發票字軌與號碼區間，需先透過 `GetGovInvoiceWordSetting` 查詢財政部核發的配號。

### 參數說明

| 參數 | 必填 | 說明 |
|------|------|------|
| MerchantID | 是 | 特店代號 |
| InvoiceYear | 是 | 民國年（例：`113`） |
| InvoiceTerm | 是 | 期數（1=一月、2=三月…6=十一月） |
| InvoiceHeader | 是 | 字軌英文字頭（例：`AB`） |
| InvoiceStart | 是 | 起始號碼（例：`00000001`） |
| InvoiceEnd | 是 | 結束號碼（例：`00000050`） |
| InvoiceCategory | 是 | 1=B2C |

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'      => '2000132',
        'InvoiceYear'     => '113',
        'InvoiceTerm'     => 1,              // 1=一月
        'InvoiceHeader'   => 'AB',
        'InvoiceStart'    => '00000001',
        'InvoiceEnd'      => '00000050',
        'InvoiceCategory' => 1,              // 1=B2C
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/InvoiceWordSetting');
} catch (\Exception $e) {
    error_log('ECPay InvoiceWordSetting Error: ' . $e->getMessage());
}
```

## 設定字軌號碼狀態

端點：`POST /B2CInvoice/UpdateInvoiceWordStatus`

啟用或停用已設定的字軌號碼區間。

### 參數說明

| 參數 | 必填 | 說明 |
|------|------|------|
| MerchantID | 是 | 特店代號 |
| InvoiceYear | 是 | 民國年（例：`113`） |
| InvoiceTerm | 是 | 期數 |
| InvoiceHeader | 是 | 字軌英文字頭 |
| InvoiceStart | 是 | 起始號碼 |
| InvoiceEnd | 是 | 結束號碼 |
| Status | 是 | 狀態（`0`=停用、`1`=啟用） |
| InvoiceCategory | 是 | 1=B2C |

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'      => '2000132',
        'InvoiceYear'     => '113',
        'InvoiceTerm'     => 1,
        'InvoiceHeader'   => 'AB',
        'InvoiceStart'    => '00000001',
        'InvoiceEnd'      => '00000050',
        'Status'          => '1',            // 1=啟用, 0=停用
        'InvoiceCategory' => 1,              // 1=B2C
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/UpdateInvoiceWordStatus');
} catch (\Exception $e) {
    error_log('ECPay UpdateInvoiceWordStatus Error: ' . $e->getMessage());
}
```

## 依關聯編號查詢發票（非官方端點）

> ⚠️ **此端點未列於官方技術文件目錄，亦無 PHP SDK 範例支援，使用前請先向綠界確認可用性。建議優先使用 `/B2CInvoice/GetIssueList` 代替。**

端點：`POST /B2CInvoice/GetIssueByRelateNo`

以開立時指定的 `RelateNumber`（自訂關聯編號）查詢發票，適合用於以訂單編號反查發票。

### 參數說明

| 參數 | 必填 | 說明 |
|------|------|------|
| MerchantID | 是 | 特店代號 |
| RelateNumber | 是 | 開立發票時帶入的自訂關聯編號 |

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '3.0.0'],
    'Data'       => [
        'MerchantID'   => '2000132',
        'RelateNumber' => '你的訂單關聯編號',
    ],
];
try {
    $response = $postService->post($input, 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/GetIssueByRelateNo');
    // Data 包含 InvoiceNo（發票號碼）、InvoiceDate 等發票資訊
} catch (\Exception $e) {
    error_log('ECPay GetIssueByRelateNo Error: ' . $e->getMessage());
}
```

## 相關文件

- 官方 API 規格：`references/Invoice/B2C電子發票介接技術文件.md`（36 個 URL）
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- B2B 發票：[guides/05-invoice-b2b.md](./05-invoice-b2b.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
