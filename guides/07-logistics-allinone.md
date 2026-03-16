> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 全方位物流完整指南

## 概述

全方位物流（v2）是新版物流 API，使用 AES 加密 + JSON 格式（與 ECPG/發票相同），提供 RWD 響應式物流選擇介面。支援暫存訂單流程。

### ⚠️ AES-JSON 開發者必讀：雙層錯誤檢查

全方位物流使用 AES-JSON 協議，回應為三層 JSON 結構。**必須做兩次檢查**：

1. 檢查外層 `TransCode === 1`（否則 AES 加密/格式有問題）
2. 解密 Data 後，檢查內層 `RtnCode === 1`（業務邏輯問題）

> 全方位物流 v2 的 **callback 回應**也需要 AES 加密 JSON（三層結構），不同於國內物流的 `1|OK`。

完整錯誤碼參考見 [guides/21](./21-error-codes-reference.md)。

## 與國內物流差異

| 面向 | 國內物流 | 全方位物流 v2 |
|------|---------|-------------|
| 加密方式 | CheckMacValue MD5 | AES |
| 請求格式 | Form POST | JSON POST |
| 物流選擇 | 電子地圖選店 | RWD 頁面含選店 |
| 訂單流程 | 直接建單 | 暫存 → 更新 → 成立 |
| 端點前綴 | /Express/ | /Express/v2/ |

## 前置需求

- MerchantID / HashKey / HashIV（測試：2000132 / 5294y06JbISpM5x9 / v77hoKGq4kWxNNIS）
  > **⚠️ 帳號與國內物流（B2C）相同，但協議不同**：國內物流使用 CMV-MD5（Form POST），全方位物流 v2 使用 **AES-JSON**（JSON POST）。API 端點前綴也不同（`/Express/` vs `/Express/v2/`）。
- SDK Service：`PostWithAesJsonResponseService` 或 `PostWithAesStrResponseService`
- 基礎端點：`https://logistics-stage.ecpay.com.tw/Express/v2/`

```php
$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv'  => 'v77hoKGq4kWxNNIS',
    // 全方位物流 v2 使用 AES-JSON 協定，不需 hashMethod
]);
```

## 🚀 首次串接：最快成功路徑

> 第一次串接全方位物流？從這裡開始，目標是完成**一筆全家超商取貨的建單流程**（暫存訂單→選店→成立訂單）。

### 前置確認清單

- [ ] ⚠️ **全方位物流是 AES-JSON 協議**（與國內物流的 CMV-MD5 完全不同），使用 `PostWithAesJsonResponseService`
- [ ] ⚠️ **Callback 回應格式不同於金流**：全方位物流的 ServerReplyURL callback 回應**必須是 AES 加密 JSON**（三層結構），不是 `1|OK`
- [ ] ⚠️ **訂單是暫存流程**：先 `RedirectToLogisticsSelection`（建暫存+顯示選店頁面）→ 消費者選店 → `CreateByTempTrade`（成立正式訂單）
- [ ] `RqHeader` 有 `Revision: "1.0.0"`（與站內付2.0 不同，物流 v2 需要 Revision）
- [ ] `ServerReplyURL` 和 `ClientReplyURL` 均為公開可訪問的 URL（localhost 無效）

> ⚠️ ReturnURL / ServerReplyURL 僅支援 port 80/443，不可放在 CDN 後方。本機測試需使用 ngrok 等 tunnel。
- [ ] 先用全家超商（LogisticsSubType=FAMI）測試，流程最單純

---

### 步驟 1：產生物流選擇頁面（建立暫存訂單）

> 參考範例：`scripts/SDK_PHP/example/Logistics/AllInOne/RedirectToLogisticsSelection.php`

```php
$postService = $factory->create('PostWithAesStrResponseService');  // ← 注意：Str 不是 Json
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],  // ← Revision 必填
    'Data' => [
        'TempLogisticsID' => '0',          // 0=新建暫存訂單
        'GoodsAmount'     => 100,
        'GoodsName'       => '測試商品',
        'SenderName'      => '寄件人',
        'SenderZipCode'   => '106',
        'SenderAddress'   => '台北市大安區測試路1號',
        'ServerReplyURL'  => 'https://你的網站/ecpay/logistics-notify',  // ← 物流狀態 callback
        'ClientReplyURL'  => 'https://你的網站/ecpay/logistics-result',  // ← 消費者選店後的前端跳轉
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/RedirectToLogisticsSelection');
echo $response['body'];  // 輸出 HTML 重導向頁面（消費者進行選店）
```

> **⚠️ 步驟 1 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | `TransCode` ≠ 1 | Revision 缺少或 AES 加密失敗 | RqHeader 必須包含 `Revision: "1.0.0"` |
> | 頁面顯示但選店後沒有 POST 到 ClientReplyURL | ClientReplyURL 非公開 URL | 使用 ngrok 或部署到有公開 IP 的主機 |
> | 使用了 `PostWithAesJsonResponseService` | 此 API 回應不是 JSON | 改用 `PostWithAesStrResponseService`（回應是 HTML body） |

---

### 步驟 2：消費者選店後接收 ClientReplyURL 回調

消費者在物流選擇頁面選好門市後，綠界透過**瀏覽器 Form POST** 跳轉到 `ClientReplyURL`。

```php
// ClientReplyURL 處理
use Ecpay\Sdk\Response\AesJsonResponse;
$aesJsonResponse = $factory->create(AesJsonResponse::class);
$result = $aesJsonResponse->get($_POST['ResultData']);
// $result 包含：TempLogisticsID（建立正式訂單的必要欄位）
$tempLogisticsID = $result['TempLogisticsID'];
```

> **⚠️ 步驟 2 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | `$_POST['ResultData']` 為空 | ClientReplyURL 非公開 URL 或 URL 錯誤 | 確認 URL 可公開訪問，並用 ngrok 測試 |
> | AES 解密失敗 | HashKey/HashIV 與全方位物流帳號不符 | 全方位物流帳號：2000132 / 5294y06JbISpM5x9 / v77hoKGq4kWxNNIS |

---

### 步驟 3：成立正式訂單

> 參考範例：`scripts/SDK_PHP/example/Logistics/AllInOne/CreateByTempTrade.php`

> **CreateByTempTrade 必填欄位速查**
>
> | 欄位 | 必填？ | 說明 |
> |------|:------:|------|
> | `TempLogisticsID` | ✅ 必填 | 步驟 2 從 ClientReplyURL 回調取得 |
> | `MerchantTradeNo` | 否 | 自訂交易編號（選填；留空則系統自動產生）|
>
> > ℹ️ **為什麼這麼少欄位？** 收件人姓名、電話、地址等資訊由消費者在步驟 1 的物流選擇頁填寫，並已存入暫存訂單。CreateByTempTrade 只需憑 `TempLogisticsID` 將暫存訂單正式成立即可。

```php
$postService2 = $factory->create('PostWithAesJsonResponseService');
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data' => [
        'TempLogisticsID' => $tempLogisticsID,  // 步驟 2 取得的暫存物流 ID
        // 'MerchantTradeNo' => 'ORD' . time(),  // 選填：自訂交易編號
    ],
];
// PostWithAesJsonResponseService 內部自動驗證 TransCode，TransCode ≠ 1 時拋出 TransException（雙層檢查的第一層）
$response = $postService2->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/CreateByTempTrade');
// ✅ 成功：$response['Data']['RtnCode'] === 1（AES-JSON 解密後 RtnCode 為整數，必須 strict 比較）
$data = $response['Data'];
if (($data['RtnCode'] ?? null) === 1) {
    $logisticsID = $data['LogisticsID'];  // 物流訂單號
}
```

> **⚠️ 步驟 3 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | `TransCode` ≠ 1 | TempLogisticsID 無效或 AES 加密失敗 | 確認步驟 2 正確取得 TempLogisticsID，且在有效期內 |
> | `RtnCode` ≠ 1 | 暫存訂單問題或帳戶餘額不足 | 確認 TempLogisticsID 有效；確認綠界帳戶餘額充足供物流費扣款 |

**步驟 3 成功後應看到（Data 解密後）**：
```json
{
  "RtnCode": 1,
  "RtnMsg": "OK",
  "LogisticsID": "1234567890",
  "LogisticsType": "CVS",
  "LogisticsSubType": "FAMI"
}
```

---

### 步驟 4：接收物流狀態 Callback

> ⚠️ **全方位物流 ServerReplyURL 回應格式與金流不同！**
> 必須用 AES 加密 JSON 回應（三層結構），不是 `1|OK`。

```php
// ServerReplyURL 處理（JSON body POST）
use Ecpay\Sdk\Response\AesJsonResponse as AesParser;
$aesParser = $factory->create(AesParser::class);
$result = $aesParser->get(file_get_contents('php://input'));
// $result['Data'] 包含：RtnCode, LogisticsID, LogisticsStatus 等（SDK 自動驗證 TransCode 並解密 Data）

// ⚠️ 回應必須是 AES 加密 JSON，不是 1|OK
use Ecpay\Sdk\Services\AesService;
$aesService = $factory->create(AesService::class);
$responseBody = json_encode([
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time()],
    'TransCode'  => 1,
    'TransMsg'   => '',
    'Data'       => $aesService->encrypt(['RtnCode' => 1, 'RtnMsg' => '']),
]);
header('Content-Type: application/json');
echo $responseBody;
```

```python
# Python / Flask — ServerReplyURL 物流狀態通知（全方位物流 v2，AES-JSON）
# ⚠️ 與國內物流不同：ECPay 以 JSON body 傳送 callback，回應必須是 AES 加密 JSON
import time, base64, json
from urllib.parse import quote_plus, unquote_plus
from flask import Flask, request, Response
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

app = Flask(__name__)
HASH_KEY = '5294y06JbISpM5x9'   # 全方位物流使用物流帳號
HASH_IV  = 'v77hoKGq4kWxNNIS'
MERCHANT_ID = '2000132'

def aes_encrypt(data: dict) -> str:
    # 對應 AesService::encrypt()：1. JSON encode  2. URL encode  3. AES-128-CBC  4. Base64
    json_str = json.dumps(data, separators=(',', ':'))  # ensure_ascii=True (預設) 確保與 PHP json_encode 行為一致
    url_encoded = quote_plus(json_str).replace('~', '%7E')
    cipher = AES.new(HASH_KEY.encode(), AES.MODE_CBC, HASH_IV.encode())
    return base64.b64encode(cipher.encrypt(pad(url_encoded.encode('utf-8'), 16))).decode()

def aes_decrypt(cipher_b64: str) -> dict:
    # 對應 AesService::decrypt()：1. Base64 decode  2. AES decrypt  3. URL decode  4. JSON decode
    ct = base64.b64decode(cipher_b64)
    decipher = AES.new(HASH_KEY.encode(), AES.MODE_CBC, HASH_IV.encode())
    decrypted = unpad(decipher.decrypt(ct), 16)
    return json.loads(unquote_plus(decrypted.decode('utf-8')))

def make_aes_json_response(rtn_code=1, rtn_msg: str = '成功') -> str:
    """全方位物流 callback 回應必須是 AES 加密 JSON 三層結構"""
    return json.dumps({
        'MerchantID': MERCHANT_ID,
        'RqHeader':   {'Timestamp': int(time.time())},
        'TransCode':  1,
        'TransMsg':   '',
        'Data':       aes_encrypt({'RtnCode': rtn_code, 'RtnMsg': rtn_msg}),
    })

@app.route('/ecpay/logistics-notify', methods=['POST'])
def logistics_notify():
    # ⚠️ ECPay 以 JSON body 傳送 callback（不是 Form POST）；讀取 Data 欄位後 AES 解密
    body = request.get_json(force=True, silent=True) or {}
    # TransCode 由 ECPay 設定，應為 1（傳輸成功）
    if body.get('TransCode') != 1:
        return Response(make_aes_json_response(0, 'TransCode 錯誤'), content_type='application/json')
    encrypted_data = body.get('Data', '')
    try:
        result = aes_decrypt(encrypted_data)
    except Exception:
        # 解密失敗，仍需回應 AES-JSON（告知 ECPay 有錯誤）
        return Response(make_aes_json_response(0, '解密失敗'), content_type='application/json')

    rtn_code     = result.get('RtnCode', 0)
    logistics_id = result.get('LogisticsID', '')
    status       = result.get('LogisticsStatus', '')
    print(f'[物流通知] ID={logistics_id} RtnCode={rtn_code} 狀態={status}')

    if rtn_code == 1:
        pass  # TODO: 更新訂單物流狀態

    # ⚠️ 必須回應 AES 加密 JSON，不可回應 '1|OK'
    return Response(make_aes_json_response(1, '成功'), content_type='application/json')
```

```javascript
// Node.js / Express — ServerReplyURL 物流狀態通知（全方位物流 v2）
const express = require('express');
const crypto  = require('crypto');

const app = express();
app.use(express.json());

const HASH_KEY    = '5294y06JbISpM5x9';
const HASH_IV     = 'v77hoKGq4kWxNNIS';
const MERCHANT_ID = '2000132';

function aesDecrypt(cipherB64) {
  // 對應 AesService::decrypt()：Base64 → AES → URL decode → JSON parse
  const ct = Buffer.from(cipherB64, 'base64');
  const d = crypto.createDecipheriv('aes-128-cbc', HASH_KEY, HASH_IV);
  const decrypted = Buffer.concat([d.update(ct), d.final()]).toString('utf8');
  const urlDecoded = decodeURIComponent(decrypted.replace(/\+/g, '%20'));
  return JSON.parse(urlDecoded);
}
function aesEncrypt(data) {
  // 對應 AesService::encrypt()：JSON → URL encode → AES → Base64
  const jsonStr = JSON.stringify(data);
  const urlEncoded = encodeURIComponent(jsonStr)
    .replace(/%20/g, '+').replace(/~/g, '%7E')
    .replace(/!/g, '%21').replace(/'/g, '%27')
    .replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A');
  const c = crypto.createCipheriv('aes-128-cbc', HASH_KEY, HASH_IV);
  return Buffer.concat([c.update(urlEncoded, 'utf8'), c.final()]).toString('base64');
}
function makeAesJsonResponse(rtnCode = 1, rtnMsg = '成功') {
  return JSON.stringify({
    MerchantID: MERCHANT_ID,
    RqHeader:   { Timestamp: Math.floor(Date.now() / 1000) },
    TransCode:  1,
    TransMsg:   '',
    Data:       aesEncrypt({ RtnCode: rtnCode, RtnMsg: rtnMsg }),
  });
}

app.post('/ecpay/logistics-notify', (req, res) => {
  // ⚠️ ECPay 以 JSON body 傳送 callback（不是 Form POST）；讀取 Data 欄位後 AES 解密
  const body = req.body || {};
  // ⚠️ AES-JSON 雙層驗證：先查 TransCode（傳輸層），再解密 Data（業務層）
  if (Number(body.TransCode) !== 1) {
    return res.type('json').send(makeAesJsonResponse(0, `TransCode 錯誤: ${body.TransCode}`));
  }
  const encryptedData = body.Data || '';
  let result;
  try {
    result = aesDecrypt(encryptedData);
  } catch {
    return res.type('json').send(makeAesJsonResponse(0, '解密失敗'));
  }

  console.log(`[物流通知] ID=${result.LogisticsID} RtnCode=${result.RtnCode}`);
  if (result.RtnCode === 1) {
    // TODO: 更新訂單物流狀態
  }

  // ⚠️ 必須回應 AES-JSON，不可用 '1|OK'（否則 ECPay 視為失敗並重送）
  res.type('json').send(makeAesJsonResponse(1, '成功'));
});
```

> **⚠️ 步驟 4 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | 收不到 callback | ServerReplyURL 非公開 URL | 使用 ngrok 或部署到有公開 IP 的主機 |
> | 綠界持續重發 callback | 回應了 `1\|OK` 而非 AES 加密 JSON | 全方位物流 callback 回應必須是三層 AES-JSON，見上方範例 |
> | 解密失敗 | AES 函式編碼錯誤 | `aes_decrypt` 須用 `json.loads(unquote_plus(...))` 而非 `parse_qsl`；ECPay callback 為 JSON body，讀取 `request.get_json()['Data']`（Python）或 `req.body.Data`（Node.js） |

---

## AES 請求格式

```json
{
  "MerchantID": "2000132",
  "RqHeader": {
    "Timestamp": 1234567890,
    "Revision": "1.0.0"
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
| 測試環境 | `https://logistics-stage.ecpay.com.tw` |
| 正式環境 | `https://logistics.ecpay.com.tw` |
| 端點前綴 | `/Express/v2/` |
| Revision | `1.0.0` |
| 回應結構 | 三層 JSON（TransCode → 解密 Data → RtnCode） |
| Callback 回應 | AES 加密 JSON（見 [guides/22](./22-webhook-events-reference.md)） |

> **注意**：全方位物流 v2 使用 **AES JSON**（AES-JSON），與國內物流的 **Form + CheckMacValue MD5**（CMV-MD5）完全不同。切勿混淆兩者的認證和請求格式。

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Logistics/全方位物流服務API技術文件.md`
> 以下端點及參數僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

### 端點 URL 一覽

| 功能 | 端點路徑 |
|------|---------|
| 物流選擇頁面重導 | `/Express/v2/RedirectToLogisticsSelection` |
| 暫存訂單建立（⚠️ 非公開文件端點，建議改用 RedirectToLogisticsSelection） | `/Express/v2/CreateTempTrade` |
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

## 物流選擇頁面重導

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/RedirectToLogisticsSelection.php`

```php
$postService = $factory->create('PostWithAesStrResponseService');
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'TempLogisticsID' => '0',  // 0=新建
        'GoodsAmount'     => 100,
        'GoodsName'       => '測試商品',
        'SenderName'      => '寄件人',
        'SenderZipCode'   => '106',
        'SenderAddress'   => '台北市大安區測試路1號',
        'ServerReplyURL'  => 'https://你的網站/ecpay/logistics-notify',
        'ClientReplyURL'  => 'https://你的網站/ecpay/logistics-result',
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/RedirectToLogisticsSelection');
echo $response['body'];  // 輸出 HTML 頁面
```

### 冷凍物流選擇

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/RedirectWithUnimartFreeze.php`

同上，但 Data 中加入 `'Temperature' => '0003'`。

### 處理暫存訂單回應

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/TempTradeEstablishedResponse.php`

消費者選擇完物流後，ClientReplyURL 收到結果：

```php
use Ecpay\Sdk\Response\AesJsonResponse;
$aesJsonResponse = $factory->create(AesJsonResponse::class);
$result = $aesJsonResponse->get($_POST['ResultData']);
// $result 包含 TempLogisticsID
```

## 暫存訂單流程

### 更新暫存訂單

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/UpdateTempTrade.php`

```php
$postService = $factory->create('PostWithAesJsonResponseService');
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'TempLogisticsID' => '暫存物流ID',
        'SenderName'      => '更新後的寄件人',
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/UpdateTempTrade');
```

### 正式成立訂單

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/CreateByTempTrade.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'TempLogisticsID' => '2264',  // 暫存物流ID
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/CreateByTempTrade');
```

## 物流狀態通知

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/LogisticsStatusNotify.php`

全方位物流的通知是 AES 加密的 JSON（不是 Form POST）：

```php
use Ecpay\Sdk\Response\AesJsonResponse as AesParser;
use Ecpay\Sdk\Request\AesRequest as AesGenerater;

// 接收通知
$aesParser = $factory->create(AesParser::class);
$parsedRequest = $aesParser->get(file_get_contents('php://input'));

// 回應（也需要 AES 加密）
$aesGenerater = $factory->create(AesGenerater::class);
$data = [
    'RtnCode' => 1,
    'RtnMsg'  => '',
];
$responseData = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time()],
    'TransCode'  => 1,
    'TransMsg'   => '',
    'Data'       => $data,
];
$response = $aesGenerater->get($responseData);
echo $response;
```

#### 全方位物流 Callback 回應範例

全方位物流的 callback 回應**不是** `1|OK`（那是國內物流），而是 **AES 加密的 JSON 三層結構**。

**你收到的 callback body**：
```json
{
  "MerchantID": "2000132",
  "RpHeader": { "Timestamp": 1709654400 },
  "TransCode": 1,
  "TransMsg": "Success",
  "Data": "AES加密的Base64字串"
}
```

**處理步驟**：
1. 解密 `Data` 欄位（使用 [guides/14](./14-aes-encryption.md) 的 `aesDecrypt` 函式）
2. 從解密結果取得物流狀態
3. 更新本地訂單狀態
4. 回應 AES 加密的 JSON：

**你必須回應的格式**：
```php
// PHP 範例（使用上方已建立的 $aesGenerater）
$responseData = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time()],
    'TransCode'  => 1,
    'TransMsg'   => '',
    'Data'       => ['RtnCode' => 1, 'RtnMsg' => ''],
];
$response = $aesGenerater->get($responseData);
echo $response;
```

> **常見錯誤**：用 `echo '1|OK'` 回應全方位物流 callback — 這會導致 ECPay 認為處理失敗並持續重送。
> 正確做法是回應 AES 加密的 JSON，格式與 API 請求的三層結構相同。

## 查詢物流訂單

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/QueryLogisticsTradeInfo.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'MerchantID' => '2000132',
        'LogisticsID'=> '物流編號',
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/QueryLogisticsTradeInfo');
```

## 列印

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/PrintTradeDocument.php`

```php
$postService = $factory->create('PostWithAesStrResponseService');
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'MerchantID'       => '2000132',
        'LogisticsID'      => ['1769543'],  // 陣列，可多筆
        'LogisticsSubType' => 'FAMI',
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/PrintTradeDocument');
echo $response['body'];
```

## B2C 退貨

### 全家退貨

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/B2C/ReturnFamiCVS.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'MerchantID'     => '2000132',
        'LogisticsID'    => '物流編號',
        'GoodsAmount'    => 100,
        'ServiceType'    => '4',
        'SenderName'     => '退貨人',
        'ServerReplyURL' => 'https://你的網站/ecpay/return-notify',
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/ReturnCVS');
```

### 萊爾富退貨

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/B2C/ReturnHilifeCvs.php`

端點：`POST /Express/v2/ReturnHilifeCVS`
Data 多一個 `SenderPhone` 欄位。

### 統一退貨

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/B2C/ReturnUnimartCvs.php`

端點：`POST /Express/v2/ReturnUniMartCVS`

### 宅配退貨

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/Home/ReturnHome.php`

```php
$input['Data'] = [
    'MerchantID'     => '2000132',
    'LogisticsID'    => '物流編號',
    'GoodsAmount'    => 100,
    'Temperature'    => '0001',
    'Distance'       => '00',
    'Specification'  => '0001',
    'ServerReplyURL' => 'https://你的網站/ecpay/return-notify',
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/ReturnHome');
```

## B2C 更新出貨

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/B2C/UpdateShipmentInfo.php`

```php
$input['Data'] = [
    'MerchantID'   => '2000132',
    'LogisticsID'  => '物流編號',
    'ShipmentDate' => '2026/03/16',
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/UpdateShipmentInfo');
```

## B2C 建立測試資料

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/B2C/CreateTestData.php`

```php
$input['Data'] = [
    'MerchantID'       => '2000132',
    'LogisticsSubType' => 'FAMI',
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/CreateTestData');
```

## C2C 操作

### 取消 C2C 訂單

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/C2C/CancelC2cOrder.php`

```php
$input['Data'] = [
    'MerchantID'       => '2000132',
    'LogisticsID'      => '物流編號',
    'CVSPaymentNo'     => '寄貨編號',
    'CVSValidationNo'  => '驗證碼',
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/CancelC2COrder');
```

### 更新門市資訊

> 原始範例：`scripts/SDK_PHP/example/Logistics/AllInOne/C2C/UpdateStoreInfo.php`

```php
$input['Data'] = [
    'MerchantID'       => '2000132',
    'LogisticsID'      => '物流編號',
    'CVSPaymentNo'     => '寄貨編號',
    'CVSValidationNo'  => '驗證碼',
    'StoreType'        => '01',
    'ReceiverStoreID'  => '新門市代碼',
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/v2/UpdateStoreInfo');
```

## 完整範例檔案對照（16 個）

| 檔案 | 用途 |
|------|------|
| RedirectToLogisticsSelection.php | 物流選擇頁面 |
| RedirectWithUnimartFreeze.php | 冷凍物流選擇 |
| TempTradeEstablishedResponse.php | 暫存回應 |
| UpdateTempTrade.php | 更新暫存 |
| CreateByTempTrade.php | 正式建單 |
| LogisticsStatusNotify.php | 狀態通知 |
| QueryLogisticsTradeInfo.php | 查詢 |
| PrintTradeDocument.php | 列印 |
| B2C/ReturnFamiCVS.php | 全家退貨 |
| B2C/ReturnHilifeCvs.php | 萊爾富退貨 |
| B2C/ReturnUnimartCvs.php | 統一退貨 |
| B2C/UpdateShipmentInfo.php | 更新出貨 |
| B2C/CreateTestData.php | 測試資料 |
| C2C/CancelC2cOrder.php | 取消C2C |
| C2C/UpdateStoreInfo.php | 更新門市 |
| Home/ReturnHome.php | 宅配退貨 |

> ⚠️ **安全必做清單（ServerReplyURL）**
> 1. 驗證 MerchantID 為自己的
> 2. 比對物流單號與訂單記錄
> 3. 防重複處理（記錄已處理的 LogisticsID）
> 4. 異常時仍回應 AES 加密 JSON `{ "TransCode": 1 }`（整數，非字串；避免重送風暴）
> 5. 記錄完整日誌（遮蔽 HashKey/HashIV）

## 相關文件

- 官方 API 規格：`references/Logistics/全方位物流服務API技術文件.md`（27 個 URL）
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- 國內物流（舊版）：[guides/06-logistics-domestic.md](./06-logistics-domestic.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
