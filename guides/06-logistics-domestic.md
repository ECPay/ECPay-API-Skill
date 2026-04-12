> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 國內物流完整指南

> **讀對指南了嗎？** 需要全方位物流（AES-JSON 協議）→ [guides/07](./07-logistics-allinone.md)。跨境物流 → [guides/08](./08-logistics-crossborder.md)。需要收款而非出貨 → [guides/01 AIO](./01-payment-aio.md)。

## 概述

國內物流支援超商取貨（全家/統一/萊爾富/OK）和宅配（黑貓/郵局）。使用 CheckMacValue **MD5** 加密（注意不是 SHA256）。

> 🚨 **國內物流(本指南) vs 全方位物流 v2(guides/07) — 兩套獨立 API,不可混用**:
>
> | 面向 | 國內物流(本指南) | 全方位物流 v2 ([guides/07](./07-logistics-allinone.md)) |
> |------|----------------|----------------------------------------|
> | 加密協議 | **CMV-MD5**(非 SHA256) | **AES-128-CBC** |
> | 請求格式 | Form POST(`application/x-www-form-urlencoded`) | JSON POST(`application/json`) |
> | 端點前綴 | `/Express/` | `/Express/v2/` |
> | 建單流程 | 直接建單(一次 API 呼叫) | 暫存 → 選店 → 成立(三段) |
> | Callback 回應 | 純文字 `1\|OK` | AES 加密 JSON(三層結構) |
> | Timestamp 視窗 | 不適用(CMV 無時間戳驗證) | 5 分鐘 |
> | RqHeader.Revision | 不使用 | 必填 `"1.0.0"` |
>
> **選定其一後,所有 API 呼叫、加密、回應解析必須保持一致**。從國內物流範例複製到全方位物流會因協議不符全面失敗,反之亦然。

## 前置需求

- B2C 測試帳號：MerchantID `2000132` / HashKey `5294y06JbISpM5x9` / HashIV `v77hoKGq4kWxNNIS`（完整測試帳號列表見 [guides/00 §測試帳號](./00-getting-started.md)）
- C2C 測試帳號：MerchantID `2000933` / HashKey `XBERn1YOvpM9nfZc` / HashIV `h1ONHk4P4yqbl5LK`
- ⚠️ **B2C 與 C2C 帳號不可混用**：B2C 帳號無法建立 C2C 訂單（反之亦然），API 會回傳 MerchantID 不符錯誤
- 加密方式：CheckMacValue **MD5**（與金流不同！）
- 基礎端點：`https://logistics-stage.ecpay.com.tw/`

```php
$factory = new Factory([
    'hashKey'    => '5294y06JbISpM5x9',
    'hashIv'     => 'v77hoKGq4kWxNNIS',
    'hashMethod' => 'md5',  // 重要：國內物流用 MD5
]);
```

### 物流測試帳號對應

| 服務類型 | 測試 MerchantID | 說明 |
|---------|----------------|------|
| B2C 超商 | 2000132 | 統一超商、全家、萊爾富（OK 僅支援 C2C）|
| C2C 超商 | 2000933 | 消費者寄件（超商交貨便）|
| 宅配 | 2000132 | 黑貓宅急便、中華郵政 |
| 備用（非 OTP 模式） | 2000214 | 同 B2C 的 HashKey/HashIV；API 文件指定非 OTP 帳號時才使用 |

> 注意：實際測試帳號以綠界官方文件為準，不同物流類型可能使用不同測試帳號。

> 📋 **完整跨服務測試帳號對照表**見 [SKILL.md §測試帳號](../SKILL.md#測試帳號)。

## 🚀 首次串接：最快成功路徑

> 第一次串接國內物流？從這裡開始，目標是完成**一筆全家超商取貨**的建單與狀態通知。

### 前置確認清單

- [ ] ⚠️ **物流測試帳號與 AIO 金流不同**：MerchantID `2000132` / HashKey `5294y06JbISpM5x9` / HashIV `v77hoKGq4kWxNNIS`
- [ ] ⚠️ **加密方式是 MD5，不是 SHA256**：SDK 初始化時 `'hashMethod' => 'md5'`
- [ ] **ServerReplyURL 可公開訪問**（localhost 無效，綠界無法回呼物流狀態）
- [ ] ⚠️ **ServerReplyURL 只接受 Port 80 或 443**（其他 Port 如 8080 無法接收回呼）
- [ ] 先測超商取貨（CVS），比宅配流程簡單
- [ ] 🚨 **電子地圖選店的 ServerReplyURL 與物流狀態的 ServerReplyURL 必須是兩個不同的 URL**(常見複製貼上錯誤):
  - 地圖選店用 URL(例:`/ecpay/map-result`)— 接收 `CVSStoreID`/`CVSStoreName`/`CVSAddress`
  - 物流狀態用 URL(例:`/ecpay/logistics-notify`)— 接收 `RtnCode`/`AllPayLogisticsID`
  - 兩者欄位、驗證邏輯、回應格式皆不同,絕對不可共用同一端點

### 國內物流 CreateCvs（超商取貨）必填欄位速查

> 📋 以下為 CreateCvs 建立超商取貨物流訂單最常用必填欄位。詳細規格請 web_fetch `references/Logistics/物流整合API技術文件.md`。

| 欄位 | 類型 | 必填？ | 說明 |
|------|------|:------:|------|
| `MerchantID` | String(10) | ✅ 必填 | 測試：`2000132` |
| `MerchantTradeNo` | String(20) | 否（可空） | 每次唯一；英數字。可為空，系統會自動產生 |
| `MerchantTradeDate` | String(20) | ✅ 必填 | 格式:`'yyyy/MM/dd HH:mm:ss'` (月/日需**補前導零**,如 `2026/04/09 14:30:00`)。⚠️ PHP `date('Y/m/d H:i:s')` 不會補前導零,建議使用 `date('Y/m/d H:i:s')` 並確認格式,或改用 `date('Y/m/d H:i:s', time())` 格式化前加補零邏輯;其他語言請確認 `strftime`/format string 產生補零格式 |
| `LogisticsType` | String(20) | ✅ 必填 | `'CVS'`=超商 `'HOME'`=宅配 |
| `LogisticsSubType` | String(20) | ✅ 必填 | `'FAMI'`=全家（測試最簡單）`'UNIMART'`=7-11 `'HILIFE'`=萊爾富 |
| `GoodsAmount` | Int | ✅ 必填 | 商品金額（整數，新台幣） |
| `GoodsName` | String(50) | ⚠️ C2C 必填 | 商品名稱。UNIMARTC2C / HILIFEC2C / OKMARTC2C 時不可為空 |
| `SenderName` | String(10) | ✅ 必填 | 寄件人姓名（4-10 字元，不可含數字及特殊符號） |
| `SenderCellPhone` | String(20) | ⚠️ C2C 必填 | 寄件人手機（09 開頭 10 碼）。UNIMARTC2C / HILIFEC2C / OKMARTC2C 時不可為空 |
| `ReceiverName` | String(10) | ✅ 必填 | 收件人姓名 |
| `ReceiverCellPhone` | String(20) | ✅ 必填 | 收件人手機 |
| `ReceiverStoreID` | String(6) | ✅ 必填 | 收件門市代碼（從選店步驟取得） |
| `ServerReplyURL` | String(200) | ✅ 必填 | 物流狀態通知 URL（公開 HTTPS） |
| `ReturnStoreID` | String(6) | 否 | 退貨門市代碼。僅 7-ELEVEN C2C（UNIMARTC2C）適用；未設定時退回原寄件門市 |
| `IsCollection` | String(1) | 否（預設 `'N'`）| `'N'`=不代收 `'Y'`=代收款；代收時亦需在電子地圖請求中傳入 |

> ⚠️ **超商（CVS）與宅配（HOME）收寄件人電話規則差異**：
> - **超商（CVS）**：`ReceiverCellPhone` **必填**（09 開頭 10 碼），`ReceiverPhone` 選填
> - **宅配（HOME）**：`ReceiverPhone` / `ReceiverCellPhone` **擇一不可為空**；`SenderPhone` / `SenderCellPhone` 同理**擇一不可為空**
> - **超商 C2C**：`SenderCellPhone` 單獨必填（UNIMARTC2C / HILIFEC2C / OKMARTC2C）
>
> ⚠️ **宅配（HOME）額外規則**（以上表格為超商 CVS，宅配參數有差異）：
> - **`GoodsWeight`**（Number）：當 `LogisticsSubType=POST`（中華郵政）時**必填**，上限 20 公斤，小數 3 位（單位：公斤）
> - 宅配專用參數（Temperature / Distance / Specification 等）詳見下方「宅配建單」段落

---

### 步驟 1：電子地圖選店

> 參考範例：`scripts/SDK_PHP/example/Logistics/Domestic/Map.php`

在你的頁面產生電子地圖表單，消費者選好門市後，綠界 POST 到你的 `ServerReplyURL`：

```php
$factory = new Factory([
    'hashKey'    => '5294y06JbISpM5x9',
    'hashIv'     => 'v77hoKGq4kWxNNIS',
    'hashMethod' => 'md5',   // 重要：物流用 MD5
]);
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');
$input = [
    'MerchantID'       => '2000132',
    'MerchantTradeNo'  => 'MAP' . time(),
    'LogisticsType'    => 'CVS',
    'LogisticsSubType' => 'FAMI',      // FAMI=全家，測試環境可用
    'IsCollection'     => 'N',
    'ServerReplyURL'   => 'https://你的網站/ecpay/map-result',  // ⚠️ 此為「地圖選店結果」專用端點,與步驟 2 建單的物流狀態通知端點不同
];
echo $autoSubmitFormService->generate($input, 'https://logistics-stage.ecpay.com.tw/Express/map');
```

選店結果 POST 到你的 `ServerReplyURL`，讀取 `$_POST` 欄位：

```php
// ServerReplyURL 接收選店結果（Form POST，scripts/SDK_PHP/example/Logistics/Domestic/GetMapResponse.php）
$storeID   = $_POST['CVSStoreID']   ?? null;  // 門市代碼（建立物流訂單時填入 ReceiverStoreID）
$storeName = $_POST['CVSStoreName'] ?? null;  // 門市名稱（顯示給消費者）
$address   = $_POST['CVSAddress']   ?? null;  // 門市地址
$subType   = $_POST['LogisticsSubType'] ?? null;  // FAMI/UNIMART/HILIFE/OKMARTC2C（C2C）

// ✅ 將以上欄位存入 session 或 database，供步驟 2 建立物流訂單時使用
// ℹ️ CVSStoreID 可能為 String(9)，但建單用的 ReceiverStoreID 為 String(6)，取用時需注意長度截取
```

> 🔍 **此步驟失敗？**
>
> | 症狀 | 最可能原因 |
> |------|----------|
> | 地圖頁面打不開 / 空白 | HashKey/HashIV 填錯（確認用物流帳號，非金流帳號） |
> | ServerReplyURL 沒有收到選店結果 | URL 不可公開訪問；確認 URL 拼字正確 |
> | CheckMacValue 驗證失敗 | 確認 SDK 初始化時 `hashMethod` 設為 `md5` |

---

### 步驟 2：建立超商取貨物流訂單

> 參考範例：`scripts/SDK_PHP/example/Logistics/Domestic/CreateCvs.php`

```php
$postService = $factory->create('PostWithCmvStrResponseService');
$input = [
    'MerchantID'        => '2000132',
    'MerchantTradeNo'   => 'CVS' . time(),
    'MerchantTradeDate' => date('Y/m/d H:i:s'),
    'LogisticsType'     => 'CVS',
    'LogisticsSubType'  => 'FAMI',
    'GoodsAmount'       => 100,
    'GoodsName'         => '測試商品',
    'SenderName'        => '寄件人',
    'SenderCellPhone'   => '0912345678',
    'ReceiverName'      => '收件人',
    'ReceiverCellPhone' => '0987654321',
    'ReceiverStoreID'   => $cvsStoreID,   // 從步驟 1 取得
    'ServerReplyURL'    => 'https://你的網站/ecpay/logistics-notify',  // ⚠️ 此為「物流狀態通知」專用端點,與步驟 1 的地圖選店端點(`/ecpay/map-result`)不同
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/Create');
// 解析 pipe-separated 回應：1|OK|AllPayLogisticsID=...
```

> 🔍 **此步驟失敗？**
>
> | 症狀 | 最可能原因 |
> |------|----------|
> | RtnCode ≠ 1 | 查 [guides/20 §物流錯誤碼](./20-error-codes-reference.md)；常見：收件人資料缺漏 |
> | 回應格式看不懂 | 物流回應是 pipe-separated 字串（非 JSON），用 `parse_str` 或字串分割解析 |
> | MerchantTradeNo 重複 | 每次建單要用新的交易編號 |

---

### 步驟 3：接收物流狀態通知

綠界在物流狀態更新時 POST 到你的 `ServerReplyURL`（Form POST）：

> **⚠️ 協議提醒**：國內物流使用 **Form POST + CMV-MD5**（非 AES-JSON）。`$_POST` 取得的值均為**字串型別**，包括 `RtnCode`（字串 `"300"`、`"3018"` 等）。驗證完成後必須回應純文字 **`1|OK`**（不是 AES-JSON）。

```php
// ServerReplyURL 處理（scripts/SDK_PHP/example/Logistics/Domestic/GetLogisticStatueResponse.php）
$logisticsID = $_POST['AllPayLogisticsID'] ?? null;
$rtnCode     = $_POST['RtnCode'] ?? null;

if ($rtnCode == 300) {
    // 300 = 訂單處理中（已收到訂單資料），非消費者取貨
    // 超商取貨狀態碼：7-ELEVEN=2067, 全家/萊爾富/OK=3022
}

echo '1|OK';  // 必須回應，否則綠界會重試
```

```python
# Python / Flask — ServerReplyURL 物流狀態通知（國內物流，Form POST + CheckMacValue MD5）
import hmac, hashlib, urllib.parse
from flask import Flask, request

app = Flask(__name__)
HASH_KEY = '5294y06JbISpM5x9'   # ⚠️ 物流帳號 HashKey，不同於金流/發票
HASH_IV  = 'v77hoKGq4kWxNNIS'

def verify_cmv_md5(params: dict) -> bool:
    """國內物流使用 MD5（不是 SHA256）"""
    received = params.get('CheckMacValue', '')
    sorted_params = sorted(((k, v) for k, v in params.items() if k != 'CheckMacValue'), key=lambda x: x[0].lower())
    raw = f'HashKey={HASH_KEY}&' + '&'.join(f'{k}={v}' for k, v in sorted_params) + f'&HashIV={HASH_IV}'
    # Python quote_plus 不編碼 ~，但 PHP urlencode 會編碼為 %7e，此處補齊差異
    encoded = urllib.parse.quote_plus(raw).replace('~', '%7e').lower()
    for orig, repl in [('%2d','-'),('%5f','_'),('%2e','.'),('%21','!'),('%2a','*'),('%28','('),('%29',')')]:
        encoded = encoded.replace(orig, repl)
    computed = hashlib.md5(encoded.encode()).hexdigest().upper()
    return hmac.compare_digest(computed, received.upper())  # timing-safe

@app.route('/ecpay/logistics-notify', methods=['POST'])
def logistics_notify():
    params = {k: (v[0] if isinstance(v, list) else v) for k, v in request.form.items()}

    if not verify_cmv_md5(params):
        return '', 400  # 驗證失敗，不回應 1|OK

    rtn_code     = params.get('RtnCode', '')
    logistics_id = params.get('AllPayLogisticsID', '')
    status_msg   = params.get('RtnMsg', '')

    # 常見狀態碼（完整列表見 guides/20）
    STATUS_MAP = {
        '300': '訂單處理中（已收到訂單資料）',
        '2067': '消費者已取貨（7-ELEVEN）',
        '3018': '包裹到店（消費者尚未取件）',
        '3022': '消費者成功取件（全家/萊爾富/OK mart）',
    }
    print(f'[物流通知] 物流ID={logistics_id} 狀態={rtn_code}({STATUS_MAP.get(rtn_code, status_msg)})')

    if rtn_code in ('2067', '3022'):
        pass  # TODO: 更新訂單狀態為「消費者已取貨」（2067=7-ELEVEN, 3022=全家/萊爾富/OK）

    return '1|OK', 200, {'Content-Type': 'text/plain'}  # ⚠️ 必須回應純文字 1|OK
```

```javascript
// Node.js / Express — ServerReplyURL 物流狀態通知（國內物流）
const express = require('express');
const crypto  = require('crypto');

const app = express();
app.use(express.urlencoded({ extended: true }));

const HASH_KEY = '5294y06JbISpM5x9';
const HASH_IV  = 'v77hoKGq4kWxNNIS';

function verifyCmvMd5(params) {
  const { CheckMacValue: received, ...rest } = params;
  const sorted = Object.entries(rest).sort(([a], [b]) => a.toLowerCase().localeCompare(b.toLowerCase()));
  let raw = `HashKey=${HASH_KEY}&` + sorted.map(([k,v]) => `${k}=${v}`).join('&') + `&HashIV=${HASH_IV}`;
  let encoded = encodeURIComponent(raw).replace(/%20/g,'+').replace(/~/g,'%7e').replace(/'/g,'%27').toLowerCase()
    .replace(/%2d/g,'-').replace(/%5f/g,'_').replace(/%2e/g,'.')
    .replace(/%21/g,'!').replace(/%2a/g,'*').replace(/%28/g,'(').replace(/%29/g,')');
  const computed = crypto.createHash('md5').update(encoded).digest('hex').toUpperCase();
  const bufA = Buffer.from(computed), bufB = Buffer.from((received || '').toUpperCase());
  if (bufA.length !== bufB.length) return false;
  return crypto.timingSafeEqual(bufA, bufB);
}

app.post('/ecpay/logistics-notify', (req, res) => {
  if (!verifyCmvMd5(req.body)) return res.status(400).send('Invalid');

  const { AllPayLogisticsID, RtnCode, RtnMsg } = req.body;
  console.log(`[物流通知] ID=${AllPayLogisticsID} 狀態=${RtnCode} ${RtnMsg}`);
  if (RtnCode === '300') {
    // TODO: 更新訂單狀態為「訂單處理中」（300 = 已收到訂單資料，非已取貨）
  }
  res.type('text').send('1|OK');  // ⚠️ 必須回應純文字 1|OK
});
```

> 🔍 **此步驟失敗？**
>
> | 症狀 | 最可能原因 |
> |------|----------|
> | 狀態通知從未收到 | ServerReplyURL 不可公開訪問；確認建單時的 ServerReplyURL 正確 |
> | 重複收到相同通知 | 沒有回應 `1|OK` |
> | 狀態碼看不懂 | 查 [guides/20 §物流狀態碼](./20-error-codes-reference.md) |
>
> ⚠️ **物流狀態通知重試機制**：若未正確回應 `1|OK`，系統重發 3 次後延至隔天，從貨態更新日起維持 3 天發送。

---

### 首次串接常見失誤

| 錯誤 | 解法 |
|------|------|
| 用 SHA256 計算 CheckMacValue | 物流必須用 **MD5**，SDK 初始化加 `'hashMethod' => 'md5'` |
| 複製 AIO 金流程式碼後忘記改 hashMethod | AIO 用 SHA256，國內物流用 **MD5**，確認 `'hashMethod' => 'md5'` |
| 用金流帳號（3002607）建物流單 | 物流帳號獨立，B2C 超商用 2000132，C2C 用 2000933 |
| 電子地圖和物流通知用同一個 ServerReplyURL | 選店結果和物流狀態是兩種不同的 POST，需要分別處理 |
| 忘記回應 `1|OK` | 不回應會導致物流狀態重複推送 |

---

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式 | CMV-MD5 — 詳見 [guides/19-http-protocol-reference.md](./19-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/x-www-form-urlencoded` |
| 認證 | CheckMacValue（**MD5**，非 SHA256） — 詳見 [guides/13-checkmacvalue.md](./13-checkmacvalue.md) |
| 測試環境 | `https://logistics-stage.ecpay.com.tw` |
| 正式環境 | `https://logistics.ecpay.com.tw` |
| 回應格式 | 依端點不同：pipe-separated / URL-encoded / JSON / HTML / plain text |
| Callback | Form POST 至 ServerReplyURL，必須回應 `1|OK` |

> **重要**：國內物流的 CheckMacValue 使用 **MD5**（不是 SHA256）。與 AIO 金流的加密方式不同！

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Logistics/物流整合API技術文件.md`
> 以下端點及參數僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

### 端點 URL 一覽

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
| 查詢物流訂單 | `/Helper/QueryLogisticsTradeInfo/V5` | URL-encoded |
| 取得門市清單 | `/Helper/GetStoreList` | JSON |

> **冷鏈物流**：部分超商（統一超商、全家）支援冷凍/冷藏配送。相關規格（如 `LogisticsSubType` 冷凍參數）需向綠界確認帳號是否已開通，詳見 `references/Logistics/物流整合API技術文件.md` 查詢官方最新支援說明。

## 物流商支援表

| 代碼 | 物流商 | 類型 | 說明 |
|------|-------|------|------|
| FAMI | 全家（B2C） | CVS | 超商取貨 |
| UNIMART | 統一超商（B2C） | CVS | 超商取貨 |
| UNIMARTFREEZE | 統一超商（B2C 冷凍） | CVS | 冷凍取貨 |
| HILIFE | 萊爾富（B2C） | CVS | 超商取貨 |
| FAMIC2C | 全家（C2C） | CVS | 店到店 |<!-- FAMIC2C SenderCellPhone 自動帶入行為未記載於官方文件 -->
| UNIMARTC2C | 統一超商（C2C） | CVS | 店到店 |
| HILIFEC2C | 萊爾富（C2C） | CVS | 店到店 |
| OKMARTC2C | OK 超商（C2C） | CVS | 店到店（OK 僅支援 C2C） |
| TCAT | 黑貓宅急便 | HOME | 宅配 |
| POST | 中華郵政 | HOME | 宅配 |

## 電子地圖選店

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/Map.php`

讓消費者在地圖上選擇取貨門市：

```php
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');
$input = [
    'MerchantID'      => '2000132',
    'MerchantTradeNo' => 'Log' . time(),
    'LogisticsType'   => 'CVS',
    'LogisticsSubType'=> 'FAMI',       // B2C: FAMI/UNIMART/HILIFE; C2C: FAMIC2C/UNIMARTC2C/HILIFEC2C/OKMARTC2C
    'IsCollection'    => 'N',           // Y=貨到付款, N=僅配送
    'ServerReplyURL'  => 'https://你的網站/ecpay/map-result',
];
echo $autoSubmitFormService->generate($input, 'https://logistics-stage.ecpay.com.tw/Express/map');
```

### 處理選店結果

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/GetMapResponse.php`

```php
use Ecpay\Sdk\Response\ArrayResponse;
$arrayResponse = $factory->create(ArrayResponse::class);
$result = $arrayResponse->get($_POST);
// $result 包含：CVSStoreID, CVSStoreName, CVSAddress, CVSTelephone 等
```

## 超商取貨建單

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/CreateCvs.php`

> ⚠️ **GoodsAmount 限制**：CVS 超商取貨（含所有子類型）`GoodsAmount` 上限為 **20,000 元**，超過回傳錯誤碼 `10500040`。宅配（HOME/TCAT/POST）無此限制，但貨到付款（IsCollection=Y）上限同為 20,000 元。
>
> ⚠️ **UNIMART / UNIMARTC2C / UNIMARTFREEZE 額外必填**：需同時傳入 `CollectionAmount`，值必須等於 `GoodsAmount`，否則建單失敗。
>
> ℹ️ 超商包裹尺寸/重量限制依各物流商規定（7-ELEVEN、全家、萊爾富、OK 各有不同），詳見綠界官方文件「出貨注意事項」（`references/Logistics/物流整合API技術文件.md` → 出貨注意事項）。API 層面僅限制 GoodsAmount 金額範圍 1~20,000 元。

```php
$postService = $factory->create('PostWithCmvStrResponseService');
$input = [
    'MerchantID'       => '2000132',
    'MerchantTradeNo'  => 'CVS' . time(),
    'MerchantTradeDate'=> date('Y/m/d H:i:s'),
    'LogisticsType'    => 'CVS',
    'LogisticsSubType' => 'FAMI',
    'GoodsAmount'      => 100,
    'GoodsName'        => '測試商品',
    'SenderName'       => '寄件人',
    'SenderCellPhone'  => '0912345678',
    'ReceiverName'     => '收件人',
    'ReceiverCellPhone'=> '0987654321',
    'ServerReplyURL'   => 'https://你的網站/ecpay/logistics-notify',
    'ReceiverStoreID'  => '門市代碼',  // 從電子地圖取得
];

try {
    $response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/Create');
    // 回應包含 AllPayLogisticsID（物流交易編號）和 CVSPaymentNo（超商寄貨編號）
} catch (\Exception $e) {
    error_log('ECPay Logistics Create Error: ' . $e->getMessage());
}
```

#### 超商建單回傳欄位

| 欄位 | 說明 |
|------|------|
| AllPayLogisticsID | 綠界物流交易編號（後續查詢、列印、退貨用） |
| CVSPaymentNo | 超商寄貨編號 |
| CVSValidationNo | 驗證碼（統一超商退貨用） |
| MerchantTradeNo | 特店交易編號（你送出的） |
| RtnCode | 回應代碼（1=成功） |
| RtnMsg | 回應訊息 |

> 🔍 **建單失敗？** ①`RtnCode ≠ 1`→查 [guides/20 §物流錯誤碼](./20-error-codes-reference.md)；②確認收件人 `ReceiverStoreID` 已從電子地圖取得（不可手填猜測）；③ `MerchantTradeNo` 不可重複。
>
> ⚠️ 常見物流訂單建立失敗原因：收件人資料格式錯誤（手機非 09 開頭 10 碼、地址超長）、門市代號無效或暫停服務。建議送出前驗證欄位格式，並處理 API 回傳的錯誤碼（見 [guides/20](./20-error-codes-reference.md)）。

### 表單模式建單

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/CreateCvsForm.php`

同樣參數但使用 `AutoSubmitFormWithCmvService`，多一個 `ClientReplyURL`。

### 統一超商冷凍取貨

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/CreateUnimartFreeze.php`

`LogisticsSubType` 改為 `UNIMARTFREEZE`，其餘同一般超商取貨。

## 宅配建單

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/CreateHome.php`

> ⚠️ **宅配注意事項**：
> - `SenderPhone` / `SenderCellPhone` **擇一不可為空**（與超商不同，不是只有 C2C 才需要）
> - `ReceiverPhone` / `ReceiverCellPhone` **擇一不可為空**（與超商不同，超商是 `ReceiverCellPhone` 單獨必填）
> - 當 `LogisticsSubType=POST`（中華郵政）時，`GoodsWeight` **必填**（上限 20 公斤，小數 3 位，單位：公斤）
> - 中華郵政請忽略 `Temperature`（僅限常溫）、`Distance`、`Specification`、`ScheduledPickupTime`、`ScheduledDeliveryTime`

```php
$input = [
    'MerchantID'          => '2000132',
    'MerchantTradeNo'     => 'HOME' . time(),
    'MerchantTradeDate'   => date('Y/m/d H:i:s'),
    'LogisticsType'       => 'HOME',
    'LogisticsSubType'    => 'TCAT',       // TCAT=黑貓, POST=郵局
    'GoodsAmount'         => 100,
    'GoodsName'           => '測試商品',
    'SenderName'          => '寄件人',
    'SenderCellPhone'     => '0912345678',
    'SenderZipCode'       => '106',
    'SenderAddress'       => '台北市大安區測試路1號',
    'ReceiverName'        => '收件人',
    'ReceiverCellPhone'   => '0987654321',
    'ReceiverZipCode'     => '110',
    'ReceiverAddress'     => '台北市信義區測試路2號',
    'Temperature'         => '0001',       // 0001=常溫, 0002=冷藏, 0003=冷凍
    'Distance'            => '00',         // 00=同縣市, 01=外縣市, 02=離島
    'Specification'       => '0001',       // 0001=60cm, 0002=90cm, 0003=120cm, 0004=150cm
    'ScheduledPickupTime' => '4',          // 4=不限時
    'ScheduledDeliveryTime'=> '4',         // 4=不限時
    'ServerReplyURL'      => 'https://你的網站/ecpay/logistics-notify',
];

try {
    $response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/Create');
    // 回應包含 AllPayLogisticsID（物流交易編號）
} catch (\Exception $e) {
    error_log('ECPay Home Delivery Create Error: ' . $e->getMessage());
}
```

#### 宅配建單回傳欄位

| 欄位 | 說明 |
|------|------|
| AllPayLogisticsID | 綠界物流交易編號 |
| MerchantTradeNo | 特店交易編號 |
| RtnCode | 回應代碼（1=成功） |
| RtnMsg | 回應訊息 |

### 宅配表單模式

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/CreateHomeForm.php`

## 物流狀態通知

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/GetLogisticStatueResponse.php`

物流狀態變更時，綠界 POST 到 ServerReplyURL：

```php
use Ecpay\Sdk\Response\VerifiedArrayResponse;
$verifiedResponse = $factory->create(VerifiedArrayResponse::class);
$result = $verifiedResponse->get($_POST);
// $result 包含：AllPayLogisticsID, MerchantTradeNo, RtnCode, RtnMsg, LogisticsType, LogisticsSubType 等
echo '1|OK';
```

**物流狀態碼參考**：`scripts/SDK_PHP/example/Logistics/logistics_status.xlsx` 和 `logistics_history.xlsx`

> 🔍 **物流狀態通知沒有收到？** ①確認建單時的 `ServerReplyURL` 可公開訪問；② CheckMacValue 驗證失敗→確認 SDK 用 MD5（非 SHA256）；③重複收到通知→確認 handler 有回應 `1|OK`。

## 退貨

### 超商退貨

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/ReturnFamiCvs.php`, `scripts/SDK_PHP/example/Logistics/Domestic/ReturnUniMartCvs.php`
>
> ⚠️ **萊爾富逆物流（`/express/ReturnHilifeCVS`）**：官方 PHP SDK 無 Domestic 範例，參數格式同 `ReturnFamiCvs.php`，詳見 `references/Logistics/物流整合API技術文件.md` 對應章節。

```php
$input = [
    'MerchantID'     => '2000132',
    'GoodsAmount'    => 100,
    'ServiceType'    => '4',
    'SenderName'     => '退貨人',
    'ServerReplyURL' => 'https://你的網站/ecpay/return-notify',
];
// 全家退貨
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/express/ReturnCVS');
// 統一退貨
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/express/ReturnUniMartCVS');
```

> **注意**：超商退貨（CVS Return）建單的回傳結果中不會包含 `AllPayLogisticsID`。
> 需改用 `RtnMerchantTradeNo`（綠界回傳的退貨交易編號）追蹤退貨狀態。
> 退貨物流狀態會透過 `ServerReplyURL` 通知。
>
> ⚠️ **逆物流 Callback 注意**：退貨狀態通知使用 `RtnMerchantTradeNo`（非正向物流的 `MerchantTradeNo`），且 `RtnCode` 固定為 `100`（表示退貨成功受理），處理邏輯需與正向物流 Callback 區分。

### 宅配退貨

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/ReturnHome.php`

```php
$input = [
    'MerchantID'       => '2000132',
    'AllPayLogisticsID'=> '物流編號',
    'GoodsAmount'      => 100,
    'Temperature'      => '0001',
    'Distance'         => '00',
    'ServerReplyURL'   => 'https://你的網站/ecpay/return-notify',
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Express/ReturnHome');
```

### 退貨回應處理

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/GetReturnResponse.php`

```php
$verifiedResponse = $factory->create(VerifiedArrayResponse::class);
$result = $verifiedResponse->get($_POST);
```

## 更新出貨 / 門市資訊

### 更新出貨資訊

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/UpdateShipmentInfo.php`

```php
$input = [
    'MerchantID'       => '2000132',
    'AllPayLogisticsID'=> '物流編號',
    'ShipmentDate'     => '2025/01/20',
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Helper/UpdateShipmentInfo');
```

### 更新門市資訊（C2C）

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/UpdateStoreInfo.php`

> ⚠️ **C2C 操作必須使用 C2C 帳號重新建立 Factory**（HashKey/HashIV 與 B2C 不同）

```php
// C2C 操作：必須用 C2C HashKey/HashIV 重新建立 Factory
$factoryC2C = new Factory([
    'hashKey'    => 'XBERn1YOvpM9nfZc',  // C2C 專用 HashKey
    'hashIv'     => 'h1ONHk4P4yqbl5LK',  // C2C 專用 HashIV
    'hashMethod' => 'md5',
]);
$postServiceC2C = $factoryC2C->create('PostWithCmvStrResponseService');

$input = [
    'MerchantID'       => '2000933',
    'AllPayLogisticsID'=> '物流編號',
    'CVSPaymentNo'     => '寄貨編號',
    'CVSValidationNo'  => '驗證碼',
    'StoreType'        => '01',
    'ReceiverStoreID'  => '新門市代碼',
];
$response = $postServiceC2C->post($input, 'https://logistics-stage.ecpay.com.tw/Express/UpdateStoreInfo');
```

## 取消 C2C 訂單

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/CancelC2cOrder.php`

```php
// 使用上方建立的 $factoryC2C / $postServiceC2C（C2C 帳號）
$input = [
    'MerchantID'       => '2000933',
    'AllPayLogisticsID'=> '物流編號',
    'CVSPaymentNo'     => '寄貨編號',
    'CVSValidationNo'  => '驗證碼',
];
$response = $postServiceC2C->post($input, 'https://logistics-stage.ecpay.com.tw/Express/CancelC2COrder');
```

## 查詢物流訂單

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/QueryLogisticsTradeInfo.php`

```php
$postService = $factory->create('PostWithCmvVerifiedEncodedStrResponseService');
$input = [
    'MerchantID'       => '2000132',
    'AllPayLogisticsID'=> '物流編號',
    'TimeStamp'        => time(),
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Helper/QueryLogisticsTradeInfo/V5');
```

## 列印托運單

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/PrintTradeDocument.php`

```php
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');
$input = [
    'MerchantID'       => '2000132',
    'AllPayLogisticsID'=> '物流編號',
];
echo $autoSubmitFormService->generate($input, 'https://logistics-stage.ecpay.com.tw/helper/printTradeDocument');
```

### C2C 列印標籤

> ⚠️ C2C 列印功能需使用 **C2C 帳號**（MerchantID: 2000933），不是 **B2C** 帳號。

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/PrintFamic2cOrderInfo.php`, `scripts/SDK_PHP/example/Logistics/Domestic/PrintUniMartc2cOrderInfo.php`, `scripts/SDK_PHP/example/Logistics/Domestic/PrintHilifec2cOrderInfo.php`, `scripts/SDK_PHP/example/Logistics/Domestic/PrintOkmartc2cOrderInfo.php`

| 超商 | 端點 | 參數 |
|------|------|------|
| 全家 | /Express/PrintFAMIC2COrderInfo | MerchantID, AllPayLogisticsID, CVSPaymentNo |
| 統一 | /Express/PrintUniMartC2COrderInfo | + CVSValidationNo |
| 萊爾富 | /Express/PrintHILIFEC2COrderInfo | MerchantID, AllPayLogisticsID, CVSPaymentNo |
| OK | /Express/PrintOKMARTC2COrderInfo | MerchantID, AllPayLogisticsID, CVSPaymentNo |

## 查詢門市清單

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/GetStoreList.php`

```php
$postService = $factory->create('PostWithCmvJsonResponseService');
$input = [
    'MerchantID' => '2000132',
    'CvsType'    => 'All',  // All/FAMI/UNIMART/HILIFE/OKMART/UNIMARTFREEZE
];
$response = $postService->post($input, 'https://logistics-stage.ecpay.com.tw/Helper/GetStoreList');
```

## 建立測試資料

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/CreateTestData.php`

```php
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');
$input = [
    'MerchantID'       => '2000132',
    'LogisticsSubType' => 'FAMI',
    'ClientReplyURL'   => 'https://你的網站/ecpay/test-data-result',
];
echo $autoSubmitFormService->generate($input, 'https://logistics-stage.ecpay.com.tw/Express/CreateTestData');
```

### 處理測試資料結果

> 原始範例：`scripts/SDK_PHP/example/Logistics/Domestic/GetCreateTestDataResponse.php`

## 完整範例檔案對照（24 個）

| 檔案 | 用途 | SDK Service |
|------|------|-------------|
| Map.php | 電子地圖 | AutoSubmitFormWithCmvService |
| GetMapResponse.php | 地圖結果 | ArrayResponse |
| CreateCvs.php | 超商建單 | PostWithCmvStrResponseService |
| CreateCvsForm.php | 超商建單（表單） | AutoSubmitFormWithCmvService |
| CreateUnimartFreeze.php | 冷凍取貨 | PostWithCmvStrResponseService |
| CreateHome.php | 宅配建單 | PostWithCmvStrResponseService |
| CreateHomeForm.php | 宅配建單（表單） | AutoSubmitFormWithCmvService |
| GetLogisticStatueResponse.php | 狀態通知 | VerifiedArrayResponse |
| ReturnFamiCvs.php | 全家退貨 | PostWithCmvStrResponseService |
| ReturnUniMartCvs.php | 統一退貨 | PostWithCmvStrResponseService |
| ReturnHome.php | 宅配退貨 | PostWithCmvStrResponseService |
| GetReturnResponse.php | 退貨回應 | VerifiedArrayResponse |
| UpdateShipmentInfo.php | 更新出貨 | PostWithCmvStrResponseService |
| UpdateStoreInfo.php | 更新門市 | PostWithCmvStrResponseService |
| CancelC2cOrder.php | 取消C2C | PostWithCmvStrResponseService |
| QueryLogisticsTradeInfo.php | 查詢 | PostWithCmvVerifiedEncodedStrResponseService |
| PrintTradeDocument.php | 列印 | AutoSubmitFormWithCmvService |
| PrintFamic2cOrderInfo.php | 全家C2C列印 | AutoSubmitFormWithCmvService |
| PrintUniMartc2cOrderInfo.php | 統一C2C列印 | AutoSubmitFormWithCmvService |
| PrintHilifec2cOrderInfo.php | 萊爾富C2C列印 | AutoSubmitFormWithCmvService |
| PrintOkmartc2cOrderInfo.php | OKC2C列印 | AutoSubmitFormWithCmvService |
| GetStoreList.php | 門市清單 | PostWithCmvJsonResponseService |
| CreateTestData.php | 測試資料 | AutoSubmitFormWithCmvService |
| GetCreateTestDataResponse.php | 測試資料結果 | VerifiedArrayResponse |

> ⚠️ **安全必做清單（ServerReplyURL）**
> 1. 驗證 MerchantID 為自己的
> 2. 比對物流單號與訂單記錄
> 3. 防重複處理（記錄已處理的 AllPayLogisticsID）
> 4. 異常時仍回應 `1|OK`（避免重送風暴）
> 5. 記錄完整日誌（遮蔽 HashKey/HashIV）
> 6. CheckMacValue 驗證**必須**使用 timing-safe 比較函式（見 [guides/13](./13-checkmacvalue.md) 各語言實作），禁止使用 `==` 或 `===` 直接比對

> ℹ️ 國內物流 API 不支援多件合併出貨，每筆訂單對應一個物流單號。跨境退貨請參閱 [guides/08（跨境物流）](./08-logistics-crossborder.md)。

## 相關文件

- 官方 API 規格：`references/Logistics/物流整合API技術文件.md`（36 個 URL）
- 物流狀態碼：`scripts/SDK_PHP/example/Logistics/logistics_status.xlsx`
- CheckMacValue：[guides/13-checkmacvalue.md](./13-checkmacvalue.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
