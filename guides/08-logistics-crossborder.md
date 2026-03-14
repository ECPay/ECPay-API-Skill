> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 跨境物流完整指南

## 概述

跨境物流讓台灣商家將商品出貨到海外，目前支援透過統一超商的跨境超商取貨和跨境宅配。使用 AES 加密 + JSON 格式。

## 前置需求

- MerchantID / HashKey / HashIV（測試：2000132 / 5294y06JbISpM5x9 / v77hoKGq4kWxNNIS）
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
| 回應結構 | 三層 JSON（TransCode → 解密 Data → RtnCode） |
| Callback 回應 | AES 加密 JSON（三層結構，與全方位物流相同）— 詳見 [guides/22](./22-webhook-events-reference.md) |

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Logistics/綠界科技跨境物流API技術文件.md`
> 以下端點及參數僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

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
        'GoodsAmount'       => 100,
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
        'SenderCellPhone'   => '0912345678',
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
    s = json.dumps(data, ensure_ascii=False, separators=(',', ':'))
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
        'GoodsAmount':       100,
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
        'SenderCellPhone':   '0912345678',
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
| GoodsWeight | 商品重量（公斤），海關報關用 |
| GoodsEnglishName | 英文品名，海關報關用 |
| ReceiverCountry | 收件國家代碼（如 SG=新加坡） |
| ReceiverEmail | 收件人 Email |
| SenderEmail | 寄件人 Email |

## 跨境宅配

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/CreateUnimartHomeOrder.php`

與超商取貨相同端點和參數，差異：
- `LogisticsSubType` 改為 `UNIMARTCBHOME`
- 不需要 `ReceiverStoreID`

## 海外電子地圖

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/Map.php`

讓消費者選擇海外取貨門市：

```php
$autoSubmitFormService = $factory->create('AutoSubmitFormService');  // 注意：無 CMV
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

### 處理地圖結果

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/GetMapResponse.php`

```php
use Ecpay\Sdk\Response\ArrayResponse;
$arrayResponse = $factory->create(ArrayResponse::class);
$result = $arrayResponse->get($_POST);
```

## 列印

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/Print.php`

```php
$input['Data'] = [
    'MerchantID' => '2000132',
    'LogisticsID'=> '物流編號',
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/CrossBorder/Print');
```

## 查詢

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/QueryLogisticsTradeInfo.php`

```php
$input['Data'] = [
    'MerchantID' => '2000132',
    'LogisticsID'=> '物流編號',
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/CrossBorder/QueryLogisticsTradeInfo');
```

## 狀態變更通知

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/GetStatusChangedResponse.php`

跨境物流狀態通知也是 AES 加密：

```php
use Ecpay\Sdk\Response\AesJsonResponse;
$aesJsonResponse = $factory->create(AesJsonResponse::class);
$response = file_get_contents('php://input');
$parsed = $aesJsonResponse->get($response);
```

**狀態碼參考**：`scripts/SDK_PHP/example/Logistics/crossborder_logistics_status.xlsx`

## 建立測試資料

> 原始範例：`scripts/SDK_PHP/example/Logistics/CrossBorder/CreateTestData.php`

```php
$input['Data'] = [
    'MerchantID'       => '2000132',
    'Country'          => 'SG',
    'LogisticsType'    => 'CB',
    'LogisticsSubType' => 'UNIMARTCBCVS',
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

> ⚠️ **安全必做清單（ServerReplyURL）**
> 1. 驗證 MerchantID 為自己的
> 2. 比對物流單號與訂單記錄
> 3. 防重複處理（記錄已處理的 LogisticsID）
> 4. 異常時仍回應 AES 加密 JSON（避免重送風暴）— 格式同全方位物流，見 [guides/22](./22-webhook-events-reference.md)
> 5. 記錄完整日誌（遮蔽 HashKey/HashIV）

## 相關文件

- 官方 API 規格：`references/Logistics/綠界科技跨境物流API技術文件.md`（13 個 URL）
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
