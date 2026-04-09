> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 跨境物流完整指南

## 概述

跨境物流讓台灣商家將商品出貨到海外，目前支援透過統一超商的跨境超商取貨和跨境宅配。使用 AES 加密 + JSON 格式。

### ⚠️ AES-JSON 開發者必讀：雙層錯誤檢查

跨境物流使用 AES-JSON 協議，回應為三層 JSON 結構。**必須做兩次檢查**：

1. 檢查外層 `TransCode === 1`（否則 AES 加密/格式有問題）
2. 解密 Data 後，檢查內層 `RtnCode === 1`（**整數** `1`，非字串 `'1'`）（業務邏輯問題）

> 跨境物流的 **callback 回應**也需要 AES 加密 JSON（三層結構），與全方位物流相同，不同於國內物流的 `1|OK`。

完整錯誤碼參考見 [guides/21](./21-error-codes-reference.md)。

## 前置需求

- MerchantID / HashKey / HashIV（測試：2000132 / 5294y06JbISpM5x9 / v77hoKGq4kWxNNIS）
  > ℹ️ **帳號關係說明**:跨境物流的測試帳號與**國內物流(guides/06)、全方位物流(guides/07)完全相同**(三者共用 `2000132`)。差異在於**協議**與**端點前綴**:
  > - 國內物流:CMV-MD5 + Form POST,端點 `/Express/`
  > - 全方位物流 v2:AES-JSON,端點 `/Express/v2/`
  > - **跨境物流(本指南):AES-JSON,端點 `/CrossBorder/`**
  >
  > **此帳號也與 AIO 金流的 `3002607`、發票的 `2000132`(同編號但不同 HashKey)、離線發票的 `3085340` 完全無關**。切勿混用 HashKey/HashIV。
- SDK Service：`PostWithAesJsonResponseService`
- 基礎端點：`https://logistics-stage.ecpay.com.tw/CrossBorder/`

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式 | AES-JSON — 詳見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證 | AES-128-CBC 加密 Data 欄位 — 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| 測試環境 | `https://logistics-stage.ecpay.com.tw` |
| 正式環境 | `https://logistics.ecpay.com.tw` |
| 端點前綴 | `/CrossBorder/` |
| Revision | `1.0.0` |
| Timestamp 驗證 | **10 分鐘**內有效(⚠️ 全方位物流為 5 分鐘,與本服務**不同**;ECPG 亦為 10 分鐘。無論哪種,每次送出前均需**即時呼叫 `time()`**,不可預先計算或快取) |
| 回應結構 | 三層 JSON（TransCode → 解密 Data → RtnCode） |
| Callback 回應 | AES 加密 JSON（三層結構，與全方位物流相同）— 詳見 [guides/22](./22-webhook-events-reference.md) |

<!-- Revision 欄位於跨境物流官方文件中未明確記載，建議依全方位物流文件設定 -->
<!-- ⚠️ 跨境物流 Timestamp 驗證時間為 10 分鐘（全方位物流為 5 分鐘），注意差異 -->

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Logistics/綠界科技跨境物流API技術文件.md`
> 以下端點及參數僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

## Quick Start（5 分鐘速覽）

1. 確認使用 **AES-JSON 協定**（非 CMV-MD5），測試帳號見 guides/00
2. 加密流程：`json_encode → urlencode → AES-128-CBC 加密 → Base64`（詳見 guides/14）
3. 建立訂單 → 收 Callback → 查詢狀態（三步完成基本串接）
4. 回應格式為 **AES 加密 JSON**（三層結構：TransCode → 解密 Data → RtnCode）
5. ⚠️ **Timestamp 10 分鐘有效視窗**(與全方位物流 5 分鐘不同):每次送出前即時呼叫 `time()`,不可預先計算或快取

### 端點 URL 一覽

| 功能 | 端點路徑 |
|------|---------|
| 跨境建單（超商/宅配）| `/CrossBorder/Create` |
| 查詢跨境物流 | `/CrossBorder/QueryLogisticsTradeInfo` |
| 海外電子地圖 | `/CrossBorder/Map` |
| 列印 | `/CrossBorder/Print` |
| 建立測試資料 | `/CrossBorder/CreateTestData` |

> 超商取貨與宅配使用相同端點 `/CrossBorder/Create`，以 `LogisticsSubType` 區分：
> `UNIMARTCBCVS`（跨境超商）/ `UNIMARTCBHOME`（跨境宅配）

## 跨境超商取貨

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/CreateUnimartCvsOrder.php`

```php
$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv'  => 'v77hoKGq4kWxNNIS',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$input = [
    'MerchantID' => '2000132',
    'RqHeader'   => ['Timestamp' => time(), 'Revision' => '1.0.0'],
    'Data'       => [
        'MerchantID'        => '2000132',
        'MerchantTradeDate' => date('Y/m/d H:i:s'),
        'MerchantTradeNo'   => 'CB' . time(),
        'LogisticsType'     => 'CB',
        'LogisticsSubType'  => 'UNIMARTCBCVS',    // 跨境超商
        'GoodsAmount'       => 1000,
        'GoodsWeight'       => 5.0,                 // 重量（公斤）
        'GoodsEnglishName'  => 'Test Product',      // 英文品名（海關需要）
        'ReceiverCountry'   => 'SG',                // 收件國家代碼
        'ReceiverName'      => 'Receiver',
        'ReceiverCellPhone' => '6591234567',
        'ReceiverStoreID'   => '711_1',             // 海外門市代碼
        'ReceiverZipCode'   => '123456',
        'ReceiverAddress'   => 'Test Address',
        'ReceiverEmail'     => 'receiver@example.com',
        'SenderName'        => '寄件人',
        'SenderCellPhone'   => '886912345678',      // 跨境須用國際格式（去掉 0，加國碼 886）
        'SenderAddress'     => '台北市大安區測試路1號',
        'SenderEmail'       => 'sender@example.com',
        'Remark'            => '備註',
        'ServerReplyURL'    => 'https://你的網站/ecpay/cb-notify',
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/CrossBorder/Create');
```

```python
# Python — 跨境超商取貨建單（pip install requests pycryptodome）
import json, time, base64, urllib.parse, requests
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

HASH_KEY = b'5294y06JbISpM5x9'
HASH_IV  = b'v77hoKGq4kWxNNIS'

def aes_encrypt(data: dict) -> str:
    s = json.dumps(data, separators=(',', ':'))  # ensure_ascii=True (預設) 確保與 PHP json_encode 行為一致
    u = urllib.parse.quote_plus(s).replace('~', '%7E')
    cipher = AES.new(HASH_KEY, AES.MODE_CBC, HASH_IV)
    return base64.b64encode(cipher.encrypt(pad(u.encode('utf-8'), 16))).decode()

def aes_decrypt(b64: str) -> dict:
    decipher = AES.new(HASH_KEY, AES.MODE_CBC, HASH_IV)
    dec = unpad(decipher.decrypt(base64.b64decode(b64)), 16).decode('utf-8')
    return json.loads(urllib.parse.unquote_plus(dec))

resp = requests.post('https://logistics-stage.ecpay.com.tw/CrossBorder/Create', json={
    'MerchantID': '2000132',
    'RqHeader': {'Timestamp': int(time.time()), 'Revision': '1.0.0'},
    'Data': aes_encrypt({
        'MerchantID':        '2000132',
        'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
        'MerchantTradeNo':   'CB' + str(int(time.time())),
        'LogisticsType':     'CB',
        'LogisticsSubType':  'UNIMARTCBCVS',   # 跨境超商
        'GoodsAmount':       1000,
        'GoodsWeight':       5.0,
        'GoodsEnglishName':  'Test Product',
        'ReceiverCountry':   'SG',
        'ReceiverName':      'Receiver',
        'ReceiverCellPhone': '6591234567',
        'ReceiverStoreID':   '711_1',
        'ReceiverZipCode':   '123456',
        'ReceiverAddress':   'Test Address',
        'ReceiverEmail':     'receiver@example.com',
        'SenderName':        '寄件人',
        'SenderCellPhone':   '886912345678',   # 跨境須用國際格式（去掉 0，加國碼 886）
        'SenderAddress':     '台北市大安區測試路1號',
        'SenderEmail':       'sender@example.com',
        'ServerReplyURL':    'https://你的網站/ecpay/cb-notify',
    })
})
outer = resp.json()
if outer.get('TransCode') == 1:
    data = aes_decrypt(outer['Data'])
    print(f"✅ 建單成功 LogisticsID={data.get('LogisticsID')}")
else:
    print(f"❌ 格式錯誤 TransCode={outer.get('TransCode')} TransMsg={outer.get('TransMsg')}")
```

### 跨境必要欄位

| 欄位 | 說明 |
|------|------|
| GoodsAmount | 商品金額（整數），上限 20,000 TWD，作為商品遺失賠償依據 |
| GoodsWeight | 商品重量（公斤），支援整數 10 位 + 小數 2 位。超取上限：HK 5KG / SG 10KG / MY 20KG；宅配上限：均 20KG |
| GoodsEnglishName | 商品英文名稱（≤60 字元） |
| ReceiverCountry | 收件國家代碼（SG=新加坡、MY=馬來西亞、HK=香港） |
| ReceiverEmail | 收件人 Email |
| SenderEmail | 寄件人 Email |

> ⚠️ **各國 ReceiverZipCode / 長度限制對照表**:
>
> | 國家 | ReceiverCountry | ZipCode 格式 | ReceiverName | ReceiverCellPhone | ReceiverAddress |
> |------|---------------|-------------|--------------|-------------------|-----------------|
> | 新加坡 | `SG` | **6 碼數字**(如 `123456`) | ≤60 字 | 國際格式(不含國碼 `+65`) | ≤80 字 |
> | 馬來西亞 | `MY` | **5 碼數字**(如 `50000`) | ≤60 字 | 國際格式(不含國碼 `+60`) | ≤80 字 |
> | 香港 | `HK` | **固定填 `00000`**(香港無郵遞區號) | **店取時 ≤60 字** | **店取時 ≤15 字** | **店取時 ≤80 字** |
>
> - **寄件人手機格式**:`SenderCellPhone` 需用國際格式(去掉開頭 0,加國碼 `886`),例如 `886912345678`
> - **收件人手機**:`ReceiverCellPhone` 依收件國家格式;不含國碼前綴 `+`,但須為當地有效號碼
> - **GoodsWeight 國別上限**:超取 HK=5KG、SG=10KG、MY=20KG;宅配均為 20KG(見上表 `GoodsWeight` 列)
> - 以上規則依 `references/Logistics/綠界科技跨境物流API技術文件.md`;正式上線前請 web_fetch 對應 URL 確認最新限制

## 跨境宅配

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/CreateUnimartHomeOrder.php`

與超商取貨相同端點和參數，差異：
- `LogisticsSubType` 改為 `UNIMARTCBHOME`
- 不需要 `ReceiverStoreID`

## 海外電子地圖

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/Map.php`

讓消費者選擇海外取貨門市：

```php
$autoSubmitFormService = $factory->create('AutoSubmitFormService');  // 注意：電子地圖為 Form POST 開啟頁面，無 CMV，與國內物流的 CMV-MD5 不同
$input = [
    'MerchantID'       => '2000132',
    'MerchantTradeNo'  => 'Map' . time(),
    'LogisticsType'    => 'CB',
    'LogisticsSubType' => 'UNIMARTCBCVS',
    'Destination'      => 'SG',
    'ServerReplyURL'   => 'https://你的網站/ecpay/map-result',
];
echo $autoSubmitFormService->generate($input, 'https://logistics-stage.ecpay.com.tw/CrossBorder/Map');
```

**注意**：跨境電子地圖使用 `AutoSubmitFormService`（無 CheckMacValue），與國內不同。

> ⚠️ 電子地圖為 Form POST 開啟頁面，非 AES-JSON API 呼叫。參數以明文送出，回傳結果也是明文 POST（見下方「處理地圖結果」）。

### 處理地圖結果

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/GetMapResponse.php`

```php
use Ecpay\Sdk\Response\ArrayResponse;
// 地圖回傳為明文 POST，不需要 HashKey/HashIV，使用無憑證的 Factory
$factory = new Factory();
$arrayResponse = $factory->create(ArrayResponse::class);
$result = $arrayResponse->get($_POST);
```

## 列印

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/Print.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'  => '2000132',
        'LogisticsID' => '物流編號',
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/CrossBorder/Print');
```

## 查詢

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/QueryLogisticsTradeInfo.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'  => '2000132',
        'LogisticsID' => '物流編號',
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/CrossBorder/QueryLogisticsTradeInfo');
```

## 狀態變更通知

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/GetStatusChangedResponse.php`

跨境物流狀態通知也是 AES 加密，解密後 Data 包含 `LogisticsStatus`（貨態代碼）和 `LogisticsStatusName`（貨態訊息）等欄位：

```php
use Ecpay\Sdk\Response\AesJsonResponse;
$aesJsonResponse = $factory->create(AesJsonResponse::class);
$response = file_get_contents('php://input');
$parsed = $aesJsonResponse->get($response);
```

**狀態碼參考**：`scripts/SDK_PHP/example/Logistics/crossborder_logistics_status.xlsx`

> ⚠️ **重試機制**：若未正確回應 AES 加密 JSON（RtnCode:1、RtnMsg:OK），綠界每 60 分鐘重發一次，當天最多重複發送 3 次。

## 建立測試資料

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/CreateTestData.php`

```php
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision'  => '1.0.0',
    ],
    'Data' => [
        'MerchantID'       => '2000132',
        'Country'          => 'SG',
        'LogisticsType'    => 'CB',
        'LogisticsSubType' => 'UNIMARTCBCVS',
    ],
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/CrossBorder/CreateTestData');
```

## 完整範例檔案對照（8 個）

| 檔案 | 用途 |
|------|------|
| CreateUnimartCvsOrder.php | 跨境超商建單 |
| CreateUnimartHomeOrder.php | 跨境宅配建單 |
| Map.php | 海外電子地圖 |
| GetMapResponse.php | 地圖結果 |
| Print.php | 列印 |
| QueryLogisticsTradeInfo.php | 查詢 |
| GetStatusChangedResponse.php | 狀態通知 |
| CreateTestData.php | 測試資料 |

> ⚠️ ReturnURL / ServerReplyURL 僅支援 port 80/443，不可放在 CDN 後方。本機測試需使用 ngrok 等 tunnel。

> ⚠️ **安全必做清單（ServerReplyURL）**
> 1. 驗證 MerchantID 為自己的
> 2. 比對物流單號與訂單記錄
> 3. 防重複處理（記錄已處理的 LogisticsID）
> 4. 異常時仍回應 AES 加密 JSON（避免重送風暴）— 格式同全方位物流，見 [guides/22](./22-webhook-events-reference.md)
> 5. 記錄完整日誌（遮蔽 HashKey/HashIV）

### Callback 回應範例

跨境物流 ServerReplyURL 收到後，**必須回應 AES 加密 JSON**（格式同全方位物流 v2，不是 `1|OK`）：

```php
// PHP — 跨境物流 ServerReplyURL 回應
use Ecpay\Sdk\Services\AesService;
$aesService = $factory->create(AesService::class);
echo json_encode([
    'MerchantID' => getenv('ECPAY_MERCHANT_ID'),
    'RqHeader'   => ['Timestamp' => time()],
    'TransCode'  => 1,
    'TransMsg'   => '',
    'Data'       => $aesService->encrypt(['RtnCode' => 1, 'RtnMsg' => '']),
]);
```

```python
# Python — 跨境物流 callback 回應（格式與全方位物流相同）
# 參考 guides/07 §Callback 回應範例 的 make_aes_json_response() 實作
return Response(make_aes_json_response(1, '成功'), content_type='application/json')
```

## 相關文件

- 官方 API 規格：`references/Logistics/綠界科技跨境物流API技術文件.md`（13 個 URL）
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
